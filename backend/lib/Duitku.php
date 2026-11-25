<?php
/**
 * SEARAH — Klien Duitku (v2 API, HMAC-SHA256).
 * ------------------------------------------------------------------
 * Docs: https://docs.duitku.com/api/en/
 *  - Create : POST {base}/webapi/api/merchant/v2/inquiry  (JSON)
 *      signature = HMAC_SHA256(merchantCode + merchantOrderId + paymentAmount, apiKey)
 *      Response : reference, paymentUrl, qrString (QRIS), vaNumber, amount, statusCode("00"=sukses)
 *  - Status : POST {base}/webapi/api/merchant/transactionStatus
 *      signature = HMAC_SHA256(merchantCode + merchantOrderId, apiKey)
 *      statusCode: 00 sukses, 01 proses, 02 gagal/expired
 *  - Callback (notification): POST form ke callbackUrl
 *      signature = HMAC_SHA256(merchantCode + amount + merchantOrderId, apiKey)
 *  Catatan: TIDAK ada API refund publik di v2 (hanya "Support Void" utk e-wallet).
 *           Refund dicatat lokal lalu diproses via dashboard Duitku.
 *
 * Tanpa merchant_code/api_key -> mode MOCK (tanpa jaringan) agar flow bisa diuji lokal.
 */

require_once __DIR__ . '/Response.php';

final class Duitku
{
    private string $base;
    private array  $ep;
    private string $merchantCode;
    private string $apiKey;
    private string $paymentMethod;
    private int    $expiry;
    private string $callbackUrl;
    private string $returnUrl;
    private bool   $debug;

    public function __construct()
    {
        $cfg = require __DIR__ . '/../config.php';
        $d   = $cfg['duitku'];
        $this->base          = rtrim($d['base_url'], '/');
        $this->ep            = $d['endpoints'];
        $this->merchantCode  = (string)($d['merchant_code'] ?? '');
        $this->apiKey        = (string)($d['api_key'] ?? '');
        $this->paymentMethod = (string)($d['payment_method'] ?? 'SQ');
        $this->expiry        = (int)($d['expiry_period'] ?? 15);
        $this->callbackUrl   = (string)($d['callback_url'] ?? '');
        $this->returnUrl     = (string)($d['return_url'] ?? '');
        $this->debug         = (bool)($cfg['app']['debug'] ?? false);
    }

    /** Punya kredensial lengkap -> pakai jaringan; kalau tidak -> mock. */
    public function isLive(): bool
    {
        return $this->merchantCode !== '' && $this->apiKey !== '';
    }

    public function paymentMethod(): string { return $this->paymentMethod; }
    public function merchantCode(): string  { return $this->merchantCode; }
    public function expiry(): int           { return $this->expiry; }

    /** Signature HMAC-SHA256 (hex lowercase). */
    public function sign(string $stringToSign): string
    {
        return hash_hmac('sha256', $stringToSign, $this->apiKey);
    }

    /**
     * Buat transaksi (QRIS). $o = [merchantOrderId, amount, email, phone, productDetails, customerVaName].
     * Return: [success, mock, reference, paymentUrl, qrString, vaNumber, raw]
     */
    public function createTransaction(array $o): array
    {
        $merchantOrderId = (string)$o['merchantOrderId'];
        $amount          = (int)$o['amount'];

        if (!$this->isLive()) {
            throw new AppException('Pembayaran belum dikonfigurasi.', 503);

        }

        foreach ([$this->callbackUrl,$this->returnUrl] as $url) {
            if (!filter_var($url, FILTER_VALIDATE_URL) || parse_url($url, PHP_URL_SCHEME) !== 'https') throw new AppException('URL pembayaran HTTPS wajib dikonfigurasi.', 503);
        }
        $signature = $this->sign($this->merchantCode . $merchantOrderId . $amount);
        $payload = [
            'merchantCode'   => $this->merchantCode,
            'paymentAmount'  => $amount,
            'paymentMethod'  => $this->paymentMethod,
            'merchantOrderId'=> $merchantOrderId,
            'productDetails' => (string)($o['productDetails'] ?? 'Pembayaran SEARAH'),
            'customerVaName' => (string)($o['customerVaName'] ?? 'Pelanggan'),
            'email'          => (string)($o['email'] ?? ''),
            'phoneNumber'    => (string)($o['phone'] ?? ''),
            'callbackUrl'    => $this->callbackUrl,
            'returnUrl'      => $this->returnUrl,
            'expiryPeriod'   => $this->expiry,
            'signature'      => $signature,
        ];
        $resp = $this->postJson($this->base . $this->ep['inquiry'], $payload);
        $sc   = (string)($resp['statusCode'] ?? '');
        if ($sc !== '00' || empty($resp['reference']) || (string)($resp['amount'] ?? '') !== (string)$amount || ($resp['merchantCode'] ?? '') !== $this->merchantCode) throw new AppException('Duitku menolak atau mengembalikan transaksi tidak cocok.', 502);
        return [
            'success'    => $sc === '00',
            'mock'       => false,
            'reference'  => (string)($resp['reference'] ?? ''),
            'paymentUrl' => (string)($resp['paymentUrl'] ?? ''),
            'qrString'   => (string)($resp['qrString'] ?? ''),
            'vaNumber'   => $resp['vaNumber'] ?? null,
            'raw'        => $resp,
        ];
    }

    /** Cek status transaksi ke Duitku. */
    public function checkTransaction(string $merchantOrderId): array
    {
        if (!$this->isLive()) {
            throw new AppException('Pembayaran belum dikonfigurasi.', 503);
        }
        $signature = $this->sign($this->merchantCode . $merchantOrderId);
        $resp = $this->postJson($this->base . $this->ep['status'], [
            'merchantCode'    => $this->merchantCode,
            'merchantOrderId' => $merchantOrderId,
            'signature'       => $signature,
        ]);
        return [
            'success'       => (string)($resp['statusCode'] ?? '') === '00',
            'mock'          => false,
            'statusCode'    => (string)($resp['statusCode'] ?? ''),
            'statusMessage' => (string)($resp['statusMessage'] ?? ''),
            'reference'     => (string)($resp['reference'] ?? ''),
            'amount'        => $resp['amount'] ?? null,
            'raw'           => $resp,
        ];
    }

    /**
     * Verifikasi signature callback/notification.
     * stringToSign = merchantCode + amount + merchantOrderId ; bandingkan dgn signature (HMAC).
     */
    public function verifyCallback(array $p): bool
    {
        $mc     = (string)($p['merchantCode'] ?? '');
        $amount = (string)($p['amount'] ?? '');
        $moid   = (string)($p['merchantOrderId'] ?? '');
        $sig    = (string)($p['signature'] ?? '');
        if ($mc === '' || $amount === '' || $moid === '' || $sig === '') {
            return false;
        }
        if ($this->apiKey === '') {
            // dev: tidak ada api_key -> tidak bisa verifikasi; tolak kecuali debug (agar tetap bisa uji manual)
            return false;
        }
        if ($mc !== $this->merchantCode || !preg_match('/^[1-9][0-9]*$/', $amount)) return false;
        $calc = $this->sign($mc . $amount . $moid);
        return hash_equals($calc, $sig);
    }

    /** POST JSON via cURL. */
    private function postJson(string $url, array $data): array
    {
        if(defined('SEARAH_TEST_TRANSPORT') && SEARAH_TEST_TRANSPORT instanceof Closure){
            $cfg=require __DIR__.'/../config.php';
            $path=realpath($cfg['paths']['db']);$temp=realpath('C:/Users/allme/AppData/Local/Temp/qwenwork');
            if($cfg['app']['environment']!=='development'||!$path||!$temp||!str_starts_with(strtolower($path),strtolower($temp.DIRECTORY_SEPARATOR)))throw new AppException('Test transport forbidden.',503);
            return (SEARAH_TEST_TRANSPORT)($url,$data);
        }
        $body = json_encode($data, JSON_UNESCAPED_UNICODE);
        $ch   = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Content-Length: ' . strlen($body)],
        ]);
        $res  = curl_exec($ch);
        $err  = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($res === false) {
            throw new AppException('Gagal menghubungi Duitku: ' . $err, 502);
        }
        $json = json_decode($res, true);
        if ($code < 200 || $code >= 300 || !is_array($json)) {
            throw new AppException('Respons Duitku tidak valid (HTTP ' . $code . ').', 502);
        }
        return $json;
    }
}
