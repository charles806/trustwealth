<?php
declare(strict_types=1);

define('APP_NAME', 'Trust Wealth Ltd');

/* Live market rate used across the site (demo). */
define('BTC_USD_RATE', 94500.0);

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? 0) == 443);
}

/*
 * Local development override.
 * Drop an app/config.local.php (gitignored) with the same constants to use a
 * local database, e.g.:
 *
 *   define('DB_HOST', '127.0.0.1');
 *   define('DB_PORT', 3306);
 *   define('DB_NAME', 'trust_wealth');
 *   define('DB_USER', 'root');
 *   define('DB_PASS', '');
 *
 * Without that file, the production (pxxl) database below is used.
 */
$__localConfig = __DIR__ . '/config.local.php';
if (is_file($__localConfig)) {
    require $__localConfig;
}

/* Database — production (pxxl) defaults. */
if (!defined('DB_HOST')) define('DB_HOST', '2440xm1uv.pxxldb.pxxl.pro');
if (!defined('DB_PORT')) define('DB_PORT', 52380);
if (!defined('DB_NAME')) define('DB_NAME', 'pxxldb_1a0102c98f18a9c');
if (!defined('DB_USER')) define('DB_USER', 'pxxluser_1a0102c98f1c976');
if (!defined('DB_PASS')) define('DB_PASS', 'HWK87fJbNcfKE4&dU9fzmEm0jw95KkqL');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/sessions.php';

/* Request is served over HTTPS? */
$__https = is_https();

/* Never leak errors to visitors on production; they're written to the log. */
if ($__https) {
    ini_set('display_errors', '0');
}

/* Double-submit CSRF cookie — set before any output so it reaches the client. */
if (!preg_match('/^[0-9a-f]{64}$/', $_COOKIE['tw_csrf'] ?? '')) {
    $_COOKIE['tw_csrf'] = bin2hex(random_bytes(32));
    setcookie('tw_csrf', $_COOKIE['tw_csrf'], [
        'expires' => time() + 60 * 60 * 24 * 30,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => $__https,
    ]);
}

/* Session boot. */
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.gc_maxlifetime', '604800');
    register_db_session_handler();
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => $__https,
        'path' => '/',
    ]);
    session_name('tw_session');
    session_start();
}