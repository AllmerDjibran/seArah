<?php
/**
 * SEARAH — Modul Admin Master.
 * Garasi (CRUD), Mobil (CRUD + monitoring pindah garasi), Verifikasi KTP/SIM driver,
 * Akun (penumpang/pengemudi) + ban/suspend, Laporan dua arah, dan review pengajuan unban.
 * Semua operasi dihormati scope wilayah admin (koordinator vs super admin).
 */
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/Response.php';
require_once __DIR__ . '/Scope.php';

final class Admin
{
    // ============================ GARASI ============================
    public static function listGarages(array $admin): array
    {
        $regions = Scope::regionIds($admin);
        $sql = "SELECT g.*, c.name AS city, c.region_id, r.code AS region
                FROM garages g JOIN cities c ON c.id = g.city_id
                LEFT JOIN regions r ON r.id = c.region_id WHERE 1=1";
        $p = [];
        if ($regions) { $sql .= " AND c.region_id IN (" . implode(',', array_fill(0, count($regions), '?')) . ")"; $p = $regions; }
        $sql .= " ORDER BY c.name, g.name";
        $st = db()->prepare($sql); $st->execute($p); return $st->fetchAll();
    }

    public static function createGarage(array $admin, array $in): array
    {
        $name = trim($in['name'] ?? ''); $cityId = (int)($in['city_id'] ?? 0);
        if ($name === '' || !$cityId || trim((string)($in['address'] ?? '')) === '' || !is_numeric($in['lat'] ?? null) || !is_numeric($in['lng'] ?? null) || abs((float)$in['lat']) > 90 || abs((float)$in['lng']) > 180) throw new AppException('Nama, kota, alamat dan koordinat valid wajib.', 422);
        $regionId = self::cityRegion($cityId);
        self::assertRegion($admin, $regionId);
        db()->prepare("INSERT INTO garages (name, city_id, address, lat, lng, phone, operating_hours, is_active, created_at)
                       VALUES (?,?,?,?,?,?,?,1,?)")
            ->execute([$name, $cityId, $in['address'] ?? null, self::num($in['lat'] ?? null), self::num($in['lng'] ?? null),
                       $in['phone'] ?? null, $in['operating_hours'] ?? null, date('Y-m-d H:i:s')]);
        return self::findGarage((int)db()->lastInsertId());
    }

    public static function updateGarage(array $admin, int $id, array $in): array
    {
        $g = self::findGarage($id); if (!$g) throw new AppException('Garasi tidak ditemukan.', 404);
        self::assertRegion($admin, (int)$g['region_id']);
        self::assertRegion($admin, self::cityRegion((int)($in['city_id'] ?? $g['city_id'])));
        $merged = array_replace($g, $in);
        if (trim((string)$merged['name']) === '' || trim((string)$merged['address']) === '' || !is_numeric($merged['lat']) || !is_numeric($merged['lng']) || abs((float)$merged['lat']) > 90 || abs((float)$merged['lng']) > 180) throw new AppException('Data garasi tidak valid.', 422);
        db()->prepare("UPDATE garages SET name=?, city_id=?, address=?, lat=?, lng=?, phone=?, operating_hours=?, updated_at=? WHERE id=?")
            ->execute([$in['name'] ?? $g['name'], (int)($in['city_id'] ?? $g['city_id']), $in['address'] ?? $g['address'],
                       self::num($in['lat'] ?? $g['lat']), self::num($in['lng'] ?? $g['lng']), $in['phone'] ?? $g['phone'],
                       $in['operating_hours'] ?? $g['operating_hours'], date('Y-m-d H:i:s'), $id]);
        return self::findGarage($id);
    }

    public static function disableGarage(array $admin, int $id): array
    {
        $g = self::findGarage($id); if (!$g) throw new AppException('Garasi tidak ditemukan.', 404);
        self::assertRegion($admin, (int)$g['region_id']);
        db()->prepare("UPDATE garages SET is_active=0, updated_at=? WHERE id=?")->execute([date('Y-m-d H:i:s'), $id]);
        return self::findGarage($id);
    }

    // ============================ MOBIL ============================
    public static function listVehicles(array $admin, array $f = []): array
    {
        $regions = Scope::regionIds($admin);
        $sql = "SELECT v.*, p.name AS partner, g.name AS garage, c.region_id
                FROM vehicles v
                LEFT JOIN partners p ON p.id = v.partner_id
                LEFT JOIN garages g ON g.id = v.current_garage_id
                LEFT JOIN cities c ON c.id = g.city_id WHERE 1=1";
        $p = [];
        if (!empty($f['package'])) { $sql .= " AND v.serves_package = ?"; $p[] = $f['package']; }
        if (!empty($f['status']))  { $sql .= " AND v.status = ?"; $p[] = $f['status']; }
        if ($regions) { $sql .= " AND c.region_id IN (" . implode(',', array_fill(0, count($regions), '?')) . ")"; $p = array_merge($p, $regions); }
        $sql .= " ORDER BY v.id DESC";
        $st = db()->prepare($sql); $st->execute($p); return $st->fetchAll();
    }

    public static function createVehicle(array $admin, array $in): array
    {
        $model = trim($in['model'] ?? ''); $plate = strtoupper(trim($in['plate'] ?? ''));
        $pkg = $in['serves_package'] ?? ''; $seats = (int)($in['seat_capacity'] ?? 0);
        if ($model === '' || $plate === '' || !in_array($pkg, ['reguler','door_to_door','private'], true) || $seats <= 0)
            throw new AppException('model, plate, serves_package (reguler/door_to_door/private), seat_capacity wajib.', 422);
        $exists = db()->prepare("SELECT 1 FROM vehicles WHERE plate = ?");
        $exists->execute([$plate]);
        if ($exists->fetchColumn()) throw new AppException('Pelat sudah terdaftar.', 409);
        $garageId = (int)($in['current_garage_id'] ?? 0);
        $garage = self::findGarage($garageId);
        if (!$garage || !$garage['is_active']) throw new AppException('Garasi aktif wajib.', 422);
        self::assertRegion($admin, (int)$garage['region_id']);
        db()->prepare("INSERT INTO vehicles (partner_id, model, serves_package, plate, seat_capacity, year, current_garage_id, status, created_at)
                       VALUES (?,?,?,?,?,?,?, 'aktif', ?)")
            ->execute([(int)($in['partner_id'] ?? 0) ?: null, $model, $pkg, $plate, $seats, (int)($in['year'] ?? 0) ?: null, $garageId, date('Y-m-d H:i:s')]);
        return self::findVehicle((int)db()->lastInsertId());
    }

    public static function updateVehicle(array $admin, int $id, array $in): array
    {
        $v = self::findVehicle($id); if (!$v) throw new AppException('Mobil tidak ditemukan.', 404);
        $garage = self::findGarage((int)$v['current_garage_id']);
        self::assertRegion($admin, $garage ? (int)$garage['region_id'] : null);
        $merged = array_replace($v,$in);
        if (trim((string)$merged['model']) === '' || trim((string)$merged['plate']) === '' || (int)$merged['seat_capacity'] < 1 || !in_array($merged['serves_package'], ['reguler','door_to_door','private'], true) || !in_array($merged['status'], ['aktif','suspended','maintenance','non_aktif'], true)) throw new AppException('Data mobil tidak valid.', 422);
        db()->prepare("UPDATE vehicles SET partner_id=?, model=?, serves_package=?, plate=?, seat_capacity=?, year=?, status=?, updated_at=? WHERE id=?")
            ->execute([(int)($in['partner_id'] ?? $v['partner_id'] ?? 0) ?: null, $in['model'] ?? $v['model'],
                       $in['serves_package'] ?? $v['serves_package'], strtoupper($in['plate'] ?? $v['plate']),
                       (int)($in['seat_capacity'] ?? $v['seat_capacity']), (int)($in['year'] ?? $v['year'] ?? 0) ?: null,
                       $in['status'] ?? $v['status'], date('Y-m-d H:i:s'), $id]);
        return self::findVehicle($id);
    }

    public static function disableVehicle(array $admin, int $id): array
    {
        $v = self::findVehicle($id); if (!$v) throw new AppException('Mobil tidak ditemukan.', 404);
        $garage = self::findGarage((int)$v['current_garage_id']);
        self::assertRegion($admin, $garage ? (int)$garage['region_id'] : null);
        db()->prepare("UPDATE vehicles SET status='non_aktif', updated_at=? WHERE id=?")->execute([date('Y-m-d H:i:s'), $id]);
        return self::findVehicle($id);
    }

    /** Monitoring: pindahkan mobil antar garasi (catat riwayat). */
    public static function moveVehicle(array $admin, int $id, int $toGarageId, string $note = ''): array
    {
        $v = self::findVehicle($id); if (!$v) throw new AppException('Mobil tidak ditemukan.', 404);
        $garage = self::findGarage((int)$v['current_garage_id']);
        self::assertRegion($admin, $garage ? (int)$garage['region_id'] : null);
        $to = self::findGarage($toGarageId); if (!$to) throw new AppException('Garasi tujuan tidak ada.', 404);
        self::assertRegion($admin, (int)$to['region_id']);
        $now = date('Y-m-d H:i:s');
        db()->prepare("UPDATE vehicles SET current_garage_id=?, updated_at=? WHERE id=?")->execute([$toGarageId, $now, $id]);
        db()->prepare("INSERT INTO vehicle_movements (vehicle_id, from_garage_id, to_garage_id, note, moved_at, moved_by) VALUES (?,?,?,?,?,?)")
            ->execute([$id, $v['current_garage_id'] ?: null, $toGarageId, $note ?: null, $now, $admin['id']]);
        return self::findVehicle($id);
    }

    // ==================== VERIFIKASI KTP/SIM DRIVER ====================
    public static function listPendingDrivers(array $admin): array
    {
        $regions = Scope::regionIds($admin);
        $sql = "SELECT u.id, u.name, u.email, u.phone, d.status, d.region_id, d.home_garage_id
                FROM drivers d JOIN users u ON u.id = d.user_id WHERE d.status = 'menunggu'";
        $p = [];
        if ($regions) { $sql .= " AND d.region_id IN (" . implode(',', array_fill(0, count($regions), '?')) . ")"; $p = $regions; }
        $st = db()->prepare($sql); $st->execute($p);
        $rows = $st->fetchAll();
        $doc = db()->prepare("SELECT jenis, file_name, version, status, uploaded_at FROM driver_documents WHERE driver_user_id = ?");
        foreach ($rows as &$r) { $doc->execute([$r['id']]); $r['documents'] = $doc->fetchAll(); }
        return $rows;
    }

    public static function reviewVerification(array $admin, int $driverUserId, bool $approve, string $note = ''): array
    {
        $st = db()->prepare("SELECT d.*, u.role FROM drivers d JOIN users u ON u.id=d.user_id WHERE d.user_id=?");
        $st->execute([$driverUserId]); $d = $st->fetch();
        if (!$d || $d['role'] !== 'pengemudi') throw new AppException('Driver tidak ditemukan.', 404);
        self::assertRegion($admin, $d['region_id'] ? (int)$d['region_id'] : null);
        if ($approve) {
            $docs = db()->prepare("SELECT COUNT(DISTINCT jenis) FROM driver_documents WHERE driver_user_id=? AND jenis IN ('KTP','SIM') AND status IN ('menunggu','disetujui')");
            $docs->execute([$driverUserId]);
            if ((int)$docs->fetchColumn() !== 2) throw new AppException('KTP dan SIM wajib sebelum approval.', 422);
        }
        $now = date('Y-m-d H:i:s');
        $newStatus = $approve ? 'disetujui' : 'ditolak';
        db()->prepare("UPDATE drivers SET status=?, updated_at=? WHERE user_id=?")->execute([$newStatus, $now, $driverUserId]);
        db()->prepare("UPDATE driver_documents SET status=?, reviewed_by=?, reviewed_at=?, review_note=? WHERE driver_user_id=? AND status='menunggu'")
            ->execute([$newStatus, $admin['id'], $now, $note ?: null, $driverUserId]);
        if (!$approve && $note !== '') {
            db()->prepare("INSERT INTO verification_notes (driver_user_id, admin_user_id, note, created_at) VALUES (?,?,?,?)")
                ->execute([$driverUserId, $admin['id'], $note, $now]);
        }
        return ['driver_user_id' => $driverUserId, 'status' => $newStatus];
    }

    // ============================ AKUN ============================
    public static function listAccounts(array $admin, string $role): array
    {
        if (!in_array($role, ['admin','penumpang', 'pengemudi'], true)) throw new AppException('role harus penumpang/pengemudi.', 422);
        $sql = "SELECT u.id, u.name, u.email, u.phone, u.status, u.created_at,
                       d.status AS drv_status, d.rating, d.reject_count, d.current_garage_id, d.region_id
                FROM users u LEFT JOIN drivers d ON d.user_id = u.id WHERE u.role = ?";
        if (Scope::regionIds($admin) !== null) throw new AppException('Manajemen akun global memerlukan super admin eksplisit.', 403);
        $st = db()->prepare($sql); $st->execute([$role]);
        return $st->fetchAll();
    }

    /** Ban/suspend/aktifkan akun (penumpang & driver). */
    public static function setAccountStatus(array $admin, int $userId, string $status, string $reason = ''): array
    {
        if (Scope::regionIds($admin) !== null) throw new AppException('Manajemen akun global memerlukan super admin eksplisit.', 403);
        if ($userId === (int)$admin['id']) throw new AppException('Tidak dapat mengubah status sendiri.', 422);
        if (!in_array($status, ['aktif', 'suspend', 'ban', 'dinonaktifkan'], true)) throw new AppException('status tidak valid.', 422);
        $st = db()->prepare("SELECT * FROM users WHERE id=?"); $st->execute([$userId]); $u = $st->fetch();
        if (!$u) throw new AppException('Akun tidak ditemukan.', 404);
        if($u['role']==='admin' && $u['status']==='aktif' && $status!=='aktif'){
            $ids=array_filter(array_map('intval',explode(',',getenv('SEARAH_SUPER_ADMIN_IDS')?:'')));
            $st=db()->prepare("SELECT id FROM users WHERE role='admin' AND status='aktif' AND id<>?");$st->execute([$userId]);$remaining=$st->fetchAll(PDO::FETCH_COLUMN);
            if(!array_intersect($ids,$remaining))throw new AppException('Tidak menonaktifkan admin berwenang terakhir.',409);
        }
        if($status!=='aktif')db()->prepare('DELETE FROM auth_tokens WHERE user_id=?')->execute([$userId]);
        $now = date('Y-m-d H:i:s');
        db()->prepare("UPDATE users SET status=?, ban_reason=?, updated_at=? WHERE id=?")
            ->execute([$status, $status === 'aktif' ? null : ($reason ?: null), $now, $userId]);
        $action = $status === 'ban' ? 'ban' : ($status === 'suspend' ? 'suspend' : ($status === 'aktif' ? 'aktifkan_kembali' : 'suspend'));
        db()->prepare("INSERT INTO account_actions (target_user_id, action, reason, by_admin_id, created_at) VALUES (?,?,?,?,?)")
            ->execute([$userId, $action, $reason ?: null, $admin['id'], $now]);
        if ($u['role'] === 'pengemudi') {
            if (in_array($status, ['suspend', 'ban'], true)) db()->prepare("UPDATE drivers SET status='suspen' WHERE user_id=?")->execute([$userId]);
            elseif ($status === 'aktif') db()->prepare("UPDATE drivers SET status='disetujui' WHERE user_id=? AND status='suspen'")->execute([$userId]);
        }
        return ['user_id' => $userId, 'status' => $status];
    }

    // ============================ LAPORAN ============================
    public static function listReports(array $admin, ?string $type = null): array
    {
        $regions = Scope::regionIds($admin);
        $sql = "SELECT rp.*, ru.name AS reporter_name, su.name AS subject_name
                FROM reports rp LEFT JOIN users ru ON ru.id=rp.reporter_user_id LEFT JOIN users su ON su.id=rp.subject_user_id WHERE 1=1";
        $p = [];
        if ($type) { $sql .= " AND rp.type = ?"; $p[] = $type; }
        if ($regions) { $sql .= " AND rp.region_id IN (" . implode(',', array_fill(0, count($regions), '?')) . ")"; $p = array_merge($p, $regions); }
        $sql .= " ORDER BY rp.created_at DESC";
        $st = db()->prepare($sql); $st->execute($p); return $st->fetchAll();
    }

    public static function createReport(array $admin, array $in): array
    {
        $type = $in['type'] ?? '';
        if (!in_array($type, ['terhadap_driver', 'terhadap_penumpang'], true)) throw new AppException('type tidak valid.', 422);
        $subject = (int)($in['subject_user_id'] ?? 0) ?: null;
        $regionId = (int)($in['region_id'] ?? 0) ?: null;
        self::assertRegion($admin, $regionId);
        $code = self::uniqueCode('RPT-', 'reports');
        db()->prepare("INSERT INTO reports (code, type, reporter_user_id, subject_user_id, booking_id, region_id, category, description, status, created_at)
                       VALUES (?,?,?,?,?,?,?,?,'menunggu',?)")
            ->execute([$code, $type, (int)($in['reporter_user_id'] ?? 0) ?: null, $subject, (int)($in['booking_id'] ?? 0) ?: null,
                       $regionId, $in['category'] ?? null, $in['description'] ?? null, date('Y-m-d H:i:s')]);
        $st = db()->prepare("SELECT * FROM reports WHERE code=?"); $st->execute([$code]); return $st->fetch();
    }

    /** Update status laporan + tindakan admin; opsional ban subject. */
    public static function updateReport(array $admin, int $id, array $in): array
    {
        $st = db()->prepare("SELECT * FROM reports WHERE id=?"); $st->execute([$id]); $r = $st->fetch();
        if (!$r) throw new AppException('Laporan tidak ditemukan.', 404);
        self::assertRegion($admin, $r['region_id'] ? (int)$r['region_id'] : null);
        $status = $in['status'] ?? $r['status'];
        if (!in_array($status, ['menunggu', 'diproses', 'selesai'], true)) throw new AppException('status laporan tidak valid.', 422);
        db()->prepare("UPDATE reports SET status=?, admin_action=?, handled_by=?, handled_at=? WHERE id=?")
            ->execute([$status, $in['tindakan'] ?? $r['admin_action'], $admin['id'], date('Y-m-d H:i:s'), $id]);
        $ban = $in['subject_action'] ?? ''; // '', 'suspend', 'ban', 'aktifkan'
        if ($ban && $r['subject_user_id']) {
            $map = ['suspend' => 'suspend', 'ban' => 'ban', 'aktifkan' => 'aktif'];
            if (!isset($map[$ban])) throw new AppException('Tindakan tidak valid.', 422);
            self::setAccountStatus($admin, (int)$r['subject_user_id'], $map[$ban] ?? 'aktif', $in['tindakan'] ?? 'Tindakan laporan');
        }
        $q = db()->prepare("SELECT * FROM reports WHERE id=?"); $q->execute([$id]); return $q->fetch();
    }

    // ============================ UNBAN ============================
    public static function listUnbanRequests(array $admin): array
    {
        if (Scope::regionIds($admin) !== null) throw new AppException('Review akun global memerlukan super admin eksplisit.', 403);
        $st = db()->prepare("SELECT q.*, u.name, u.email, u.role FROM unban_requests q JOIN users u ON u.id=q.user_id WHERE q.status='menunggu' ORDER BY q.created_at");
        $st->execute(); return $st->fetchAll();
    }

    public static function reviewUnban(array $admin, int $id, bool $approve, string $note = ''): array
    {
        if (Scope::regionIds($admin) !== null) throw new AppException('Review akun global memerlukan super admin eksplisit.', 403);
        if ((new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->format('w') !== '0') throw new AppException('Review unban hanya hari Minggu Asia/Jakarta.', 409);
        $st = db()->prepare("SELECT * FROM unban_requests WHERE id=?"); $st->execute([$id]); $q = $st->fetch();
        if (!$q) throw new AppException('Pengajuan tidak ditemukan.', 404);
        if ($q['status'] !== 'menunggu') throw new AppException('Pengajuan sudah diproses.', 409);
        $now = date('Y-m-d H:i:s');
        if ($approve) self::setAccountStatus($admin, (int)$q['user_id'], 'aktif', 'Disetujui setelah banding');
        db()->prepare("UPDATE unban_requests SET status=?, reviewed_by=?, review_note=?, reviewed_at=? WHERE id=?")
            ->execute([$approve ? 'disetujui' : 'ditolak', $admin['id'], $note ?: null, $now, $id]);
        $q2 = db()->prepare("SELECT * FROM unban_requests WHERE id=?"); $q2->execute([$id]); return $q2->fetch();
    }

    public static function account(array $admin, array $in, ?int $id=null): array
    {
        if(Scope::regionIds($admin)!==null)throw new AppException('CRUD akun memerlukan super admin.',403);
        if($id===null){
            $role=$in['role']??'penumpang';
            if(!in_array($role,['admin','penumpang','pengemudi'],true))throw new AppException('Role invalid.',422);
            $input=$in;$input['role']=$role==='admin'?'penumpang':$role;
            $u=Auth::register($input);$id=(int)$u['id'];
            if($role==='admin')db()->prepare("UPDATE users SET role='admin' WHERE id=?")->execute([$id]);
        }else{
            $u=Auth::userById($id);
            if(isset($in['role']) && $in['role']!==$u['role'])throw new AppException('Role existing tidak dapat diubah.',422);
            $name=trim((string)($in['name']??$u['name']));$email=strtolower(trim((string)($in['email']??$u['email'])));
            if($name===''||!filter_var($email,FILTER_VALIDATE_EMAIL))throw new AppException('Nama/email invalid.',422);
            $st=db()->prepare('SELECT id FROM users WHERE lower(email)=? AND id<>?');$st->execute([$email,$id]);if($st->fetchColumn())throw new AppException('Email sudah terdaftar.',409);
            db()->prepare('UPDATE users SET name=?,email=?,phone=?,updated_at=? WHERE id=?')->execute([$name,$email,$in['phone']??$u['phone'],date('Y-m-d H:i:s'),$id]);
            if(isset($in['password'])){
                if(strlen($in['password'])<8||strlen($in['password'])>72)throw new AppException('Password 8-72 byte.',422);
                db()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($in['password'],PASSWORD_DEFAULT),$id]);
                db()->prepare('DELETE FROM auth_tokens WHERE user_id=?')->execute([$id]);
            }
        }
        if(isset($in['status']))self::setAccountStatus($admin,$id,$in['status'],$in['reason']??'CRUD account');
        if(isset($in['region_ids'])){
            $u=Auth::userById($id);if($u['role']!=='admin'||!is_array($in['region_ids']))throw new AppException('Scope hanya untuk admin.',422);
            if($id===(int)$admin['id'])throw new AppException('Tidak mengubah scope sendiri.',422);
            db()->prepare('DELETE FROM user_region_scopes WHERE user_id=?')->execute([$id]);
            foreach(array_unique($in['region_ids']) as $region){$st=db()->prepare('SELECT 1 FROM regions WHERE id=?');$st->execute([$region]);if(!$st->fetchColumn())throw new AppException('Region invalid.',422);db()->prepare('INSERT INTO user_region_scopes VALUES (?,?)')->execute([$id,$region]);}
        }
        if(isset($in['current_garage_id'])){
            $g=self::findGarage((int)$in['current_garage_id']);$u=Auth::userById($id);
            if(!$g||!$g['is_active']||$u['role']!=='pengemudi')throw new AppException('Garasi/driver invalid.',422);
            db()->prepare('UPDATE drivers SET current_garage_id=?,current_region_id=?,region_id=? WHERE user_id=?')->execute([$g['id'],$g['region_id'],$g['region_id'],$id]);
        }
        return Auth::userById($id);
    }

    public static function userReport(array $user,array $in): array
    {
        $id=(int)($in['booking_id']??0);$b=Booking::find($id);if(!$b)throw new AppException('Booking tidak ditemukan.',404);
        $st=db()->prepare('SELECT t.driver_id FROM trips t JOIN trip_bookings tb ON tb.trip_id=t.id WHERE tb.booking_id=?');$st->execute([$id]);$driver=(int)$st->fetchColumn();
        if($user['role']==='penumpang' && (int)$b['passenger_user_id']===(int)$user['id']){$type='terhadap_driver';$subject=$driver;}
        elseif($user['role']==='pengemudi' && $driver===(int)$user['id']){$type='terhadap_penumpang';$subject=(int)$b['passenger_user_id'];}
        else throw new AppException('Booking bukan milik/penugasan Anda.',403);
        if(!$subject||trim((string)($in['description']??''))==='')throw new AppException('Subject dan kronologi wajib.',422);
        $st=db()->prepare('SELECT c.region_id FROM routes r JOIN cities c ON c.id=r.origin_city_id WHERE r.id=?');$st->execute([$b['route_id']]);$region=$st->fetchColumn();
        $code='RPT-'.bin2hex(random_bytes(8));
        db()->prepare("INSERT INTO reports (code,type,reporter_user_id,subject_user_id,booking_id,region_id,category,description,status) VALUES (?,?,?,?,?,?,?,?,'menunggu')")->execute([$code,$type,$user['id'],$subject,$id,$region,$in['category']??null,substr($in['description'],0,5000)]);
        return ['id'=>(int)db()->lastInsertId(),'code'=>$code,'status'=>'menunggu'];
    }

    public static function requestUnban(array $in): array
    {
        $st=db()->prepare('SELECT * FROM users WHERE lower(email)=?');$st->execute([strtolower(trim((string)($in['email']??'')))]);$u=$st->fetch();
        if(!$u||!password_verify((string)($in['password']??''),$u['password_hash']))throw new AppException('Email/password salah.',401);
        if(!in_array($u['status'],['ban','suspend'],true))throw new AppException('Akun tidak dalam status ban/suspend.',409);
        $now=new DateTimeImmutable('now',new DateTimeZone('Asia/Jakarta'));
        $start=$now->setTime(0,0)->modify('-'.$now->format('w').' days');$end=$start->modify('+7 days');
        $st=db()->prepare('SELECT 1 FROM unban_requests WHERE user_id=? AND created_at>=? AND created_at<?');$st->execute([$u['id'],$start->format('Y-m-d H:i:s'),$end->format('Y-m-d H:i:s')]);if($st->fetchColumn())throw new AppException('Satu pengajuan per minggu dimulai Minggu Asia/Jakarta.',409);
        $reason=trim((string)($in['reason']??''));if($reason==='')throw new AppException('Alasan wajib.',422);
        db()->prepare('INSERT INTO unban_requests (user_id,reason,created_at) VALUES (?,?,?)')->execute([$u['id'],$reason,$now->format('Y-m-d H:i:s')]);
        return ['id'=>(int)db()->lastInsertId(),'week_start'=>$start->format('Y-m-d'),'next_eligible_at'=>$end->format(DATE_ATOM),'status'=>'menunggu'];
    }

    // ============================ helpers ============================
    private static function findGarage(int $id): ?array
    {
        $st = db()->prepare("SELECT g.*, c.name AS city, c.region_id, r.code AS region FROM garages g JOIN cities c ON c.id=g.city_id LEFT JOIN regions r ON r.id=c.region_id WHERE g.id=?");
        $st->execute([$id]); return $st->fetch() ?: null;
    }
    private static function findVehicle(int $id): ?array
    {
        $st = db()->prepare("SELECT v.*, p.name AS partner, g.name AS garage FROM vehicles v LEFT JOIN partners p ON p.id=v.partner_id LEFT JOIN garages g ON g.id=v.current_garage_id WHERE v.id=?");
        $st->execute([$id]); return $st->fetch() ?: null;
    }
    private static function cityRegion(int $cityId): ?int
    {
        $st = db()->prepare("SELECT region_id FROM cities WHERE id=?"); $st->execute([$cityId]);
        $r = $st->fetchColumn(); return $r ? (int)$r : null;
    }
    private static function assertRegion(array $admin, ?int $regionId): void
    {
        $regions = Scope::regionIds($admin);
        if ($regions === null) return; // super admin
        if ($regionId === null || !in_array($regionId, $regions, true)) throw new AppException('Di luar wilayah wewenang Anda.', 403);
    }
    private static function uniqueCode(string $prefix, string $table): string
    {
        for ($i = 0; $i < 25; $i++) {
            $code = $prefix . str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $st = db()->prepare("SELECT 1 FROM {$table} WHERE code=?"); $st->execute([$code]);
            if (!$st->fetch()) return $code;
        }
        throw new AppException('Gagal membuat kode.', 500);
    }
    private static function num($v) { return ($v === null || $v === '' || !is_numeric($v)) ? null : (float)$v; }
}
