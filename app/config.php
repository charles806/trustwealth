<?php
declare(strict_types=1);

define('APP_NAME', 'Trust Wealth Ltd');

/* Live market rate used across the site (demo). */
define('BTC_USD_RATE', 94500.0);

/* Database — XAMPP defaults. Adjust if your setup differs. */
define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3306);
define('DB_NAME', 'trust_wealth');
define('DB_USER', 'root');
define('DB_PASS', '');

/* Session boot. */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => false,
        'path' => '/',
    ]);
    session_name('tw_session');
    session_start();
}