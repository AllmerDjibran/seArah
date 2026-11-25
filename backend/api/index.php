<?php
/**
 * SEARAH — Front controller API.
 * ------------------------------------------------------------------
 * Rute aktif:
 *   AUTH      POST /api/auth/register | /login | /logout , GET /api/auth/me
 *   TARIF     GET  /api/tarif?origin=&dest=&package=&pax=&road=&insurance=
 *   RUTES     GET  /api/routes
 *   BOOKINGS  POST /api/bookings   (auth penumpang)  -> buat pesanan + invoice
 *             GET  /api/bookings    (auth)            -> daftar pesanan saya
 *             GET  /api/bookings/{id} (owner/admin)   -> detail
 *
 * Tanpa mod_rewrite:  index.php?r=tarif&origin=Malang&dest=Surabaya&package=reguler
 */

require_once __DIR__ . '/../lib/Response.php';
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/Pricing.php';
require_once __DIR__ . '/../lib/Booking.php';
require_once __DIR__ . '/../lib/Duitku.php';
require_once __DIR__ . '/../lib/Payment.php';
require_once __DIR__ . '/../lib/Scope.php';
require_once __DIR__ . '/../lib/Settings.php';
require_once __DIR__ . '/../lib/Trip.php';
require_once __DIR__ . '/../lib/Match.php';
require_once __DIR__ . '/../lib/Driver.php';
require_once __DIR__ . '/../lib/Admin.php';

$CFG = require __DIR__ . '/../config.php';

cors_headers($CFG);
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// --- resolve path -> segmen ---
$script = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$path   = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$path   = str_replace('\\', '/', $path);
if ($script !== '' && strpos($path, $script) === 0) {
    $path = substr($path, strlen($script));
}
$path = trim($path, '/');
if ($path === '' && isset($_GET['r'])) {
    $path = trim((string)$_GET['r'], '/');
}
$seg    = $path !== '' ? explode('/', $path) : [];
$group  = strtolower($seg[0] ?? '');
$action = strtolower($seg[1] ?? '');
$sub    = strtolower($seg[2] ?? '');
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

try {
    if (in_array($method, ['POST','PUT','PATCH','DELETE'], true)) {
        db()->exec('BEGIN IMMEDIATE');
        $GLOBALS['api_transaction'] = true;
    }
    switch ($group) {
        case 'documents':
            $u=Auth::requireUser();
            if($method==='GET'&&ctype_digit($action))json_ok(['document'=>Driver::document((int)$action,$u)]);
            json_error('Metode salah.',405);
            break;
        case 'availability':
            if($method!=='GET')json_error('Metode salah.',405);
            json_ok(Settings::choices($_GET));
            break;
        case 'unban':
            if($method!=='POST')json_error('Metode salah.',405);
            json_ok(['request'=>Admin::requestUnban(request_body())],201);
            break;
        case 'reports':
            $u=Auth::requireUser();
            if($method==='POST')json_ok(['report'=>Admin::userReport($u,request_body())],201);
            if($method==='GET'){$st=db()->prepare('SELECT * FROM reports WHERE reporter_user_id=? ORDER BY id DESC');$st->execute([$u['id']]);json_ok(['reports'=>$st->fetchAll()]);}
            json_error('Metode salah.',405);
            break;
        case 'references':
            if ($method !== 'GET') json_error('Metode salah.', 405);
            json_ok([
                'regions'=>db()->query('SELECT * FROM regions')->fetchAll(),
                'cities'=>db()->query('SELECT * FROM cities WHERE is_active=1')->fetchAll(),
                'garages'=>db()->query('SELECT id,name,city_id,address,lat,lng,phone,operating_hours FROM garages WHERE is_active=1')->fetchAll(),
                'vehicles'=>db()->query("SELECT id,model,plate,serves_package,seat_capacity,current_garage_id FROM vehicles WHERE status='aktif' AND current_garage_id IS NOT NULL")->fetchAll(),
                'drivers'=>db()->query("SELECT d.user_id,u.name,d.rating,d.rating_count,d.current_garage_id FROM drivers d JOIN users u ON u.id=d.user_id WHERE u.status='aktif' AND d.status IN ('disetujui','peringatan')")->fetchAll(),
            ]);
            break;
        case 'settings':
            if ($method !== 'GET') json_error('Metode salah.',405);
            json_ok(['settings'=>['currency'=>'IDR','packages'=>Pricing::PACKAGES,'payment_enabled'=>(new Duitku())->isLive(),'match_time_window_minutes'=>Settings::int('match_time_window_minutes',60),'match_duration_minutes'=>Settings::int('match_duration_minutes',240),'match_base_score'=>Settings::int('match_base_score',50),'match_fill_weight'=>Settings::int('match_fill_weight',50)]]);
            break;
        case 'docs':
            Auth::requireUser();
            if ($method !== 'GET') json_error('Metode salah.',405);
            json_ok(['contract'=>file_get_contents(__DIR__.'/../README.md')]);
            break;
        case 'invoices':
            $u = Auth::requireUser();
            if ($method !== 'GET' || !ctype_digit($action)) json_error('Metode salah.',405);
            $st=db()->prepare('SELECT * FROM invoices WHERE id=?'); $st->execute([(int)$action]); $invoice=$st->fetch();
            if (!$invoice) json_error('Invoice tidak ditemukan.',404);
            Booking::findOwned((int)$invoice['booking_id'],$u);
            json_ok(['invoice'=>$invoice]);
            break;
        case 'ratings':
            $u=Auth::requireUser('penumpang');
            if ($method !== 'POST') json_error('Metode salah.',405);
            $body=request_body(); $booking=Booking::findOwned((int)($body['booking_id']??0),$u);
            if ($booking['status'] !== 'selesai') json_error('Booking belum selesai.',409);
            $score=$body['score']??null;
            if (!is_int($score) || $score<1 || $score>5) json_error('Score integer 1-5 wajib.',422);
            $st=db()->prepare('SELECT t.id,t.driver_id FROM trips t JOIN trip_bookings tb ON tb.trip_id=t.id WHERE tb.booking_id=?'); $st->execute([$booking['id']]); $trip=$st->fetch();
            if (!$trip || !$trip['driver_id']) json_error('Driver tidak tersedia.',409);
            $st=db()->prepare('SELECT 1 FROM ratings WHERE booking_id=?'); $st->execute([$booking['id']]); if ($st->fetchColumn()) json_error('Rating sudah ada.',409);
            db()->prepare('INSERT INTO ratings (booking_id,trip_id,passenger_user_id,driver_user_id,score,comment) VALUES (?,?,?,?,?,?)')->execute([$booking['id'],$trip['id'],$u['id'],$trip['driver_id'],$score,substr((string)($body['comment']??''),0,2000)]);
            db()->prepare('UPDATE drivers SET rating=(SELECT AVG(score) FROM ratings WHERE driver_user_id=?),rating_count=(SELECT COUNT(*) FROM ratings WHERE driver_user_id=?) WHERE user_id=?')->execute([$trip['driver_id'],$trip['driver_id'],$trip['driver_id']]);
            json_ok(['message'=>'Rating tersimpan.'],201);
            break;
        /* ---------------- AUTH ---------------- */
        case 'auth':
            if ($action === 'register' && $method === 'POST') {
                json_ok(['user' => Auth::register(request_body())], 201);
            }
            if ($action === 'login' && $method === 'POST') {
                $b = request_body();
                json_ok(Auth::login((string)($b['email'] ?? ''), (string)($b['password'] ?? '')));
            }
            if ($action === 'me') {
                json_ok(['user' => Auth::requireUser()]);
            }
            if ($action === 'logout' && $method === 'POST') {
                Auth::requireUser();
                Auth::logout();
                json_ok(['message' => 'Berhasil keluar.']);
            }
            json_error('Endpoint auth tidak dikenal / metode salah.', 404);
            break;

        /* ---------------- TARIF (kutip harga) ---------------- */
        case 'tarif':
            $origin = (string)($_GET['origin'] ?? $_GET['asal'] ?? '');
            $dest   = (string)($_GET['dest']   ?? $_GET['tujuan'] ?? '');
            $pkg    = strtolower((string)($_GET['package'] ?? $_GET['paket'] ?? 'reguler'));
            $pax    = max(1, (int)($_GET['pax'] ?? $_GET['jumlah'] ?? 1));
            $road   = strtolower((string)($_GET['road'] ?? $_GET['jalan'] ?? 'biasa'));
            $ins    = in_array((string)($_GET['insurance'] ?? $_GET['asuransi'] ?? ''), ['1', 'true', 'ya', 'on'], true);
            if ($origin === '' || $dest === '') json_error('Parameter origin & dest wajib.', 422);
            $q = Pricing::quote($origin, $dest, $pkg, $pax, $road, $ins);
            json_ok($q);
            break;

        /* ---------------- RUTES (daftar rute + tarif) ---------------- */
        case 'routes':
            $rows = db()->query(
                "SELECT r.id, o.name AS origin, d.name AS dest, r.distance_km,
                        f.package_type, f.base_price, f.toll_price, f.protection_price, f.price_unit
                 FROM routes r
                 JOIN cities o ON o.id = r.origin_city_id
                 JOIN cities d ON d.id = r.dest_city_id
                 LEFT JOIN fares f ON f.route_id = r.id AND f.is_active = 1
                 WHERE r.is_active = 1
                 ORDER BY o.name, d.name, f.package_type"
            )->fetchAll();
            json_ok(['routes' => $rows]);
            break;

        /* ---------------- BOOKINGS ---------------- */
        case 'bookings':
            if(ctype_digit($action) && $sub==='location' && $method==='GET')json_ok(Booking::location((int)$action,Auth::requireUser()));
            if(ctype_digit($action) && $sub==='cancel' && $method==='POST')json_ok(Booking::cancel((int)$action,Auth::requireUser(),(string)(request_body()['reason']??'')));
            if ($action === '' && $method === 'POST') {
                $u = Auth::requireUser('penumpang');
                $res = Booking::create(request_body(), (int)$u['id']);
                json_ok($res, 201);
            }
            if ($action === '' && $method === 'GET') {
                $u = Auth::requireUser();
                json_ok(['bookings' => Booking::mine((int)$u['id'])]);
            }
            if ($action !== '' && ctype_digit($action) && $method === 'GET') {
                $u = Auth::requireUser();
                json_ok(['booking' => Booking::findOwned((int)$action, $u)]);
            }
            json_error('Endpoint bookings tidak dikenal / metode salah.', 404);
            break;

        /* ---------------- PAYMENTS (Duitku QRIS) ---------------- */
        case 'payments':
            if ($action === '' && $method === 'POST') {
                $u  = Auth::requireUser('penumpang');
                $b  = request_body();
                $bid = (int)($b['booking_id'] ?? 0);
                if ($bid <= 0) json_error('booking_id wajib diisi.', 422);
                json_ok(['payment' => Payment::createForBooking($bid, $u)], 201);
            }
            if (ctype_digit($action)) {
                $u = Auth::requireUser();
                if ($method === 'GET' && $sub === '') json_ok(['payment' => Payment::getOwned((int)$action, $u)]);
                if ($method === 'POST' && $sub === 'check') json_ok(['payment' => Payment::syncStatus((int)$action, $u)]);
                if ($method === 'POST' && $sub === 'refund') {
                    $a = Auth::requireUser('admin');
                    $b = request_body();
                    json_ok(['refund' => Payment::refund((int)$action, $a, isset($b['amount']) ? (int)$b['amount'] : null, (string)($b['reason'] ?? ''))], 201);
                }
            }
            json_error('Endpoint payments tidak dikenal / metode salah.', 404);
            break;

        case 'refunds':
            if ($method === 'POST' && $sub === 'complete' && ctype_digit($action)) {
                $a = Auth::requireUser('admin');
                json_ok(['refund' => Payment::completeRefund((int)$action, $a)]);
            }
            json_error('Endpoint refunds tidak dikenal / metode salah.', 404);
            break;

        case 'payment':
            if ($action === 'callback' && $method === 'POST') {
                // notifikasi server-to-server Duitku (form-urlencoded) = sumber kebenaran status
                $r = Payment::handleCallback($_POST);
                json_ok($r); // wajib balas 200 agar Duitku stop retry
            }
            if ($action === 'callback' && $method === 'GET') {
                // redirect user (tidak dipakai mengubah status)
                json_ok([
                    'redirect'       => true,
                    'merchantOrderId'=> $_GET['merchantOrderId'] ?? null,
                    'resultCode'     => $_GET['resultCode'] ?? null,
                    'note'           => 'Status final diambil dari callback POST (terverifikasi signature).',
                ]);
            }
            json_error('Endpoint payment tidak dikenal.', 404);
            break;

        /* ---------------- ADMIN: Smart Match & Trip ---------------- */
        case 'admin':
            $u   = Auth::requireUser('admin');
            Scope::regionIds($u);
            $res = $seg[1] ?? '';
            if ($res === 'match') {
                $verb = $seg[2] ?? '';
                if ($verb === 'run' && $method === 'POST') json_ok(SmartMatch::run($u));
                if ($verb === 'suggestions' && $method === 'GET') json_ok(['suggestions' => SmartMatch::listPending($u)]);
                if (ctype_digit($verb)) {
                    $sid = (int)$verb; $op = strtolower($seg[3] ?? '');
                    if ($op === 'accept' && $method === 'POST') json_ok(SmartMatch::accept($sid, $u, request_body()));
                    if ($op === 'reject' && $method === 'POST') json_ok(SmartMatch::reject($sid, $u));
                }
            }
            if ($res === 'trips') {
                if ($method === 'GET' && !isset($seg[2])) json_ok(['trips' => Trip::list($u)]);
                if ($method === 'POST' && !isset($seg[2])) json_ok(['trip' => Trip::create(request_body(), $u)], 201);
                if (isset($seg[2]) && ctype_digit($seg[2])) {
                    $tid = (int)$seg[2]; $op = strtolower($seg[3] ?? '');
                    if ($method === 'GET' && $op === '') json_ok(['trip' => Trip::detail($tid, $u)]);
                    if ($method === 'POST' && $op === 'confirm') json_ok(['trip' => Trip::confirm($tid, $u)]);
                    if ($method === 'POST' && $op === 'bookings') {
                        $b = request_body(); json_ok(['attach' => Trip::attach($tid, (int)($b['booking_id'] ?? 0), $u)]);
                    }
                }
            }
            if ($res === 'relocations') {
                if ($method === 'GET' && !isset($seg[2])) json_ok(['relocations' => Driver::listPendingRelocations($u)]);
                if (isset($seg[2]) && ctype_digit($seg[2]) && $method === 'POST') {
                    $rid = (int)$seg[2]; $op = strtolower($seg[3] ?? '');
                    if ($op === 'approve') json_ok(['relocation' => Driver::decideRelocation($rid, $u, true, (string)(request_body()['note'] ?? ''))]);
                    if ($op === 'reject')  json_ok(['relocation' => Driver::decideRelocation($rid, $u, false, (string)(request_body()['note'] ?? ''))]);
                }
            }
            if ($res === 'garasi') {
                if ($method === 'GET' && !isset($seg[2])) json_ok(['garages' => Admin::listGarages($u)]);
                if ($method === 'POST' && !isset($seg[2])) json_ok(['garage' => Admin::createGarage($u, request_body())], 201);
                if (isset($seg[2]) && ctype_digit($seg[2])) {
                    $gid = (int)$seg[2]; $op = strtolower($seg[3] ?? '');
                    if (in_array($method, ['POST', 'PUT', 'PATCH'], true) && $op === '') json_ok(['garage' => Admin::updateGarage($u, $gid, request_body())]);
                    if ($method === 'DELETE' || ($method === 'POST' && $op === 'disable')) json_ok(['garage' => Admin::disableGarage($u, $gid)]);
                }
            }
            if ($res === 'vehicles') {
                if ($method === 'GET' && !isset($seg[2])) json_ok(['vehicles' => Admin::listVehicles($u, $_GET)]);
                if ($method === 'POST' && !isset($seg[2])) json_ok(['vehicle' => Admin::createVehicle($u, request_body())], 201);
                if (isset($seg[2]) && ctype_digit($seg[2])) {
                    $vid = (int)$seg[2]; $op = strtolower($seg[3] ?? '');
                    if (in_array($method, ['POST', 'PUT', 'PATCH'], true) && $op === '') json_ok(['vehicle' => Admin::updateVehicle($u, $vid, request_body())]);
                    if ($method === 'DELETE' || ($method === 'POST' && $op === 'disable')) json_ok(['vehicle' => Admin::disableVehicle($u, $vid)]);
                    if ($method === 'POST' && $op === 'move') { $b = request_body(); json_ok(['vehicle' => Admin::moveVehicle($u, $vid, (int)($b['to_garage_id'] ?? 0), (string)($b['note'] ?? ''))]); }
                }
            }
            if ($res === 'verify') {
                if ($method === 'GET' && ($seg[2] ?? '') === 'pending') json_ok(['drivers' => Admin::listPendingDrivers($u)]);
                if ($method === 'POST' && isset($seg[2]) && ctype_digit($seg[2])) {
                    $b = request_body(); json_ok(Admin::reviewVerification($u, (int)$seg[2], !empty($b['approve']), (string)($b['note'] ?? '')));
                }
            }
            if ($res === 'accounts') {
                if($method==='POST' && !isset($seg[2]))json_ok(['user'=>Admin::account($u,request_body())],201);
                if(isset($seg[2]) && ctype_digit($seg[2]) && !isset($seg[3])){
                    if($method==='GET'){if(Scope::regionIds($u)!==null)json_error('Super admin wajib.',403);json_ok(['user'=>Auth::userById((int)$seg[2])]);}
                    if(in_array($method,['PUT','PATCH','POST'],true))json_ok(['user'=>Admin::account($u,request_body(),(int)$seg[2])]);
                    if($method==='DELETE')json_ok(Admin::setAccountStatus($u,(int)$seg[2],'dinonaktifkan','Soft delete account'));
                }
                if ($method === 'GET' && in_array($seg[2] ?? '', ['admin','penumpang', 'pengemudi'], true)) json_ok(['accounts' => Admin::listAccounts($u, $seg[2])]);
                if ($method === 'POST' && isset($seg[2]) && ctype_digit($seg[2]) && ($seg[3] ?? '') === 'status') {
                    $b = request_body(); json_ok(Admin::setAccountStatus($u, (int)$seg[2], (string)($b['status'] ?? ''), (string)($b['reason'] ?? '')));
                }
            }
            if ($res === 'reports') {
                if ($method === 'GET' && !isset($seg[2])) json_ok(['reports' => Admin::listReports($u, $_GET['type'] ?? null)]);
                if ($method === 'POST' && !isset($seg[2])) json_ok(['report' => Admin::createReport($u, request_body())], 201);
                if (isset($seg[2]) && ctype_digit($seg[2]) && ($seg[3] ?? '') === 'update' && $method === 'POST') json_ok(['report' => Admin::updateReport($u, (int)$seg[2], request_body())]);
            }
            if ($res === 'unban') {
                if ($method === 'GET' && !isset($seg[2])) json_ok(['requests' => Admin::listUnbanRequests($u)]);
                if (isset($seg[2]) && ctype_digit($seg[2]) && ($seg[3] ?? '') === 'review' && $method === 'POST') {
                    $b = request_body(); json_ok(['request' => Admin::reviewUnban($u, (int)$seg[2], !empty($b['approve']), (string)($b['note'] ?? ''))]);
                }
            }
            json_error('Endpoint admin tidak dikenal / metode salah.', 404);
            break;

        /* ---------------- DRIVER ---------------- */
        case 'driver':
            $u = Auth::requireUser('pengemudi');
            if($action==='documents' && $method==='POST')json_ok(['document'=>Driver::upload($u,request_body())],201);
            if ($action === 'trips' && $sub === '' && $method === 'GET') json_ok(['trips' => Driver::myTrips($u)]);
            if ($action === 'trips' && ctype_digit($sub)) {
                $tid = (int)$sub; $op = strtolower($seg[3] ?? '');
                if ($method === 'POST' && $op === 'respond')  { $b = request_body(); json_ok(Driver::respond($tid, $u, (string)($b['response'] ?? ''), (string)($b['reason'] ?? ''))); }
                if ($method === 'POST' && $op === 'status')   { $b = request_body(); json_ok(Driver::updateStatus($tid, $u, (string)($b['event'] ?? ''), $b['lat'] ?? null, $b['lng'] ?? null)); }
                if ($method === 'POST' && $op === 'location') { $b = request_body(); json_ok(Driver::sendLocation($tid, $u, $b['lat'] ?? null, $b['lng'] ?? null, $b['accuracy'] ?? null)); }
            }
            if ($action === 'relocation') {
                if ($method === 'POST' && $sub === '') json_ok(['relocation' => Driver::relocationRequest($u, request_body())], 201);
                if ($method === 'GET'  && $sub === '') json_ok(['relocations' => Driver::myRelocations($u)]);
                if (ctype_digit($sub) && $method === 'POST') json_ok(['relocation' => Driver::verifyRelocation((int)$sub, $u, request_body())]);
            }
            json_error('Endpoint driver tidak dikenal / metode salah.', 404);
            break;

        default:
            json_error('Endpoint tidak ditemukan.', 404);
    }
} catch (Throwable $e) {
    if (!empty($GLOBALS['api_transaction'])) { db()->exec('ROLLBACK'); $GLOBALS['api_transaction'] = false; }
    // Kode exception (AuthException/AppException) = status HTTP; selain itu 500.
    $code = (int)$e->getCode();
    if ($code < 400 || $code > 599) $code = 500;
    $payload = ['ok' => false];
    $payload['error'] = $code >= 500 ? 'Terjadi kesalahan server.' : $e->getMessage();
    if ($CFG['app']['debug'] && $code >= 500) {
        $payload['debug'] = $e->getMessage();
    }
    json_out($payload, $code);
}
