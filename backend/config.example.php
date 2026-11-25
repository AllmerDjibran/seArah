<?php
/**
 * TEMPLATE secret. Salin jadi `config.local.php` lalu isi nilainya.
 *   cp config.example.php config.local.php
 * File config.local.php di-gitignore (JANGAN di-commit).
 */
return [
    'admin_default_password' => '', // password admin seed saat DB baru dibuat

    'duitku' => [
        'mode'            => 'sandbox',     // 'sandbox' | 'production'
        'merchant_code'   => '',      // MerchantCode dari dashboard Duitku
        'api_key'         => '',// API Key (apiKey) Duitku
        'callback_url'    => 'https://domainanda.com/backend/api/payment/callback',
        'notification_url'=> 'https://domainanda.com/backend/api/payment/notification',
    ],
];
