<?php
/**
 * SEARAH — Trip & manifest (model "terima jadi" yang disusun admin).
 * create -> draft ; attach -> trip_bookings (seat & urutan stop) ;
 * confirm -> status dikonfirmasi + trip_events + tandai ketersediaan supir/mobil on_trip.
 */
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/Response.php';
require_once __DIR__ . '/Scope.php';
require_once __DIR__ . '/Settings.php';

final class Trip
{
    public static function create(array $in, array $admin): array
    {
        $package = strtolower(trim($in['package'] ?? ''));
        if (!in_array($package, ['reguler', 'door_to_door', 'private'], true)) {
            throw new AppException('Paket trip tidak valid.');
        }
        $date = trim($in['trip_date'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) throw new AppException('trip_date wajib (YYYY-MM-DD).');

        $routeId = isset($in['route_id']) ? (int)$in['route_id'] : null;
        if (!$routeId && !empty($in['origin']) && !empty($in['dest'])) {
            $routeId = self::routeIdByCities($in['origin'], $in['dest']);
        }

        $vehicleId = isset($in['vehicle_id']) ? (int)$in['vehicle_id'] ?: null : null;
        $driverId  = isset($in['driver_id'])  ? (int)$in['driver_id']  ?: null : null;
        $capacity  = isset($in['capacity']) ? (int)$in['capacity'] : null;
        if (!$capacity && $vehicleId) $capacity = self::vehicleSeats($vehicleId);

        Scope::assertRoute($admin,(int)$routeId);
        $now  = date('Y-m-d H:i:s');
        $code = self::uniqueCode('SRH-TRIP-', 'trips');
        $dep  = trim($in['departure_time'] ?? '') ?: null;
        $road = (string)($in['road_type'] ?? 'biasa');
        if (!in_array($road, ['biasa', 'tol'], true)) $road = 'biasa';

        $st = db()->prepare(
            "INSERT INTO trips
             (code, route_id, package_type, trip_date, departure_time, origin_garage_id, dest_garage_id,
              road_type, vehicle_id, driver_id, capacity, seats_booked, status, created_at, updated_at)
             VALUES (?,?,?,?,?,?,?, ?,?,?,?, 0, 'draft', ?, ?)"
        );
        $st->execute([
            $code, $routeId, $package, $date, $dep,
            (int)($in['origin_garage_id'] ?? 0) ?: null, (int)($in['dest_garage_id'] ?? 0) ?: null,
            $road,
            $vehicleId, $driverId, $capacity, $now, $now,
        ]);
        $tripId = (int)db()->lastInsertId();
        self::event($tripId, 'ditetapkan', (int)$admin['id']);
        return self::detail($tripId, $admin);
    }

    /** Tempel booking ke trip (manifest). */
    public static function attach(int $tripId, int $bookingId, array $admin): array
    {
        $pdo = db();
        $trip = self::find($tripId);
        if (!$trip) throw new AppException('Trip tidak ditemukan.', 404);
        Scope::assertRoute($admin, (int)$trip['route_id']);
        if (in_array($trip['status'], ['selesai', 'dibatalkan'], true)) throw new AppException('Trip tidak bisa diubah.', 422);

        $bk = $pdo->prepare("SELECT * FROM bookings WHERE id = ?");
        $bk->execute([$bookingId]);
        $booking = $bk->fetch();
        if (!$booking) throw new AppException('Pesanan tidak ditemukan.', 404);
        if ((int)$booking['route_id'] !== (int)$trip['route_id'] && $trip['route_id']) {
            throw new AppException('Rute pesanan tidak sama dengan rute trip.', 422);
        }

        $dup = $pdo->prepare("SELECT 1 FROM trip_bookings WHERE booking_id = ?");
        $dup->execute([$bookingId]);
        if ($dup->fetch()) throw new AppException('Pesanan sudah masuk trip.', 409);

        Scope::assertRoute($admin, (int)$booking['route_id']);
        if ($trip['status'] !== 'draft' || $booking['status'] !== 'dibayar') throw new AppException('Booking harus lunas dan trip harus draft.', 409);
        if ($booking['package_type'] !== $trip['package_type'] || $booking['trip_date'] !== $trip['trip_date'] || $booking['road_type'] !== $trip['road_type']) throw new AppException('Paket, tanggal atau jalan tidak cocok.', 422);
        if ($trip['package_type'] === 'private' && (int)$trip['seats_booked'] > 0) throw new AppException('Private eksklusif satu booking.', 409);
        if ($booking['selected_vehicle_id'] && (int)$booking['selected_vehicle_id'] !== (int)$trip['vehicle_id']) throw new AppException('Mobil pilihan tidak cocok.', 422);
        if ($booking['selected_driver_id'] && (int)$booking['selected_driver_id'] !== (int)$trip['driver_id']) throw new AppException('Supir pilihan tidak cocok.', 422);
        if ($trip['capacity'] === null || (int)$trip['seats_booked'] + (int)$booking['pax_count'] > (int)$trip['capacity']) {
            throw new AppException('Kapasitas trip penuh.', 422);
        }
        $seq     = (int)$trip['seats_booked'] + 1;
        $seatNo  = $seq; // sederhana: urut sesuai urutan penambahan
        $geo=Settings::geometry($booking);
        if($geo['detour_km']>(float)Settings::get('match_max_detour_km',20) || $geo['bearing_delta']>(float)Settings::get('match_max_bearing_degrees',45)) throw new AppException('Detour/arah di luar toleransi.',422);
        if(abs(strtotime($booking['trip_date'].' '.$booking['preferred_time'])-strtotime($trip['trip_date'].' '.$trip['departure_time']))>Settings::int('match_time_window_minutes',60)*60) throw new AppException('Jam di luar toleransi.',422);
        $now  = date('Y-m-d H:i:s');

        $pdo->prepare(
            "INSERT INTO trip_bookings (trip_id, booking_id, seat_no, pickup_stop_order, drop_stop_order, stop_status)
             VALUES (?,?,?,?,?, 'menunggu')"
        )->execute([$tripId, $bookingId, $seatNo, $seq, $seq]);

        $pdo->prepare('UPDATE trips SET seats_booked = seats_booked + ?, updated_at = ? WHERE id = ?')
            ->execute([(int)$booking['pax_count'], $now, $tripId]);
        $pdo->prepare("UPDATE bookings SET status = 'menunggu_konfirmasi_admin', updated_at = ? WHERE id = ? AND status IN ('dibayar','menunggu_pencocokan','draft')")
            ->execute([$now, $bookingId]);

        return ['trip_id' => $tripId, 'booking_id' => $bookingId, 'seat_no' => $seatNo, 'stop_order' => $seq];
    }

    /** Konfirmasi trip -> bookings dikonfirmasi + trip_events + ketersediaan on_trip. */
    public static function confirm(int $tripId, array $admin): array
    {
        $pdo = db();
        $trip = self::find($tripId);
        if (!$trip) throw new AppException('Trip tidak ditemukan.', 404);
        Scope::assertRoute($admin, (int)$trip['route_id']);
        if ($trip['status'] === 'dikonfirmasi') return self::detail($tripId, $admin); // idempotent
        if (!in_array($trip['status'], ['draft'], true)) throw new AppException('Hanya trip draft yang bisa dikonfirmasi.', 422);

        if (!$trip['vehicle_id'] || !$trip['driver_id'] || !$trip['departure_time'] || (int)$trip['seats_booked'] < 1) throw new AppException('Mobil, supir, jam dan manifest wajib.', 422);
        $v = $pdo->prepare('SELECT v.*, g.is_active AS garage_active, c.region_id FROM vehicles v JOIN garages g ON g.id=v.current_garage_id JOIN cities c ON c.id=g.city_id WHERE v.id=?');
        $v->execute([$trip['vehicle_id']]); $vehicle = $v->fetch();
        if (!$vehicle || $vehicle['status'] !== 'aktif' || !$vehicle['garage_active'] || $vehicle['serves_package'] !== $trip['package_type'] || (int)$trip['capacity'] > (int)$vehicle['seat_capacity']) throw new AppException('Mobil/kapasitas tidak valid.', 422);
        Scope::assertRegion($admin, (int)$vehicle['region_id']);
        $d = $pdo->prepare('SELECT u.status AS account_status, d.status, d.current_region_id FROM drivers d JOIN users u ON u.id=d.user_id WHERE d.user_id=?');
        $d->execute([$trip['driver_id']]); $driver = $d->fetch();
        if (!$driver || $driver['account_status'] !== 'aktif' || !in_array($driver['status'], ['disetujui','peringatan'], true)) throw new AppException('Supir tidak aktif/terverifikasi.', 422);
        Scope::assertRegion($admin, $driver['current_region_id'] ? (int)$driver['current_region_id'] : null);
        $manifest=$pdo->prepare('SELECT b.* FROM bookings b JOIN trip_bookings tb ON tb.booking_id=b.id WHERE tb.trip_id=?');$manifest->execute([$tripId]);
        foreach($manifest->fetchAll() as $b){
            $geo=Settings::geometry($b);
            $pos=$pdo->prepare('SELECT d.assign_radius_km,g.lat,g.lng FROM drivers d JOIN garages g ON g.id=d.current_garage_id WHERE d.user_id=?');$pos->execute([$trip['driver_id']]);$location=$pos->fetch();
            if(!$location || Settings::distance((float)$location['lat'],(float)$location['lng'],$geo['pickup'][0],$geo['pickup'][1])>(float)($location['assign_radius_km']??Settings::get('default_assign_radius_km',30)))throw new AppException('Supir di luar radius penugasan.',409);
        }
        if(!Settings::available('vehicle',(int)$trip['vehicle_id'],$trip['trip_date'],$trip['departure_time'],0,$tripId) || !Settings::available('driver',(int)$trip['driver_id'],$trip['trip_date'],$trip['departure_time'],0,$tripId))throw new AppException('Resource bentrok dengan reservasi.',409);
        self::markAvailability($trip);
        $now = date('Y-m-d H:i:s');
        $pdo->prepare("UPDATE trips SET status='dikonfirmasi', confirmed_by=?, confirmed_at=?, updated_at=? WHERE id=?")
            ->execute([$admin['id'], $now, $now, $tripId]);
        $pdo->prepare("UPDATE bookings SET status='dikonfirmasi', updated_at=?
                       WHERE id IN (SELECT booking_id FROM trip_bookings WHERE trip_id=?)")
            ->execute([$now, $tripId]);
        self::event($tripId, 'dikonfirmasi', (int)$admin['id']);
        $pdo->prepare('UPDATE invoices SET trip_id=? WHERE booking_id IN (SELECT booking_id FROM trip_bookings WHERE trip_id=?)')->execute([$tripId,$tripId]);
        return self::detail($tripId, $admin);
    }

    public static function find(int $tripId): ?array
    {
        $st = db()->prepare("SELECT * FROM trips WHERE id = ?");
        $st->execute([$tripId]);
        return $st->fetch() ?: null;
    }

    public static function list(array $admin): array
    {
        $regions = Scope::regionIds($admin);
        $sql = "SELECT t.*, o.name AS origin_city, d.name AS dest_city,
                       u.name AS driver_name, v.plate, v.model
                FROM trips t
                LEFT JOIN routes r ON r.id = t.route_id
                LEFT JOIN cities o ON o.id = r.origin_city_id
                LEFT JOIN cities d ON d.id = r.dest_city_id
                LEFT JOIN users u ON u.id = t.driver_id
                LEFT JOIN vehicles v ON v.id = t.vehicle_id
                WHERE 1=1";
        $params = [];
        if ($regions) { $sql .= " AND o.region_id IN (" . implode(',', array_fill(0, count($regions), '?')) . ")"; $params = array_merge($params, $regions); }
        $sql .= " ORDER BY t.trip_date DESC, t.id DESC";
        $st = db()->prepare($sql); $st->execute($params);
        return $st->fetchAll();
    }

    public static function detail(int $tripId, array $admin): array
    {
        $trip = self::find($tripId);
        if (!$trip) throw new AppException('Trip tidak ditemukan.', 404);
        Scope::assertRoute($admin, (int)$trip['route_id']);
        $st = db()->prepare(
            "SELECT tb.*, b.code AS booking_code, b.package_type, b.pax_count, b.origin_city, b.dest_city,
                    b.pickup_address, b.drop_address, b.status AS booking_status, u.name AS passenger_name
             FROM trip_bookings tb
             JOIN bookings b ON b.id = tb.booking_id
             JOIN users u ON u.id = b.passenger_user_id
             WHERE tb.trip_id = ? ORDER BY tb.pickup_stop_order"
        );
        $st->execute([$tripId]);
        $trip['manifest'] = $st->fetchAll();
        return $trip;
    }

    /** Tandai supir & mobil on_trip pada kalender ketersediaan trip tsb. */
    private static function markAvailability(array $trip): void
    {
        $date = $trip['trip_date'];
        $dep  = $trip['departure_time'] ?: '08:00';
        $minutes = Settings::int('match_duration_minutes', 240);
        if (!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/', $dep) || strtotime($date . ' ' . $dep . ' +' . $minutes . ' minutes') >= strtotime($date . ' +1 day')) throw new AppException('Jam tidak valid atau melewati tengah malam; jadwal lintas hari belum didukung.', 422);
        $end = date('H:i', strtotime($date . ' ' . $dep . ' +' . $minutes . ' minutes'));
        $now  = date('Y-m-d H:i:s');

        if ($trip['driver_id']) {
            self::upsertAvailability('driver_availability', 'driver_user_id', (int)$trip['driver_id'], $date, $dep, $end, (int)$trip['id'], $now, $trip['origin_garage_id']);
        }
        if ($trip['vehicle_id']) {
            self::upsertAvailability('vehicle_availability', 'vehicle_id', (int)$trip['vehicle_id'], $date, $dep, $end, (int)$trip['id'], $now, $trip['origin_garage_id']);
        }
    }

    private static function upsertAvailability(string $table, string $col, int $resId, string $date, string $start, string $end, int $tripId, string $now, $garageId): void
    {
        $pdo = db();
        $conflict = $pdo->prepare("SELECT id FROM {$table} WHERE {$col}=? AND work_date=? AND status <> 'available' AND start_time < ? AND end_time > ? AND (trip_id IS NULL OR trip_id <> ?)");
        $conflict->execute([$resId,$date,$end,$start,$tripId]);
        if ($conflict->fetchColumn()) throw new AppException('Jadwal mobil/supir bentrok.', 409);
        $st = $pdo->prepare("SELECT id FROM {$table} WHERE {$col} = ? AND work_date = ? AND start_time = ?");
        $st->execute([$resId, $date, $start]);
        $id = $st->fetchColumn();
        if ($id) {
            $pdo->prepare("UPDATE {$table} SET status='on_trip', trip_id=?, end_time=?, updated_at=? WHERE id=?")
                ->execute([$tripId, $end, $now, $id]);
        } else {
            $g = $garageId ?: null;
            if ($table === 'driver_availability') {
                $pdo->prepare("INSERT INTO {$table} (driver_user_id, work_date, start_time, end_time, garage_id, status, trip_id, created_at, updated_at) VALUES (?,?,?,?,?, 'on_trip', ?, ?, ?)")
                    ->execute([$resId, $date, $start, $end, $g, $tripId, $now, $now]);
            } else {
                $pdo->prepare("INSERT INTO {$table} (vehicle_id, work_date, start_time, end_time, garage_id, status, trip_id, created_at, updated_at) VALUES (?,?,?,?,?, 'on_trip', ?, ?, ?)")
                    ->execute([$resId, $date, $start, $end, $g, $tripId, $now, $now]);
            }
        }
    }

    private static function event(int $tripId, string $event, int $actorId, ?string $note = null): void
    {
        db()->prepare("INSERT INTO trip_events (trip_id, event, actor_user_id, note, created_at) VALUES (?,?,?,?,?)")
            ->execute([$tripId, $event, $actorId, $note, date('Y-m-d H:i:s')]);
    }

    private static function routeIdByCities(string $origin, string $dest): ?int
    {
        $st = db()->prepare(
            "SELECT r.id FROM routes r
             JOIN cities o ON o.id=r.origin_city_id JOIN cities d ON d.id=r.dest_city_id
             WHERE lower(o.name)=lower(?) AND lower(d.name)=lower(?) LIMIT 1"
        );
        $st->execute([$origin, $dest]);
        $id = $st->fetchColumn();
        return $id ? (int)$id : null;
    }

    private static function vehicleSeats(int $vehicleId): ?int
    {
        $st = db()->prepare("SELECT seat_capacity FROM vehicles WHERE id=?");
        $st->execute([$vehicleId]);
        $v = $st->fetchColumn();
        return $v ? (int)$v : null;
    }

    private static function uniqueCode(string $prefix, string $table): string
    {
        for ($i = 0; $i < 25; $i++) {
            $code = $prefix . str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $st = db()->prepare("SELECT 1 FROM {$table} WHERE code = ?");
            $st->execute([$code]);
            if (!$st->fetch()) return $code;
        }
        throw new AppException('Gagal membuat kode trip.', 500);
    }
}
