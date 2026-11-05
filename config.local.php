<?php
define('PAYHERE_NOTIFY_URL', 'https://identifying-let-eat-jerry.trycloudflare.com/?action=payhere_notify');
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'tourguide_db');

// Application Configuration
define('BASE_URL', '/New/');
define('UPLOAD_DIR', __DIR__ . '/image/uploads/');
define('IMAGE_BASE', '/New/image/');

// PayHere Payment Gateway Configuration
define('PAYHERE_MERCHANT_ID', '1238511');
define('PAYHERE_MERCHANT_SECRET', 'MTQ5MTA3Njg4NDU1MTM1NjgzOTI5ODY2NTg1MTczNzQ5NTIwNjc3');
define('PAYHERE_ENV', 'sandbox');
define('PAYHERE_CURRENCY', 'USD');

// Session Configuration
session_name('tourguide_session');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
