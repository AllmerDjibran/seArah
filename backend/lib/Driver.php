<?php
/**
 * SEARAH — Modul Driver ("terima jadi"):
 *  - myTrips / respond (terima|tolak + penalti bertingkat)
 *  - updateStatus (berangkat/tiba/selesai -> trip_events, auto-relokasi saat selesai)
 *  - sendLocation (GPS -> driver_locations)
 *  - relocation: request (manual) / verify (auto) / admin decide
 */
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/Response.php';
require_once __DIR__ . '/Settings.php';
require_once __DIR__ . '/Scope.php';

final class Driver
{
    private const EVENTS = ['berangkat', 'tiba_jemput', 'berlangsung', 'tiba_drop', 'selesai', 'dibatalkan'];

    /** Trip yang ditugaskan ke supir ini (belum selesai/dibatalkan) + status respons. */
    public static function myTrips(array $driver): array
    {
        $st = db()->prepare(
            "SELECT t.*, r.response AS my_response,
                    (SELECT COUNT(*) FROM trip_bookings tb WHERE tb.trip_id = t.id) AS pax_total
             FROM trips t
             LEFT JOIN driver_order_responses r ON r.trip_id = t.id
             WHERE t.driver_id = ? AND t.status IN ('dikonfirmasi','berlangsung')
             ORDER BY t.trip_date, t.departure_time"
        );
        $st->execute([$driver['id']]);
        return $st->fetchAll();
    }

    /** Terima / tolak penugasan trip. */
    public static function respond(int $tripId, array $driver, string $response, string $reason = ''): array
    {
        $response = strtolower(trim($response));
        if (!in_array($response, ['terima', 'tolak'], true)) throw new AppException('Respons harus terima/tolak.');
        $trip = self::assignedTrip($tripId, $driver);
        if ($trip['status'] !== 'dikonfirmasi') throw new AppException('Trip tidak lagi menunggu respons.', 422);

        $now = date('Y-m-d H:i:s');
        $pdo = db();
        $ex = $pdo->prepare("SELECT id FROM driver_order_responses WHERE trip_id = ?");
        $ex->execute([$tripId]);
        if ($id = $ex->fetchColumn()) {
            $pdo->prepare("UPDATE driver_order_responses SET response=?, reason=?, responded_at=? WHERE id=?")
                ->execute([$response, $reason ?: null, $now, $id]);
        } else {
            $pdo->prepare("INSERT INTO driver_order_responses (trip_id, driver_user_id, response, reason, responded_at) VALUES (?,?,?,?,?)")
                ->execute([$tripId, $driver['id'], $response, $reason ?: null, $now]);
        }

        self::event($tripId, $response === 'terima' ? 'diterima' : 'ditolak', (int)$driver['id']);

        if ($response === 'tolak') {
            // lepas penugasan -> admin re-assign
            foreach(['driver_availability','vehicle_availability'] as $table)$pdo->prepare("UPDATE $table SET status='available' WHERE trip_id=?")->execute([$tripId]);
            $pdo->prepare("UPDATE trips SET driver_id = NULL, status='draft', updated_at=? WHERE id=?")->execute([$now, $tripId]);
            $penalty = self::applyRejectPenalty((int)$driver['id']);
            return ['trip_id' => $tripId, 'response' => 'tolak', 'penalty' => $penalty];
        }
        return ['trip_id' => $tripId, 'response' => 'terima'];
    }

    /** Update status perjalanan. */
    public static function updateStatus(int $tripId, array $driver, string $event, $lat = null, $lng = null): array
    {
        $event = strtolower(trim($event));
        if (!in_array($event, self::EVENTS, true)) throw new AppException('Event status tidak valid.');
        $trip = self::assignedTrip($tripId, $driver);

        if (in_array($trip['status'], ['selesai','dibatalkan','draft'], true)) throw new AppException('Transisi status ditolak.', 409);
        if ($event === 'selesai' && $trip['status'] !== 'berlangsung') throw new AppException('Trip belum berlangsung.', 409);
        $accepted = db()->prepare("SELECT 1 FROM driver_order_responses WHERE trip_id=? AND driver_user_id=? AND response='terima'");
        $accepted->execute([$tripId,$driver['id']]);
        if (!$accepted->fetchColumn()) throw new AppException('Terima penugasan terlebih dahulu.', 409);
        $map = [
            'berangkat' => 'berlangsung', 'berlangsung' => 'berlangsung',
            'tiba_jemput' => 'berlangsung', 'tiba_drop' => 'berlangsung',
            'selesai' => 'selesai', 'dibatalkan' => 'dibatalkan',
        ];
        $newStatus = $map[$event];
        $now = date('Y-m-d H:i:s');
        $pdo = db();
        $pdo->prepare("INSERT INTO trip_events (trip_id, event, actor_user_id, lat, lng, created_at) VALUES (?,?,?,?,?,?)")
            ->execute([$tripId, $event, (int)$driver['id'], self::num($lat), self::num($lng), $now]);
        $pdo->prepare("UPDATE trips SET status=?, updated_at=? WHERE id=?")->execute([$newStatus, $now, $tripId]);

        $pdo->prepare('UPDATE bookings SET status=?, updated_at=? WHERE id IN (SELECT booking_id FROM trip_bookings WHERE trip_id=?)')->execute([$newStatus,$now,$tripId]);
        if (in_array($newStatus, ['selesai','dibatalkan'], true)) {
            foreach (['driver_availability','vehicle_availability'] as $table) $pdo->prepare("UPDATE $table SET status='available',updated_at=? WHERE trip_id=?")->execute([$now,$tripId]);
        }
        $relocation = null;
        if ($event === 'selesai') {
            $pdo->prepare("UPDATE drivers SET trips_count = trips_count + 1, updated_at=? WHERE user_id=?")->execute([$now, $driver['id']]);
            $relocation = self::autoRelocate($trip, $driver);
        }
        return ['trip_id' => $tripId, 'status' => $newStatus, 'relocation_request' => $relocation];
    }

    /** Kirim titik GPS. */
    public static function sendLocation(int $tripId, array $driver, $lat, $lng, $accuracy = null): array
    {
        $trip = self::assignedTrip($tripId, $driver);
        if (!is_numeric($lat) || !is_numeric($lng) || !is_finite((float)$lat) || !is_finite((float)$lng) || abs((float)$lat)>90 || abs((float)$lng)>180 || ($accuracy !== null && (!is_numeric($accuracy) || (float)$accuracy<0))) throw new AppException('GPS tidak valid.', 422);
        if (!in_array($trip['status'], ['dikonfirmasi','berlangsung'], true)) throw new AppException('Trip tidak aktif.', 409);
        db()->prepare("INSERT INTO driver_locations (trip_id, driver_user_id, lat, lng, accuracy, recorded_at) VALUES (?,?,?,?,?,?)")
            ->execute([$tripId, (int)$driver['id'], (float)$lat, (float)$lng, self::num($accuracy), date('Y-m-d H:i:s')]);
        return ['ok' => true, 'recorded_at' => date('Y-m-d H:i:s')];
    }

    /** Supir mengajukan relokasi manual. */
    public static function relocationRequest(array $driver, array $in): array
    {
        $garageId = isset($in['requested_garage_id']) ? (int)$in['requested_garage_id'] ?: null : null;
        $vm = in_array($in['verify_method'] ?? '', ['confirm', 'gps_scan', 'manual_map'], true) ? $in['verify_method'] : 'confirm';
        $pdo = db();
        $cur = $pdo->prepare("SELECT current_garage_id FROM drivers WHERE user_id=?");
        $cur->execute([$driver['id']]);
        $from = $cur->fetchColumn() ?: null;

        $pdo->prepare(
            "INSERT INTO driver_availability_requests
             (driver_user_id, origin, type, from_garage_id, requested_garage_id, work_date, start_time, end_time,
              verify_method, reported_lat, reported_lng, driver_confirmed, note, status, created_at)
             VALUES (?, 'driver_manual', 'relocate', ?,?,?,?,?, ?,?,?, 1, ?, 'await_admin', ?)"
        )->execute([
            $driver['id'], $from, $garageId,
            $in['work_date'] ?? date('Y-m-d'), $in['start_time'] ?? null, $in['end_time'] ?? null,
            $vm, self::num($in['lat'] ?? null), self::num($in['lng'] ?? null),
            $in['note'] ?? null, date('Y-m-d H:i:s'),
        ]);
        return self::findRequest((int)$pdo->lastInsertId());
    }

    public static function myRelocations(array $driver): array
    {
        $st = db()->prepare(
            "SELECT r.*, g.name AS requested_garage FROM driver_availability_requests r
             LEFT JOIN garages g ON g.id = r.requested_garage_id
             WHERE r.driver_user_id = ? ORDER BY r.created_at DESC"
        );
        $st->execute([$driver['id']]);
        return $st->fetchAll();
    }

    /** Supir memverifikasi usulan relokasi otomatis (benar / koreksi GPS / peta). */
    public static function verifyRelocation(int $reqId, array $driver, array $in): array
    {
        $st = db()->prepare("SELECT * FROM driver_availability_requests WHERE id = ? AND driver_user_id = ?");
        $st->execute([$reqId, $driver['id']]);
        $r = $st->fetch();
        if (!$r) throw new AppException('Permintaan tidak ditemukan.', 404);
        if ($r['status'] !== 'await_driver_verify') throw new AppException('Permintaan tidak menunggu verifikasi supir.', 409);

        $action = $in['action'] ?? 'confirm';
        $now = date('Y-m-d H:i:s');
        $pdo = db();
        if ($action === 'confirm') {
            $pdo->prepare("UPDATE driver_availability_requests SET verify_method='confirm', driver_confirmed=1, requested_garage_id=COALESCE(requested_garage_id, proposed_garage_id), verified_at=?, status='await_admin' WHERE id=?")
                ->execute([$now, $reqId]);
        } elseif (in_array($action, ['gps_scan', 'manual_map'], true)) {
            $garageId = isset($in['requested_garage_id']) ? (int)$in['requested_garage_id'] ?: null : null;
            $pdo->prepare("UPDATE driver_availability_requests SET verify_method=?, reported_lat=?, reported_lng=?, requested_garage_id=?, driver_confirmed=1, verified_at=?, status='await_admin' WHERE id=?")
                ->execute([$action, self::num($in['lat'] ?? null), self::num($in['lng'] ?? null), $garageId, $now, $reqId]);
        } else {
            throw new AppException('action harus confirm/gps_scan/manual_map.');
        }
        return self::findRequest($reqId);
    }

    /** Admin: daftar relokasi menunggu (scope wilayah). */
    public static function listPendingRelocations(array $admin): array
    {
        $regions = Scope::regionIds($admin);
        $sql = "SELECT r.*, u.name AS driver_name, g.name AS requested_garage, c.region_id
                FROM driver_availability_requests r
                JOIN users u ON u.id = r.driver_user_id
                LEFT JOIN garages g ON g.id = COALESCE(r.requested_garage_id, r.proposed_garage_id)
                LEFT JOIN cities c ON c.id = g.city_id
                WHERE r.status = 'await_admin'";
        $params = [];
        if ($regions) { $sql .= " AND c.region_id IN (" . implode(',', array_fill(0, count($regions), '?')) . ")"; $params = $regions; }
        $sql .= " ORDER BY r.created_at";
        $st = db()->prepare($sql); $st->execute($params);
        return $st->fetchAll();
    }

    /** Admin: setujui/tolak relokasi -> pindahkan basis supir. */
    public static function decideRelocation(int $reqId, array $admin, bool $approve, string $note = ''): array
    {
        $st = db()->prepare("SELECT * FROM driver_availability_requests WHERE id = ?");
        $st->execute([$reqId]);
        $r = $st->fetch();
        if (!$r) throw new AppException('Permintaan tidak ditemukan.', 404);
        if ($r['status'] !== 'await_admin') throw new AppException('Permintaan tidak menunggu admin.', 409);

        $garageId = (int)($r['requested_garage_id'] ?: $r['proposed_garage_id']);
        $scope = db()->prepare('SELECT c.region_id FROM garages g JOIN cities c ON c.id=g.city_id WHERE g.id=? AND g.is_active=1');
        $scope->execute([$garageId]); $region = $scope->fetchColumn();
        if (!$region) throw new AppException('Garasi aktif wajib.', 422);
        Scope::assertRegion($admin, (int)$region);
        $now = date('Y-m-d H:i:s');
        $pdo = db();
        if ($approve) {
            $garageId = $r['requested_garage_id'] ?: $r['proposed_garage_id'];
            $regionId = null;
            if ($garageId) {
                $rg = $pdo->prepare("SELECT c.region_id FROM garages g JOIN cities c ON c.id=g.city_id WHERE g.id=?");
                $rg->execute([$garageId]);
                $regionId = $rg->fetchColumn() ?: null;
            }
            $pdo->prepare("UPDATE drivers SET current_garage_id=?, current_region_id=?, location_updated_at=?, updated_at=? WHERE user_id=?")
                ->execute([$garageId, $regionId, $now, $now, $r['driver_user_id']]);
            $pdo->prepare("UPDATE driver_availability_requests SET status='approved', reviewed_by=?, reviewed_at=?, review_note=? WHERE id=?")
                ->execute([$admin['id'], $now, $note ?: null, $reqId]);
        } else {
            $pdo->prepare("UPDATE driver_availability_requests SET status='rejected', reviewed_by=?, reviewed_at=?, review_note=? WHERE id=?")
                ->execute([$admin['id'], $now, $note ?: null, $reqId]);
        }
        return self::findRequest($reqId);
    }

    public static function upload(array $user,array $in): array
    {
        $kind=$in['jenis']??'';if(!in_array($kind,['KTP','SIM','STNK','KIR'],true))throw new AppException('Jenis invalid.',422);
        $base=getenv('SEARAH_DOCUMENT_DIR')?:'';$real=realpath($base);$root=realpath(__DIR__.'/../..');
        if(!$real||!is_dir($real)||str_starts_with(strtolower($real),strtolower($root)))throw new AppException('Storage dokumen di luar webroot wajib dikonfigurasi.',503);
        $raw=base64_decode((string)($in['content_base64']??''),true);
        if($raw===false||strlen($raw)>600000||strlen($raw)<10)throw new AppException('Gambar maksimal 600KB.',422);
        $info=@getimagesizefromstring($raw);
        if(!$info||!in_array($info['mime'],['image/jpeg','image/png'],true)||$info[0]*$info[1]>12000000)throw new AppException('JPEG/PNG valid wajib.',422);
        $img=@imagecreatefromstring($raw);if(!$img)throw new AppException('Gambar tidak dapat dibaca.',422);
        $path=$real.DIRECTORY_SEPARATOR.bin2hex(random_bytes(24)).'.jpg';
        if(!imagejpeg($img,$path,90))throw new AppException('Storage gagal.',503);imagedestroy($img);
        $st=db()->prepare('SELECT COALESCE(MAX(version),0)+1 FROM driver_documents WHERE driver_user_id=? AND jenis=?');$st->execute([$user['id'],$kind]);$version=$st->fetchColumn();
        db()->prepare("UPDATE driver_documents SET status='ditolak' WHERE driver_user_id=? AND jenis=?")->execute([$user['id'],$kind]);
        db()->prepare('INSERT INTO driver_documents (driver_user_id,jenis,file_path,file_name,version) VALUES (?,?,?,?,?)')->execute([$user['id'],$kind,$path,$kind.'.jpg',$version]);
        db()->prepare("UPDATE drivers SET status='menunggu' WHERE user_id=?")->execute([$user['id']]);
        return ['id'=>(int)db()->lastInsertId(),'jenis'=>$kind,'version'=>$version,'status'=>'menunggu'];
    }

    public static function document(int $id,array $user): array
    {
        $st=db()->prepare('SELECT id,driver_user_id,jenis,file_path,status,version FROM driver_documents WHERE id=?');$st->execute([$id]);$doc=$st->fetch();if(!$doc)throw new AppException('Dokumen tidak ada.',404);
        if($user['role']==='admin'){
            $st=db()->prepare('SELECT region_id FROM drivers WHERE user_id=?');$st->execute([$doc['driver_user_id']]);$region=$st->fetchColumn();Scope::assertRegion($user,$region?(int)$region:null);
        }elseif((int)$user['id']!==(int)$doc['driver_user_id'])throw new AppException('Akses ditolak.',403);
        $base=realpath(getenv('SEARAH_DOCUMENT_DIR')?:'');$path=realpath($doc['file_path']);
        if(!$base||!$path||!str_starts_with(strtolower($path),strtolower($base.DIRECTORY_SEPARATOR)))throw new AppException('Storage tidak valid.',503);
        $raw=file_get_contents($path);unset($doc['file_path']);$doc['content_base64']=base64_encode($raw);$doc['mime']='image/jpeg';return $doc;
    }

    // ---------------- helpers ----------------

    private static function assignedTrip(int $tripId, array $driver): array
    {
        $st = db()->prepare("SELECT * FROM trips WHERE id = ?");
        $st->execute([$tripId]);
        $t = $st->fetch();
        if (!$t) throw new AppException('Trip tidak ditemukan.', 404);
        if ((int)$t['driver_id'] !== (int)$driver['id']) throw new AppException('Trip bukan untuk supir ini.', 403);
        return $t;
    }

    private static function event(int $tripId, string $event, int $actorId): void
    {
        db()->prepare("INSERT INTO trip_events (trip_id, event, actor_user_id, created_at) VALUES (?,?,?,?)")
            ->execute([$tripId, $event, $actorId, date('Y-m-d H:i:s')]);
    }

    /** Auto usulan relokasi ke garasi tujuan saat trip selesai. */
    private static function autoRelocate(array $trip, array $driver): ?array
    {
        if (Settings::get('relocation_auto_on_trip_complete', '1') !== '1') return null;
        if (!$trip['dest_garage_id']) return null;
        $pdo = db();
        $cur = $pdo->prepare("SELECT current_garage_id FROM drivers WHERE user_id=?");
        $cur->execute([$driver['id']]);
        $from = $cur->fetchColumn() ?: null;
        $pdo->prepare(
            "INSERT INTO driver_availability_requests
             (driver_user_id, origin, source_trip_id, type, from_garage_id, proposed_garage_id, requested_garage_id,
              work_date, status, created_at)
             VALUES (?, 'auto_trip', ?, 'relocate', ?, ?, ?, ?, 'await_driver_verify', ?)"
        )->execute([
            $driver['id'], (int)$trip['id'], $from, (int)$trip['dest_garage_id'], (int)$trip['dest_garage_id'],
            $trip['trip_date'], date('Y-m-d H:i:s'),
        ]);
        return self::findRequest((int)$pdo->lastInsertId());
    }

    /** Penalti bertingkat utk penolakan: peringatan -> SP/suspend. Return info penalti. */
    private static function applyRejectPenalty(int $driverUserId): ?array
    {
        $now = date('Y-m-d H:i:s');
        $pdo = db();
        $pdo->prepare("UPDATE drivers SET reject_count = reject_count + 1, updated_at=? WHERE user_id=?")->execute([$now, $driverUserId]);
        $st = $pdo->prepare("SELECT reject_count FROM drivers WHERE user_id=?");
        $st->execute([$driverUserId]);
        $rc = (int)$st->fetchColumn();

        $warn = Settings::int('reject_warn_threshold', 3);
        $pen = Settings::int('reject_penalty_threshold', 5);

        $level = null;
        if ($rc >= $pen) {
            $sp = min(3, (int)floor($rc / $pen));
            $level = 'sp' . $sp;
            $status = ($rc >= 2 * $pen) ? 'suspen' : 'peringatan';
            $pdo->prepare("UPDATE drivers SET status=?, updated_at=? WHERE user_id=?")->execute([$status, $now, $driverUserId]);
            if ($status === 'suspen') {
                $pdo->prepare("UPDATE users SET status='suspend', ban_reason=?, updated_at=? WHERE id=?")->execute(["Akun dibekukan (akumulasi tolak order)", $now, $driverUserId]);
            }
        } elseif ($rc >= $warn) {
            $level = 'peringatan';
        }
        if (!$level) return ['reject_count' => $rc, 'penalty' => null];

        $pdo->prepare("INSERT INTO driver_penalties (driver_user_id, level, reason, reject_count_at, issued_by, created_at) VALUES (?,?,?,?,?,?)")
            ->execute([$driverUserId, $level, "Akumulasi menolak order", $rc, null, $now]);
        return ['reject_count' => $rc, 'penalty' => $level];
    }

    private static function findRequest(int $id): ?array
    {
        $st = db()->prepare("SELECT * FROM driver_availability_requests WHERE id = ?");
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    private static function num($v)
    {
        return ($v === null || $v === '' || !is_numeric($v)) ? null : (float)$v;
    }
}
