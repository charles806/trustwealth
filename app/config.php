<?php
declare(strict_types=1);

define('APP_NAME', 'Trust Wealth Ltd');

/* Live market rate used across the site — refreshed from a public API and
 * cached in the settings table (see btc_rate() in helpers.php). This define
 * is only the fallback for when the network is unreachable. */
define('BTC_USD_RATE', 94500.0);

/* Deposit wallet addresses — the client's own addresses. Override in Settings. */
define('BTC_DEPOSIT_ADDRESS', 'bc1q7wln9r6qw2kxwvrs8t3gr0k9z4hx7tkm');
define('USDT_DEPOSIT_ADDRESS', 'TXj9yQw3VJzJfTb2HcN5pLk8mQw6Ad3R1c');

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

/* Database — config.local.php (local dev) → DB_* env vars (pxxl dashboard).
 * Production credentials are never stored in this file: set DB_HOST, DB_PORT,
 * DB_NAME, DB_USER and DB_PASS in the pxxl dashboard (pointing at the AWS RDS).
 * A missing variable is fatal so the app can never silently hit the wrong DB. */
$__dbEnv = static function (string $key, ?string $default = null): string {
    $value = getenv($key);

    if ($value !== false) {
        return $value;
    }

    if ($default !== null) {
        return $default;
    }

    throw new RuntimeException(
        $key . ' is not set. Define the DB_* variables in the environment or create app/config.local.php.'
    );
};

if (!defined('DB_HOST')) define('DB_HOST', $__dbEnv('DB_HOST'));
if (!defined('DB_PORT')) define('DB_PORT', $__dbEnv('DB_PORT', '3306'));
if (!defined('DB_NAME')) define('DB_NAME', $__dbEnv('DB_NAME'));
if (!defined('DB_USER')) define('DB_USER', $__dbEnv('DB_USER'));
if (!defined('DB_PASS')) define('DB_PASS', $__dbEnv('DB_PASS'));

/* Optional TLS. Set DB_SSL_CA to a CA bundle (e.g. the AWS RDS global bundle)
 * to make app/db.php connect with certificate verification. */
if (!defined('DB_SSL_CA')) define('DB_SSL_CA', getenv('DB_SSL_CA') ?: '');

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