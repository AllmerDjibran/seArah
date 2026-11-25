<?php
/**
 * SEARAH — Smart Match: usulkan pengelompokan booking -> trip (demand-driven).
 * Hasil bersifat USULAN (match_suggestions) yang ditinjau admin sebelum dikonfirmasi.
 *  - Reguler/Door-to-Door: usulkan trip se-rute & se-tanggal yang masih ada kursi
 *    (skor lebih tinggi utk yang lebih terisi), + opsi buat trip baru.
 *  - Private: usulkan trip khusus (1 rombongan), pakai mobil/supir terpilih bila ada.
 */
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/Response.php';
require_once __DIR__ . '/Scope.php';
require_once __DIR__ . '/Trip.php';

final class SmartMatch
{
    /** Bangkitkan usulan utk semua booking berstatus 'dibayar' dalam scope admin. */
    public static function run(array $admin): array
    {
        $regions = Scope::regionIds($admin);
        $sql = "SELECT b.*, c.region_id
                FROM bookings b
                JOIN routes r ON r.id = b.route_id
                JOIN cities c ON c.id = r.origin_city_id
                WHERE b.status = 'dibayar'";
        $params = [];
        if ($regions) { $sql .= " AND c.region_id IN (" . implode(',', array_fill(0, count($regions), '?')) . ")"; $params = $regions; }
        $st = db()->prepare($sql); $st->execute($params);
        $bookings = $st->fetchAll();

        $created = 0;
        foreach ($bookings as $b) {
            // refresh: buang usulan pending lama utk booking ini
            db()->prepare("DELETE FROM match_suggestions WHERE booking_id = ? AND status = 'pending'")
                ->execute([$b['id']]);
            $created += self::suggestFor((int)$b['id'], $b);
        }
        return ['processed_bookings' => count($bookings), 'suggestions_created' => $created];
    }

    /** @return int jumlah usulan dibuat */
    private static function suggestFor(int $bookingId, array $b): int
    {
        $pdo = db();
        $now = date('Y-m-d H:i:s');

        if ($b['package_type'] === 'private') {
            $factors = [
                'reason' => 'private_dedicated',
                'selected_vehicle_id' => $b['selected_vehicle_id'],
                'selected_driver_id'  => $b['selected_driver_id'],
            ];
            $pdo->prepare(
                "INSERT INTO match_suggestions (booking_id, target_trip_id, batch_key, score, factors_json, status, created_at)
                 VALUES (?, NULL, ?, 100, ?, 'pending', ?)"
            )->execute([$bookingId, 'PRIV-' . $b['code'], json_encode($factors, JSON_UNESCAPED_UNICODE), $now]);
            return 1;
        }

        $n = 0;
        // kandidat trip existing se-rute/tanggal/paket dgn sisa kursi
        $cand = $pdo->prepare(
            "SELECT * FROM trips
             WHERE route_id = ? AND package_type = ? AND trip_date = ? AND status IN ('draft','dikonfirmasi')
               AND (capacity IS NULL OR seats_booked < capacity)
             ORDER BY (seats_booked * 1.0 / NULLIF(capacity,0)) DESC, id ASC LIMIT 3"
        );
        $cand->execute([$b['route_id'], $b['package_type'], $b['trip_date']]);
        foreach ($cand->fetchAll() as $t) {
            if (!$t['capacity'] || (int)$t['seats_booked'] + (int)$b['pax_count'] > (int)$t['capacity'] || $t['road_type'] !== $b['road_type'] || $t['status'] !== 'draft') continue;
            if ($b['selected_vehicle_id'] && (int)$b['selected_vehicle_id'] !== (int)$t['vehicle_id']) continue;
            if (!$b['preferred_time'] || !$t['departure_time']) continue;
            $delta = abs(strtotime($b['trip_date'].' '.$b['preferred_time']) - strtotime($t['trip_date'].' '.$t['departure_time'])) / 60;
            $window = Settings::int('match_time_window_minutes', 60);
            if ($delta > $window) continue;
            $geo=Settings::geometry($b);
            if($geo['detour_km']>(float)Settings::get('match_max_detour_km',20)||$geo['bearing_delta']>(float)Settings::get('match_max_bearing_degrees',45))continue;
            $cap = (int)$t['capacity'];
            $fill = $cap > 0 ? min(1, (int)$t['seats_booked'] / $cap) : 0;
            $score = round(Settings::int('match_base_score', 50) + $fill * Settings::int('match_fill_weight', 50) - $delta, 2);
            $factors = ['reason' => 'same_route_date', 'fill_ratio' => $fill, 'seats_booked' => (int)$t['seats_booked'], 'capacity' => $t['capacity']];
            $pdo->prepare(
                "INSERT INTO match_suggestions (booking_id, target_trip_id, batch_key, score, factors_json, status, created_at)
                 VALUES (?,?,NULL,?,?, 'pending', ?)"
            )->execute([$bookingId, (int)$t['id'], $score, json_encode($factors, JSON_UNESCAPED_UNICODE), $now]);
            $n++;
        }

        // opsi buat trip baru utk booking ini
        $batch = 'NEW-' . $b['route_id'] . '-' . $b['trip_date'] . '-' . $b['package_type'];
        $factors = ['reason' => 'create_new_trip', 'route_id' => (int)$b['route_id'], 'trip_date' => $b['trip_date'], 'package' => $b['package_type']];
        $pdo->prepare(
            "INSERT INTO match_suggestions (booking_id, target_trip_id, batch_key, score, factors_json, status, created_at)
             VALUES (?, NULL, ?, 40, ?, 'pending', ?)"
        )->execute([$bookingId, $batch, json_encode($factors, JSON_UNESCAPED_UNICODE), $now]);
        $n++;

        return $n;
    }

    /** Daftar usulan pending ( utk tinjauan admin ). */
    public static function listPending(array $admin): array
    {
        $regions = Scope::regionIds($admin);
        $sql = "SELECT m.*, b.code AS booking_code, b.package_type, b.origin_city, b.dest_city,
                       b.trip_date, b.pax_count, b.selected_vehicle_id, b.selected_driver_id,
                       t.code AS trip_code, t.seats_booked, t.capacity, t.status AS trip_status
                FROM match_suggestions m
                JOIN bookings b ON b.id = m.booking_id
                LEFT JOIN routes r ON r.id = b.route_id
                LEFT JOIN cities c ON c.id = r.origin_city_id
                LEFT JOIN trips t ON t.id = m.target_trip_id
                WHERE m.status = 'pending'";
        $params = [];
        if ($regions) { $sql .= " AND c.region_id IN (" . implode(',', array_fill(0, count($regions), '?')) . ")"; $params = $regions; }
        $sql .= " ORDER BY b.id ASC, m.score DESC";
        $st = db()->prepare($sql); $st->execute($params);
        return $st->fetchAll();
    }

    /** Terima usulan: tempel ke trip target ATAU buat trip baru, lalu attach. */
    public static function accept(int $suggestionId, array $admin, array $opts = []): array
    {
        $pdo = db();
        $st = $pdo->prepare("SELECT * FROM match_suggestions WHERE id = ?");
        $st->execute([$suggestionId]);
        $sugg = $st->fetch();
        if (!$sugg) throw new AppException('Usulan tidak ditemukan.', 404);
        if ($sugg['status'] !== 'pending') throw new AppException('Usulan sudah diproses.', 409);

        $bk = $pdo->prepare("SELECT * FROM bookings WHERE id = ?");
        $bk->execute([$sugg['booking_id']]);
        $booking = $bk->fetch();
        if ($booking) Scope::assertRoute($admin, (int)$booking['route_id']);
        if (!$booking) throw new AppException('Pesanan tidak ditemukan.', 404);

        $tripId = !empty($opts['target_trip_id']) ? (int)$opts['target_trip_id'] : ($sugg['target_trip_id'] ? (int)$sugg['target_trip_id'] : null);
        $action = 'attached_existing';

        if (!$tripId) {
            // buat trip baru dari booking ini
            $in = [
                'package'          => $booking['package_type'],
                'route_id'         => $booking['route_id'],
                'trip_date'        => $booking['trip_date'],
                'departure_time'   => $booking['preferred_time'] ?: null,
                'origin_garage_id' => self::firstGarageByCity($booking['origin_city']),
                'dest_garage_id'   => self::firstGarageByCity($booking['dest_city']),
                'road_type'        => $booking['road_type'],
                'vehicle_id'       => $booking['selected_vehicle_id'],
                'driver_id'        => $booking['selected_driver_id'],
                'capacity'         => $booking['package_type'] === 'private' ? $booking['pax_count'] : null,
            ];
            $trip = Trip::create($in, $admin);
            $tripId = (int)$trip['id'];
            $action = 'created_new_trip';
        }

        $attach = Trip::attach($tripId, (int)$sugg['booking_id'], $admin);

        $now = date('Y-m-d H:i:s');
        $pdo->prepare("UPDATE match_suggestions SET status='diterima', reviewed_by=?, reviewed_at=? WHERE id=?")
            ->execute([$admin['id'], $now, $suggestionId]);
        // tolak usulan lain utk booking yang sama (hindari duplikasi)
        $pdo->prepare("UPDATE match_suggestions SET status='ditolak', reviewed_by=?, reviewed_at=? WHERE booking_id=? AND id<>? AND status='pending'")
            ->execute([$admin['id'], $now, $sugg['booking_id'], $suggestionId]);

        return ['action' => $action, 'trip_id' => $tripId, 'attach' => $attach];
    }

    public static function reject(int $suggestionId, array $admin): array
    {
        $st = db()->prepare("SELECT * FROM match_suggestions WHERE id = ?");
        $st->execute([$suggestionId]);
        $s = $st->fetch();
        if (!$s) throw new AppException('Usulan tidak ditemukan.', 404);
        $booking = Booking::findOwned((int)$s['booking_id'], $admin);
        if ($s['status'] !== 'pending') throw new AppException('Usulan sudah diproses.', 409);
        db()->prepare("UPDATE match_suggestions SET status='ditolak', reviewed_by=?, reviewed_at=? WHERE id=?")
            ->execute([$admin['id'], date('Y-m-d H:i:s'), $suggestionId]);
        return ['id' => $suggestionId, 'status' => 'ditolak'];
    }

    private static function firstGarageByCity(string $city): ?int
    {
        $st = db()->prepare("SELECT g.id FROM garages g JOIN cities c ON c.id=g.city_id WHERE lower(c.name)=lower(?) AND g.is_active=1 ORDER BY g.id LIMIT 1");
        $st->execute([$city]);
        $id = $st->fetchColumn();
        return $id ? (int)$id : null;
    }
}
