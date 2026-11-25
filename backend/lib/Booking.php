<?php
/**
 * SEARAH — Booking: validasi + hitung tarif + buat pesanan & invoice.
 * ------------------------------------------------------------------
 * Model demand-driven: booking dibuat dengan status 'menunggu_pembayaran'.
 * Pembayaran (Duitku) & konfirmasi admin (Smart Match -> trip) = modul terpisah.
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/Response.php';
require_once __DIR__ . '/Pricing.php';
require_once __DIR__ . '/Scope.php';
require_once __DIR__ . '/Settings.php';

final class Booking
{
    public const MAX_PAX = 7;

    /** Buat booking + invoice (satu transaksi). Return ['booking'=>..,'invoice'=>..]. */
    public static function create(array $in, int $userId): array
    {
        $package = strtolower(trim($in['package'] ?? $in['paket'] ?? ''));
        if (!in_array($package, Pricing::PACKAGES, true)) {
            throw new AppException('Paket tidak valid (reguler / door_to_door / private).');
        }

        $origin = trim($in['origin'] ?? $in['asal'] ?? '');
        $dest   = trim($in['dest']   ?? $in['tujuan'] ?? '');
        if ($origin === '' || $dest === '')      throw new AppException('Kota asal & tujuan wajib diisi.');
        if (mb_strtolower($origin) === mb_strtolower($dest)) throw new AppException('Kota asal & tujuan tidak boleh sama.');

        $tripDate = trim($in['trip_date'] ?? $in['tanggal'] ?? '');
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $tripDate);
        if (!$parsed || $parsed->format('Y-m-d') !== $tripDate || $tripDate < date('Y-m-d')) {
            throw new AppException('Tanggal berangkat tidak valid (format YYYY-MM-DD).');
        }

        $pax = (int)($in['pax'] ?? $in['jumlah'] ?? 1);
        if ($pax < 1 || $pax > self::MAX_PAX) {
            throw new AppException('Jumlah penumpang 1-' . self::MAX_PAX . '.');
        }

        $road = strtolower(trim($in['road'] ?? $in['jalan'] ?? 'biasa'));
        if (!in_array($road, ['biasa', 'tol'], true)) throw new AppException('Jenis jalan tidak valid.', 422);
        $time = (string)($in['time'] ?? $in['jam'] ?? '');
        if (!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/', $time) || strtotime($tripDate . ' ' . $time) <= time()) throw new AppException('Jam berangkat wajib dan harus di masa depan.', 422);

        $insurance = self::bool($in['insurance'] ?? $in['asuransi'] ?? 0);

        // Titik jemput/antar: Reguler = garasi; D2D/Private = alamat sendiri
        $needsAddress = in_array($package, ['door_to_door', 'private'], true);
        $pickupAddress = trim($in['pickup_address'] ?? $in['jemput'] ?? '');
        $dropAddress   = trim($in['drop_address']   ?? $in['antar'] ?? '');
        if ($needsAddress && ($pickupAddress === '' || $dropAddress === '')) {
            throw new AppException('Paket ' . $package . ' wajib mengisi titik jemput & drop off.');
        }

        if ($needsAddress) {
            foreach (['pickup_lat'=>90,'pickup_lng'=>180,'drop_lat'=>90,'drop_lng'=>180] as $key=>$limit) {
                if (!isset($in[$key]) || !is_numeric($in[$key]) || !is_finite((float)$in[$key]) || abs((float)$in[$key]) > $limit) throw new AppException('Koordinat jemput/drop wajib dan valid.', 422);
            }
        }
        // Resolve rute + hitung tarif
        $route = Pricing::resolveRoute($origin, $dest);
        $fare  = Pricing::fare((int)$route['id'], $package);
        $calc  = Pricing::compute($fare, $pax, $road, $insurance);

        // Pilihan mobil/supir (opsional; utk Private terpisah)
        $vehicleId = isset($in['vehicle_id']) ? (int)$in['vehicle_id'] : null;
        $driverId  = isset($in['driver_id'])  ? (int)$in['driver_id']  : null;
        self::assertVehicle($vehicleId, $package);
        if ($package === 'private') {
            self::assertDriver($driverId);
        } else {
            $driverId = null; // reguler & door_to_door: supir assigned admin
        }

        if ($package === 'private') {
            if (!$vehicleId || !$driverId) throw new AppException('Private wajib memilih mobil dan supir terpisah.',422);
            $choices=Settings::choices($in);
            if (!in_array($vehicleId,array_map('intval',array_column($choices['vehicles'],'id')),true) || !in_array($driverId,array_map('intval',array_column($choices['drivers'],'user_id')),true)) throw new AppException('Mobil/supir Private tidak tersedia atau di luar radius.',409);
        }
        $pdo = db();
        $localTransaction = empty($GLOBALS['api_transaction']);
        if ($localTransaction) $pdo->beginTransaction();
        try {
            $bookingCode = self::uniqueCode('SRH-', 'bookings');
            $now = date('Y-m-d H:i:s');

            $st = $pdo->prepare(
                "INSERT INTO bookings
                 (code, passenger_user_id, package_type, route_id, origin_city, dest_city,
                  trip_date, preferred_time, pax_count, road_type, insurance,
                  pickup_type, pickup_address, pickup_lat, pickup_lng,
                  drop_type, drop_address, drop_lat, drop_lng,
                  selected_vehicle_id, selected_driver_id,
                  fare_base, fare_toll, fare_protection, fare_total, status, created_at, updated_at)
                 VALUES
                 (?,?,?,?,?,?, ?,?,?,?,?, ?,?,?,?, ?,?,?,?, ?,?, ?,?,?,?, 'menunggu_pembayaran', ?, ?)"
            );
            $st->execute([
                $bookingCode, $userId, $package, (int)$route['id'], $origin, $dest,
                $tripDate, ($in['time'] ?? $in['jam'] ?? null) ?: null, $pax, $road, $insurance ? 1 : 0,
                $needsAddress ? 'alamat' : 'garasi', $pickupAddress ?: null,
                self::num($in['pickup_lat'] ?? null), self::num($in['pickup_lng'] ?? null),
                $needsAddress ? 'alamat' : 'garasi', $dropAddress ?: null,
                self::num($in['drop_lat'] ?? null), self::num($in['drop_lng'] ?? null),
                $vehicleId, $driverId,
                $calc['base'], $calc['toll'], $calc['protection'], $calc['total'],
                $now, $now,
            ]);
            $bookingId = (int)$pdo->lastInsertId();

            // Invoice (draft) — total mengikuti booking
            $invoiceCode = self::uniqueCode('INV-', 'invoices');
            $pdo->prepare(
                "INSERT INTO invoices
                 (code, booking_id, passenger_user_id, subtotal_base, subtotal_toll,
                  subtotal_protection, discount, total, status, issued_at, created_at)
                 VALUES (?,?,?,?,?,?, 0, ?, 'draft', ?, ?)"
            )->execute([
                $invoiceCode, $bookingId, $userId,
                $calc['base'], $calc['toll'], $calc['protection'], $calc['total'],
                $now, $now,
            ]);
            $invoiceId = (int)$pdo->lastInsertId();

            if ($localTransaction) $pdo->commit();
        } catch (Throwable $e) {
            if ($localTransaction) $pdo->rollBack();
            throw $e;
        }

        return [
            'booking' => self::find($bookingId),
            'invoice' => self::findInvoice($invoiceId),
            'calc'    => $calc,
        ];
    }

    public static function cancel(int $id, array $user, string $reason): array
    {
        $b=self::findOwned($id,$user);
        if($b['status']==='dibatalkan')return ['booking'=>$b,'refund_pending'=>true];
        if(trim($reason)==='' || in_array($b['status'],['berlangsung','selesai'],true))throw new AppException('Alasan wajib; perjalanan aktif/selesai hubungi admin.',409);
        $st=db()->prepare('SELECT t.* FROM trips t JOIN trip_bookings tb ON tb.trip_id=t.id WHERE tb.booking_id=?');$st->execute([$id]);$t=$st->fetch();
        if($t && $t['status']==='berlangsung')throw new AppException('Perjalanan sudah berlangsung.',409);
        db()->prepare("INSERT INTO cancellations (booking_id,cancelled_by,reason,stage) VALUES (?,?,?,?)")->execute([$id,$user['role'],$reason,$b['status']]);
        db()->prepare("UPDATE bookings SET status='dibatalkan',updated_at=? WHERE id=?")->execute([date('Y-m-d H:i:s'),$id]);
        if($t){
            db()->prepare('DELETE FROM trip_bookings WHERE booking_id=?')->execute([$id]);
            db()->prepare('UPDATE trips SET seats_booked=MAX(0,seats_booked-?) WHERE id=?')->execute([$b['pax_count'],$t['id']]);
            if((int)$t['seats_booked']===(int)$b['pax_count']){
                db()->prepare("UPDATE trips SET status='dibatalkan' WHERE id=?")->execute([$t['id']]);
                foreach(['vehicle_availability','driver_availability'] as $table)db()->prepare("UPDATE $table SET status='available' WHERE trip_id=?")->execute([$t['id']]);
            }
        }
        $st=db()->prepare("SELECT * FROM payments WHERE booking_id=? AND status='paid'");$st->execute([$id]);$refunds=[];
        foreach($st->fetchAll() as $p){
            $r=db()->prepare("SELECT COALESCE(SUM(amount),0) FROM refunds WHERE payment_id=? AND status IN ('pending','processed','completed')");$r->execute([$p['id']]);$remaining=(int)$p['amount']-(int)$r->fetchColumn();
            if($remaining>0){db()->prepare("INSERT INTO refunds (code,payment_id,booking_id,amount,method,reason,status) VALUES (?,?,?,?,'manual',?,'pending')")->execute(['RFD-'.bin2hex(random_bytes(8)),$p['id'],$id,$remaining,$reason]);$refunds[]=(int)db()->lastInsertId();}
        }
        db()->prepare("UPDATE invoices SET status='void' WHERE booking_id=? AND status IN ('draft','issued')")->execute([$id]);
        return ['booking'=>self::find($id),'refund_ids'=>$refunds,'refund_pending'=>(bool)$refunds,'note'=>'Pembatalan bukan transfer uang; refund diproses operator secara manual.'];
    }

    public static function location(int $id,array $user): array
    {
        $b=self::findOwned($id,$user);
        $st=db()->prepare('SELECT l.lat,l.lng,l.accuracy,l.recorded_at FROM driver_locations l JOIN trip_bookings tb ON tb.trip_id=l.trip_id WHERE tb.booking_id=? ORDER BY l.id DESC LIMIT 1');$st->execute([$id]);$loc=$st->fetch();
        return ['location'=>$loc?:null,'stale'=>$loc ? strtotime($loc['recorded_at'])<time()-120 : true];
    }

    /** Ambil booking by id. */
    public static function find(int $id): ?array
    {
        $st = db()->prepare(
            "SELECT b.*, i.code AS invoice_code, i.status AS invoice_status, i.total AS invoice_total
             FROM bookings b LEFT JOIN invoices i ON i.booking_id = b.id
             WHERE b.id = ?"
        );
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    /** Ambil booking dengan cek kepemilikan (atau admin). */
    public static function findOwned(int $id, array $user): array
    {
        $b = self::find($id);
        if (!$b) throw new AppException('Pesanan tidak ditemukan.', 404);
        if ($user['role'] !== 'admin' && (int)$b['passenger_user_id'] !== (int)$user['id']) {
            throw new AppException('Akses ditolak.', 403);
        }
        if ($user['role'] === 'admin') Scope::assertRoute($user, (int)$b['route_id']);
        return $b;
    }

    /** Daftar pesanan milik penumpang. */
    public static function mine(int $userId): array
    {
        $st = db()->prepare(
            "SELECT b.*, i.code AS invoice_code, i.total AS invoice_total
             FROM bookings b LEFT JOIN invoices i ON i.booking_id = b.id
             WHERE b.passenger_user_id = ?
             ORDER BY b.created_at DESC"
        );
        $st->execute([$userId]);
        return $st->fetchAll();
    }

    private static function findInvoice(int $id): ?array
    {
        $st = db()->prepare("SELECT * FROM invoices WHERE id = ?");
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    /** Validasi mobil terpilih (bila diisi): aktif & kelas sesuai paket. */
    private static function assertVehicle(?int $vehicleId, string $package): void
    {
        if ($vehicleId === null) return;
        $st = db()->prepare("SELECT serves_package, status FROM vehicles WHERE id = ?");
        $st->execute([$vehicleId]);
        $v = $st->fetch();
        if (!$v) throw new AppException('Mobil terpilih tidak ditemukan.', 404);
        if ($v['status'] !== 'aktif') throw new AppException('Mobil terpilih tidak aktif.');
        if ($v['serves_package'] !== $package) {
            throw new AppException('Mobil tersebut tidak melayani paket ' . $package . '.');
        }
    }

    /** Validasi supir terpilih (Private): pengemudi disetujui. */
    private static function assertDriver(?int $driverId): void
    {
        if ($driverId === null) return;
        $st = db()->prepare(
            "SELECT u.role, u.status AS account_status, d.status FROM users u LEFT JOIN drivers d ON d.user_id = u.id WHERE u.id = ?"
        );
        $st->execute([$driverId]);
        $d = $st->fetch();
        if (!$d || $d['role'] !== 'pengemudi') throw new AppException('Supir terpilih tidak valid.', 404);
        if ($d['account_status'] !== 'aktif' || !in_array($d['status'], ['disetujui','peringatan'], true)) throw new AppException('Supir terpilih belum diverifikasi.');
    }

    /** Kode unik PREFIX + 6 digit untuk tabel/kolom tertentu. */
    private static function uniqueCode(string $prefix, string $table, string $col = 'code'): string
    {
        for ($i = 0; $i < 25; $i++) {
            $code = $prefix . str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            // $table/$col adalah konstanta internal (bukan input user) -> aman di-interpolasi
            $st = db()->prepare("SELECT 1 FROM {$table} WHERE {$col} = ?");
            $st->execute([$code]);
            if (!$st->fetch()) return $code;
        }
        throw new AppException('Gagal membuat kode pesanan, coba lagi.', 500);
    }

    private static function bool($v): bool
    {
        return in_array($v, [1, '1', true, 'true', 'ya', 'on'], true);
    }

    private static function num($v): ?float
    {
        if ($v === null || $v === '' || !is_numeric($v)) return null;
        return (float)$v;
    }
}
