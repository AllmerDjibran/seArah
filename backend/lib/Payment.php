<?php
/**
 * SEARAH — Orkestrasi Pembayaran (Duitku QRIS).
 * ------------------------------------------------------------------
 * createForBooking -> call Duitku inquiry -> simpan payments + qr.
 * handleCallback   -> verifikasi signature (HMAC) -> markPaid (payments+invoices+bookings).
 * check            -> sinkron status dari Duitku.
 * refund           -> catat refunds (Duitku v2 tak punya API refund publik -> proses via dashboard).
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/Response.php';
require_once __DIR__ . '/Duitku.php';
require_once __DIR__ . '/Booking.php';

final class Payment
{
    /** Buat/mulai pembayaran utk sebuah booking. */
    public static function createForBooking(int $bookingId, array $user): array
    {
        $dk = new Duitku();
        if (!$dk->isLive()) throw new AppException('Pembayaran belum dikonfigurasi.', 503);
        $booking = Booking::findOwned($bookingId, $user);
        if ($booking['status'] !== 'menunggu_pembayaran') throw new AppException('Status booking tidak dapat dibayar.', 409);
        $pdo = db();

        // sudah pernah ada pembayaran aktif?
        $st = $pdo->prepare("SELECT * FROM payments WHERE booking_id = ? AND status IN ('pending','paid') ORDER BY id DESC LIMIT 1");
        $st->execute([$bookingId]);
        if ($exist = $st->fetch()) {
            if(empty($exist['duitku_ref_id']))throw new AppException('Inquiry belum selesai atau hasil tidak pasti; rekonsiliasi operator diperlukan, jangan membuat transaksi baru.',409);
            return self::find((int)$exist['id']);
        }

        $amount = (int)$booking['fare_total'];
        if ($amount <= 0) throw new AppException('Nominal pesanan tidak valid.', 422);

        $inv = $pdo->prepare("SELECT id FROM invoices WHERE booking_id = ? LIMIT 1");
        $inv->execute([$bookingId]);
        $invoiceId = $inv->fetchColumn() ?: null;

        $dk      = new Duitku();
        $now     = date('Y-m-d H:i:s');
        $expired = date('Y-m-d H:i:s', time() + $dk->expiry() * 60);
        $code    = self::uniqueCode('PAY-', 'payments');

        $pdo->prepare(
            "INSERT INTO payments
             (code, booking_id, invoice_id, amount, currency, payment_method, gateway,
              merchant_code, duitku_payment_code, status, expired_at, created_at, updated_at)
             VALUES (?,?,?,?, 'IDR', 'qris', 'duitku', ?, ?, 'pending', ?, ?, ?)"
        )->execute([
            $code, $bookingId, $invoiceId, $amount,
            $dk->merchantCode() ?: null, $dk->paymentMethod(),
            $expired, $now, $now,
        ]);
        $paymentId = (int)$pdo->lastInsertId();

        if(!empty($GLOBALS['api_transaction'])){db()->exec('COMMIT');$GLOBALS['api_transaction']=false;}
        $res = $dk->createTransaction([
            'merchantOrderId' => $code,
            'amount'          => $amount,
            'email'           => $user['email'] ?? '',
            'phone'           => $user['phone'] ?? '',
            'productDetails'  => 'Pembayaran pesanan SEARAH ' . $booking['code'],
            'customerVaName'  => $user['name'] ?? 'Pelanggan',
        ]);

        db()->exec('BEGIN IMMEDIATE');$GLOBALS['api_transaction']=true;
        $pdo->prepare(
            "UPDATE payments SET duitku_ref_id = ?, redirect_url = ?, qr_string = ?,
                    duitku_channel_code = ?, updated_at = ? WHERE id = ?"
        )->execute([
            $res['reference'] ?: null, $res['paymentUrl'] ?: null, $res['qrString'] ?: null,
            $res['vaNumber'] ?? null, date('Y-m-d H:i:s'), $paymentId,
        ]);

        $pdo->prepare("INSERT INTO payment_logs (payment_id, event, payload_json, received_at) VALUES (?, 'create', ?, ?)")
            ->execute([$paymentId, json_encode($res, JSON_UNESCAPED_UNICODE), date('Y-m-d H:i:s')]);

        $out = self::find($paymentId);
        $out['mock'] = $res['mock'] ?? false;
        return $out;
    }

    /** Ambil payment by id (+ info booking/invoice). */
    public static function find(int $id): ?array
    {
        $st = db()->prepare(
            "SELECT p.*, b.code AS booking_code, b.package_type, b.status AS booking_status,
                    i.code AS invoice_code, i.status AS invoice_status
             FROM payments p
             JOIN bookings b ON b.id = p.booking_id
             LEFT JOIN invoices i ON i.id = p.invoice_id
             WHERE p.id = ?"
        );
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    /** Ambil dengan cek kepemilikan/admin. */
    public static function getOwned(int $id, array $user): array
    {
        $p = self::find($id);
        if (!$p) throw new AppException('Pembayaran tidak ditemukan.', 404);
        $own = db()->prepare("SELECT passenger_user_id FROM bookings WHERE id = ?");
        $own->execute([$p['booking_id']]);
        $owner = (int)($own->fetchColumn() ?: 0);
        if ($user['role'] !== 'admin' && $owner !== (int)$user['id']) {
            throw new AppException('Akses ditolak.', 403);
        }
        Booking::findOwned((int)$p['booking_id'], $user);
        return $p;
    }

    /**
     * Proses callback/notification dari Duitku (POST form).
     * Verifikasi signature -> markPaid / markFailed. Idempotent.
     */
    public static function handleCallback(array $post): array
    {
        $dk = new Duitku();
        $code = (string)($post['merchantOrderId'] ?? '');
        $reference = (string)($post['reference'] ?? '');

        $st = db()->prepare('SELECT * FROM payments WHERE code = ?');
        $st->execute([$code]);
        $payment = $st->fetch();

        $valid = $dk->verifyCallback($post);

        // log dulu (termasuk yang gagal signature) utk audit
        db()->prepare("INSERT INTO payment_logs (payment_id, event, payload_json, signature_ok, received_at) VALUES (?, 'callback', ?, ?, ?)")
            ->execute([
                $payment['id'] ?? null,
                json_encode($post, JSON_UNESCAPED_UNICODE),
                $valid ? 1 : 0,
                date('Y-m-d H:i:s'),
            ]);

        if (!$valid) throw new AppException('Bad Signature', 400);
        if (!$payment) throw new AppException('Transaksi tidak ditemukan', 404);

        if ((string)$payment['amount'] !== (string)($post['amount'] ?? '') || $reference === ''
            || !hash_equals((string)$payment['duitku_ref_id'], $reference)
            || $payment['merchant_code'] !== ($post['merchantCode'] ?? '')) throw new AppException('Identitas transaksi tidak cocok.', 409);
        $result = (string)($post['resultCode'] ?? '');
        if (!in_array($result, ['00', '01'], true)) throw new AppException('Result code tidak valid.', 422);
        if ($result === '00') {
            self::markPaid((int)$payment['id'], $reference);
            return ['ok' => true, 'refId' => $payment['code'], 'status' => self::find((int)$payment['id'])['status']];
        }
        if (!in_array($payment['status'], ['paid','refunded'], true)) {
            db()->prepare("UPDATE payments SET status='failed', duitku_result_code=?, updated_at=? WHERE id=?")
                ->execute([$result, date('Y-m-d H:i:s'), $payment['id']]);
        }
        return ['ok' => true, 'refId' => $payment['code'], 'status' => 'failed'];
    }

    /** Tandai lunas + update invoice & booking. Idempotent. */
    public static function markPaid(int $paymentId, string $reference = ''): void
    {
        $pdo = db();
        $st = $pdo->prepare("SELECT * FROM payments WHERE id = ?");
        $st->execute([$paymentId]);
        $p = $st->fetch();
        if (!$p) throw new AppException('Pembayaran tidak ditemukan.', 404);
        if (in_array($p['status'], ['paid', 'refunded'], true)) return;
        if ($reference === '' || !hash_equals((string)$p['duitku_ref_id'], $reference)) throw new AppException('Reference tidak cocok.', 409);

        $now = date('Y-m-d H:i:s');
        $pdo->prepare("UPDATE payments SET status='paid', paid_at=?, duitku_result_code='00', duitku_ref_id=COALESCE(NULLIF(?,''), duitku_ref_id), updated_at=? WHERE id=?")
            ->execute([$now, $reference, $now, $paymentId]);

        if ($p['invoice_id']) {
            $pdo->prepare("UPDATE invoices SET status='paid', paid_at=?, updated_at=? WHERE id=?")
                ->execute([$now, $now, $p['invoice_id']]);
        }
        $b=Booking::find((int)$p['booking_id']);
        if($b && $b['status']==='dibatalkan'){
            db()->prepare("INSERT INTO refunds (code,payment_id,booking_id,amount,method,reason,status) VALUES (?,?,?,?,'manual','Late payment after cancellation','pending')")->execute(['RFD-'.bin2hex(random_bytes(8)),$paymentId,$p['booking_id'],$p['amount']]);
        }
        // booking -> dibayar (hanya jika belum lanjut statusnya)
        $pdo->prepare("UPDATE bookings SET status='dibayar', updated_at=? WHERE id=? AND status IN ('menunggu_pembayaran','draft','dibayar')")
            ->execute([$now, $p['booking_id']]);
    }

    /** Sinkron status dari Duitku (check transaction). */
    public static function syncStatus(int $paymentId, array $user): array
    {
        $p = self::getOwned($paymentId, $user);
        $dk = new Duitku();
        if(!empty($GLOBALS['api_transaction'])){db()->exec('COMMIT');$GLOBALS['api_transaction']=false;}
        $res = $dk->checkTransaction((string)$p['code']);
        db()->exec('BEGIN IMMEDIATE');$GLOBALS['api_transaction']=true;
        db()->prepare("INSERT INTO payment_logs (payment_id, event, payload_json, received_at) VALUES (?, 'check', ?, ?)")
            ->execute([$paymentId, json_encode($res, JSON_UNESCAPED_UNICODE), date('Y-m-d H:i:s')]);
        if (($res['statusCode'] ?? '') === '00') {
            if ((string)($res['amount'] ?? '') !== (string)$p['amount'] || ($res['raw']['merchantOrderId'] ?? '') !== $p['code']) throw new AppException('Respons transaksi tidak cocok.', 502);
            self::markPaid($paymentId, (string)($res['reference'] ?? ''));
        }
        return self::find($paymentId);
    }

    /** Buat catatan refund (proses via dashboard Duitku). */
    public static function refund(int $paymentId, array $admin, ?int $amount, string $reason): array
    {
        $p = self::getOwned($paymentId, $admin);
        if ($p['status'] !== 'paid') throw new AppException('Hanya pembayaran lunas yang bisa direfund.', 422);
        $reserved = db()->prepare("SELECT COALESCE(SUM(amount),0) FROM refunds WHERE payment_id=? AND status IN ('pending','processed','completed')");
        $reserved->execute([$paymentId]);
        $remaining = (int)$p['amount'] - (int)$reserved->fetchColumn();
        $amount = $amount ?? $remaining;
        if ($amount > $remaining || trim($reason) === '') throw new AppException('Sisa refund tidak cukup atau alasan kosong.', 422);
        if ($amount <= 0 || $amount > (int)$p['amount']) throw new AppException('Nominal refund tidak valid.', 422);

        $code = self::uniqueCode('RFD-', 'refunds');
        db()->prepare(
            "INSERT INTO refunds (code, payment_id, booking_id, amount, method, reason, status, processed_by, created_at)
             VALUES (?,?,?,?, 'manual', ?, 'pending', ?, ?)"
        )->execute([$code, $paymentId, $p['booking_id'], $amount, $reason ?: null, $admin['id'], date('Y-m-d H:i:s')]);

        $st = db()->prepare("SELECT * FROM refunds WHERE code = ?");
        $st->execute([$code]);
        return $st->fetch();
    }

    /** Tandai refund selesai -> payments.status='refunded'. */
    public static function completeRefund(int $refundId, array $admin): array
    {
        $st = db()->prepare("SELECT * FROM refunds WHERE id = ?");
        $st->execute([$refundId]);
        $r = $st->fetch();
        if (!$r) throw new AppException('Refund tidak ditemukan.', 404);
        self::getOwned((int)$r['payment_id'], $admin);
        if ($r['status'] === 'completed') return $r;
        $evidence = trim((string)(request_body()['external_reference'] ?? ''));
        if ($evidence === '') throw new AppException('external_reference bukti transfer manual wajib; endpoint ini tidak mentransfer uang.', 422);

        $now = date('Y-m-d H:i:s');
        db()->prepare("UPDATE refunds SET status='completed', processed_at=?, processed_by=? WHERE id=?")
            ->execute([$now, $admin['id'], $refundId]);
        db()->prepare('UPDATE refunds SET duitku_ref=? WHERE id=?')->execute([$evidence,$refundId]);
        db()->prepare("UPDATE payments SET status='refunded', updated_at=? WHERE id=? AND amount <= (SELECT COALESCE(SUM(amount),0) FROM refunds WHERE payment_id=? AND status='completed')")
            ->execute([$now, $r['payment_id'], $r['payment_id']]);
        db()->prepare("SELECT * FROM refunds WHERE id = ?")->execute([$refundId]);
        $st = db()->prepare("SELECT * FROM refunds WHERE id = ?");
        $st->execute([$refundId]);
        return $st->fetch();
    }

    private static function uniqueCode(string $prefix, string $table): string
    {
        for ($i = 0; $i < 25; $i++) {
            $code = $prefix . str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $st = db()->prepare("SELECT 1 FROM {$table} WHERE code = ?");
            $st->execute([$code]);
            if (!$st->fetch()) return $code;
        }
        throw new AppException('Gagal membuat kode transaksi, coba lagi.', 500);
    }
}
