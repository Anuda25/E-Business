<?php
$localConfig = __DIR__ . '/config.local.php';
if (is_file($localConfig)) {
    require_once $localConfig;
}

if (!defined('DB_HOST')) define('DB_HOST', getenv('DB_HOST') !== false ? getenv('DB_HOST') : 'localhost');
if (!defined('DB_USER')) define('DB_USER', getenv('DB_USER') !== false ? getenv('DB_USER') : 'root');
if (!defined('DB_PASS')) define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
if (!defined('DB_NAME')) define('DB_NAME', getenv('DB_NAME') !== false ? getenv('DB_NAME') : 'tourguide_db');

if (!defined('BASE_URL')) define('BASE_URL', getenv('BASE_URL') !== false ? getenv('BASE_URL') : '/New/');
if (!defined('UPLOAD_DIR')) define('UPLOAD_DIR', __DIR__ . '/image/uploads/');
if (!defined('IMAGE_BASE')) define('IMAGE_BASE', getenv('IMAGE_BASE') !== false ? getenv('IMAGE_BASE') : '/New/image/');

if (!defined('PAYHERE_MERCHANT_ID')) define('PAYHERE_MERCHANT_ID', getenv('PAYHERE_MERCHANT_ID') !== false ? getenv('PAYHERE_MERCHANT_ID') : '');
if (!defined('PAYHERE_MERCHANT_SECRET')) define('PAYHERE_MERCHANT_SECRET', getenv('PAYHERE_MERCHANT_SECRET') !== false ? getenv('PAYHERE_MERCHANT_SECRET') : '');
if (!defined('PAYHERE_ENV')) define('PAYHERE_ENV', getenv('PAYHERE_ENV') !== false ? getenv('PAYHERE_ENV') : 'sandbox');
if (!defined('PAYHERE_CURRENCY')) define('PAYHERE_CURRENCY', getenv('PAYHERE_CURRENCY') !== false ? getenv('PAYHERE_CURRENCY') : 'USD');

if (session_status() === PHP_SESSION_NONE) {
    session_name('tourguide_session');
    session_start();
}
