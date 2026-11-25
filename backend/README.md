# SeArah Backend: Contract and Audit Status

PHP native 8.2 + PDO SQLite retained. Backend is partially hardened, NOT production-certified. Existing production database was not opened by tests or migrated. Frontend was not modified.

## Continuation Update (Overrides Earlier Limitations)

Frontend js/api.js was read; endpoints keep the same-origin JSON {ok:true,...} and Bearer contract, no frontend edits.

New GET /api/availability uses origin,dest,package,trip_date,time,pax,road and optional pickup/drop coordinates. Returns vehicles with seats_booked,target_trip_id (D2D occupancy-first), separate drivers with rating and distance_km, geometry, reservation:false. Checks calendar, draft/confirmed/active trips and Private booking holds. Private creation now requires vehicle_id and driver_id, checks availability and garage-based driver radius, and reserves by the booking record. Unpaid Private hold lasts 15 minutes; paid holds last until canceled/completed. No new schema migration needed.

Geometry is Haversine origin->pickup->drop->destination extra distance and bearing delta against directed route, NOT road-network routing or traffic. Configurable match_max_detour_km default 20, match_max_bearing_degrees default 45; enforced on availability and attachment. Driver radius uses current approved garage and assign_radius_km/global default, not live GPS. Attachment checks time tolerance; confirmation rechecks resource reservations and driver radius. Shared multi-booking aggregate stop optimization is still incomplete.

GET /api/bookings/{id}/location => {location:{lat,lng,accuracy,recorded_at}|null,stale:boolean}; owner or scoped admin only, no other passengers' addresses. POST /api/bookings/{id}/cancel {reason} cancels before active/completed, adjusts seats/releases empty trip, creates manual pending refund for paid balance. Late successful payment after cancellation creates pending manual refund without reviving booking. Refund is never automatic money movement.

POST /api/reports {booking_id,description,category?} derives reporter/subject/region from owned passenger booking or assigned driver trip. GET /api/reports lists own reports. Existing admin report update/actions remain; account sanctions require explicit super admin.

POST /api/unban {email,password,reason} authenticates banned/suspended account without issuing token. One request per Sunday-start week in Asia/Jakarta, computed from existing created_at records, no cron/reset or schema column. Admin review still Sunday-only.

Admin accounts: POST /api/admin/accounts {name,email,password,role,region_ids?,current_garage_id?}; GET /api/admin/accounts/admin|penumpang|pengemudi; GET/PATCH/PUT/POST /api/admin/accounts/{id}; DELETE soft-disables. Existing role changes prohibited. Explicit super admin only, self status/scope changes denied, last active explicitly granted administrator protected. New admin requires region_ids or an out-of-band explicit super-admin grant; creation never grants unrestricted access automatically. Password update revokes tokens.

Identity upload: POST /api/driver/documents {jenis:KTP|SIM|STNK|KIR,content_base64}. JPEG/PNG only, <=600KB, <=12M pixels, decoded and re-encoded JPEG to random filename (strips metadata/polyglot bytes). Set SEARAH_DOCUMENT_DIR to an existing private directory outside project webroot. GET /api/documents/{id} returns {document:{...,mime,content_base64}}, owner/scoped admin only; paths never returned. New upload invalidates previous kind approval and requires re-review. No antivirus, encryption-at-rest or full retention/orphan cleanup yet; upload storage isn't transactionally rolled back with SQLite.

Gateway lock update: pending inquiry intent commits BEFORE external transport, completion reopens short BEGIN IMMEDIATE. Concurrent/incomplete inquiry returns 409 for reconciliation, no blind retry/new invoice. Status check likewise outside write lock. Unknown/failed inquiry may leave pending intent requiring operator reconciliation. Test transport injection requires a PHP Closure constant set by external test router, development environment AND DB physically in approved temp directory. No request/env arbitrary endpoint or automatic production mock.

Actual continuation verification: 91 HTTP checks passed on unique disposable SQLite, including three-package booking->payment->valid signed callback/replay->trip->driver GPS->completion->rating, ownership denial, wrong callback reference, manual refund evidence/completion and replay preserving refunded, Private double-book rejection, account self-ban denial, user reports/admin review, cancellation and weekly appeal duplicate denial. Transport asserted another PDO connection could BEGIN IMMEDIATE while gateway ran. These are fixture responses, not real Duitku settlement. Secure upload, aggregate detour, D2D occupancy ordering, last-admin edge cases, regional admin mutations, Sunday review boundary, and late-payment cancellation still need dedicated expanded tests. No production claim.

## Deployment

Required extensions: pdo_sqlite, curl, mbstring. Production defaults: SEARAH_DEBUG=0, no usable default admin password, no automatic payment mock. Set SEARAH_DB_PATH to a writable absolute database path outside the webroot. Existing databases are not automatically migrated unless SEARAH_MIGRATE=1. Do not enable migration on live data without backup and review: schema reference seeds are not a versioned migration system.

SEARAH_SUPER_ADMIN_IDS is an explicit comma-separated list of trusted admin user IDs. An admin without this grant or user_region_scopes is denied, not implicitly elevated. Regional accounts management currently fails closed to explicit super admins rather than pretending passengers have a regional ownership model.

Demo requires BOTH SEARAH_ENV=development and SEARAH_DEMO=1 on a NEW isolated database. Accounts: admin@searah.id, pengemudi@searah.id, penumpang@searah.id; password 12345678, stored with password_hash. Demo does not overwrite existing account passwords. Grant demo administrator explicitly with SEARAH_SUPER_ADMIN_IDS=1. Demo drivers are verified fixture accounts, not real document verification.

Configure DUITKU_MERCHANT_CODE, DUITKU_API_KEY, DUITKU_MODE=sandbox|production, DUITKU_PAYMENT_METHOD=SQ, DUITKU_CALLBACK_URL and DUITKU_RETURN_URL (HTTPS). Missing credentials => 503, never fabricated QR or paid state. No secrets belong in frontend or settings responses.

Run locally: php -S 127.0.0.1:8080 server.php. This router is development-only. Production must restrict public access to backend/api only; deny config, lib, schema, data, local configuration, dotfiles, and backups at the webserver. Keep database outside webroot. TLS, login rate limiting, monitoring, retention and tested backups are deployment requirements.

## Transport

JSON success: {ok:true,...}; errors: {ok:false,error:string}. Authorization: Bearer <64 hexadecimal token>. Query tokens and X-Token are no longer supported. Tokens are hashed in SQLite. Non-active accounts cannot log in or reuse an existing token. Use same origin; wildcard CORS is disabled. JSON requests require an object, with a 1 MiB declared-length limit. Never use redirect resultCode as payment proof.

HTTP mutations execute inside BEGIN IMMEDIATE and commit only on successful response, rollback on errors. This currently holds the SQLite write lock during gateway calls: safe serialization but poor throughput. Direct PHP library callers must manage their own transaction for multi-operation writes. This is not a general-purpose concurrency architecture.

## Public Endpoints

- GET /api/routes: {routes:[{id,origin,dest,distance_km,package_type,base_price,toll_price,protection_price,price_unit}]}
- GET /api/tarif?origin=Malang&dest=Surabaya&package=reguler&pax=2&road=tol&insurance=1: {route,fare,calc:{unit,qty,base,toll,protection,total,unit_price}}. Currency integer IDR. Reguler/D2D per person, Private per vehicle according to fare record.
- GET /api/references: {regions,cities,garages,vehicles,drivers}. Vehicles include serves_package,seat_capacity,current_garage_id. Driver choices are separate user IDs and include rating/rating_count; no passwords/documents. This is a reference catalog, NOT a reservation or availability guarantee.
- GET /api/settings: {settings:{currency,packages,payment_enabled,match_time_window_minutes,match_duration_minutes,match_base_score,match_fill_weight}}. payment_enabled only indicates credentials present, not gateway health or complete URL configuration.
- POST /api/auth/register: {name,email,password,role:penumpang|pengemudi,phone?,username?} => 201 {user}. Public admin registration rejected. Password 8-72 bytes.
- POST /api/auth/login: {email,password} => {token,user}.

## Authenticated Endpoints

- GET /api/auth/me => {user}; POST /api/auth/logout.
- GET /api/docs => {contract:string}, this document, requires login. This is API contract documentation, NOT driver identity-document storage.
- POST /api/bookings (penumpang): {package:reguler|door_to_door|private,origin,dest,trip_date:YYYY-MM-DD,time:HH:MM,pax:1..7,road:biasa|tol,insurance:boolean,vehicle_id?,driver_id?,pickup_address?,drop_address?,pickup_lat?,pickup_lng?,drop_lat?,drop_lng?} => 201 {booking,invoice,calc}. Future valid date/time mandatory. D2D/Private require addresses and all four bounded coordinates. Private driver choice independent of vehicle. Client totals ignored.
- GET /api/bookings => {bookings} owned by current user. GET /api/bookings/{id} => {booking}, owner or scoped admin.
- GET /api/invoices/{id} => {invoice}, owner or scoped admin.
- POST /api/ratings: {booking_id,score:integer 1..5,comment?} => 201. Completed owned booking only, one rating per booking, updates driver aggregate.
- POST /api/payments: {booking_id} => 201 {payment}. Unconfigured gateway => 503 without creating a pending payment. Booking must be awaiting payment.
- GET /api/payments/{id}; POST /api/payments/{id}/check => {payment}, owner or scoped admin. Status response amount/order/reference checked before marking paid.
- POST /api/payment/callback: form-urlencoded merchantCode,merchantOrderId,reference,amount,signature,resultCode. Requires configured key and exact persisted merchant/order/amount/reference; codes 00 success,01 failure. Invalid callbacks return errors; valid processed callbacks return 200. Paid/refunded records resist callback reversal; repeated success does not revive refunded payments.
- GET /api/payment/callback is informational only, never changes payment status.

## Admin Endpoints

All /api/admin endpoints require active admin plus explicit scope/grant.

- GET/POST /api/admin/garasi => {garages}|201 {garage}; create requires name,city_id,address,lat,lng; phone,operating_hours optional. No template required. POST/PUT/PATCH /api/admin/garasi/{id}; DELETE or POST /disable soft-disables. Source and destination city scope checked on update.
- GET/POST /api/admin/vehicles => {vehicles}|201 {vehicle}; create requires model,plate,serves_package,seat_capacity,current_garage_id active; partner_id/year optional. POST/PUT/PATCH /{id}; DELETE or POST /{id}/disable; POST /{id}/move {to_garage_id,note?}. Current and destination garage scope checked.
- GET /api/admin/verify/pending; POST /api/admin/verify/{driverUserId} {approve:boolean,note?}. KTP and SIM records required for approval, but secure file upload/download is NOT implemented.
- GET /api/admin/accounts/penumpang|pengemudi; POST /api/admin/accounts/{id}/status {status:aktif|suspend|ban|dinonaktifkan,reason?}. Global account management requires explicit super admin. Self status change rejected. Admin account creation/CRUD not yet implemented.
- GET/POST /api/admin/reports; create {type:terhadap_driver|terhadap_penumpang,region_id,subject_user_id?,reporter_user_id?,booking_id?,category?,description?}. POST /{id}/update {status:menunggu|diproses|selesai,tindakan?,subject_action:suspend|ban|aktifkan?}. Unknown subject action rejected rather than silently activating.
- GET /api/admin/unban; POST /{id}/review {approve:boolean,note?}. Requires explicit super admin; review allowed only Sunday Asia/Jakarta. No automatic weekly job and no user appeal-submission endpoint yet.
- GET/POST /api/admin/trips; create {package,route_id,trip_date,departure_time,origin_garage_id?,dest_garage_id?,road_type,vehicle_id,driver_id,capacity?}. GET /{id}; POST /{id}/bookings {booking_id}; POST /{id}/confirm.
- Attach requires paid booking, draft trip, matching package/date/road/route and selected resources; adds pax_count, not one seat. Private is one exclusive booking. Confirmation requires manifest, active linked vehicle, valid capacity, active verified driver and time. Conflicting non-available calendar slots rejected. Default duration 240 minutes; overnight intervals rejected explicitly, not incorrectly wrapped.
- POST /api/admin/match/run; GET /api/admin/match/suggestions; POST /api/admin/match/{id}/accept {target_trip_id?}; POST /{id}/reject. Proposals are not confirmations.
- POST /api/payments/{id}/refund {amount?,reason} creates manual pending record, not a transfer. Aggregate pending/processed/completed refunds cannot exceed paid amount.
- POST /api/refunds/{id}/complete {external_reference} records operator-confirmed external refund. Evidence reference mandatory; only full aggregate refund marks payment refunded. No claim of automatic Duitku refund.

## Driver Endpoints

- GET /api/driver/trips.
- POST /api/driver/trips/{id}/respond {response:terima|tolak,reason?}.
- POST /api/driver/trips/{id}/status {event:berangkat|tiba_jemput|berlangsung|tiba_drop|selesai|dibatalkan,lat?,lng?}. Assigned driver and accepted assignment required. Completed/canceled trips cannot restart. Completion increments count once and releases calendar slots; booking status follows trip.
- POST /api/driver/trips/{id}/location {lat,lng,accuracy?}; bounded finite GPS, assigned active trip only.
- GET/POST /api/driver/relocation; POST body {requested_garage_id,verify_method:confirm|gps_scan|manual_map,lat?,lng?,work_date?,start_time?,end_time?,note?}.
- POST /api/driver/relocation/{id}/verify {action:confirm|gps_scan|manual_map,requested_garage_id?,lat?,lng?}.
- GET /api/admin/relocations; POST /{id}/approve|reject {note?}; active destination and destination scope required. Current garage changes only after admin approval.

## Matching Defaults and Limitations

Runtime keys read from settings table: match_time_window_minutes=60, match_duration_minutes=240, match_base_score=50, match_fill_weight=50. Candidate score = base + fill_ratio * fill_weight - time_delta_minutes. Same directed route, package/date/road, capacity and selected vehicle are hard filters. Existing candidate SQL orders by occupancy first. Parameters are DB configurable, but validated admin settings CRUD is not implemented.

NOT completed: geographic detour calculation, bearing/direction tolerance beyond exact directed route, driver assignment radius enforcement, robust busy-first D2D vehicle-choice endpoint, stale GPS validation, genuine reservation search, private availability at booking creation, overnight intervals, full relocation source-scope/proof checks, rate limiting, account/profile/password CRUD, secure identity document upload/download, user reports/appeal submission, cancellation, strict event state machine. New-trip match acceptance still requires resources and capacity; options are not a full resource allocation workflow. Do not treat the reference catalog as Smart Match.

## Verification

Temporary isolated SQLite module test ran successfully: 16 checks covering demo hashes, no-scope denial, public admin registration denial, booking/invoice consistency for all three packages, missing payment credentials, callback rejection without key, ownership denial, required garage data, vehicle garage requirement, unpaid attach denial, pax counting, invalid GPS, completed-trip restart denial, foreign_key_check. Driver accepted/start/GPS/complete flow also executed. Test script stored outside workspace in approved temp directory. Successful payment was NOT called against Duitku; paid state was explicitly injected into the disposable test DB solely to exercise trip flow. No real DB modification.

These are direct module tests, NOT complete HTTP E2E tests, callback signature/replay fixtures, concurrent race testing, successful manual refunds, Sunday review, or all three package trip lifecycles. No assertion that all weaknesses are fixed.

## Official Duitku Verification

Read https://docs.duitku.com/api/en/ directly during this audit. Current inquiry signature is HMAC-SHA256(merchantCode+merchantOrderId+paymentAmount,apiKey); status is HMAC-SHA256(merchantCode+merchantOrderId,apiKey); callback is HMAC-SHA256(merchantCode+amount+merchantOrderId,apiKey). Current page calls previous MD5 obsolete. Status HTTP section says form-urlencoded while examples use JSON; implementation follows official JSON examples. Confirm with sandbox credentials before deployment. The page does not establish a universal public refund API; this implementation only records manual external settlement.
