<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function fmt_money(float $amount): string
{
    return '$' . number_format($amount, 2);
}

function fmt_btc(float $usd): string
{
    return number_format($usd / btc_rate(), 6) . ' BTC';
}

function fmt_date(?string $datetime, string $format = 'M j, Y H:i'): string
{
    if ($datetime === null || $datetime === '') {
        return '—';
    }

    $ts = strtotime($datetime);

    return $ts === false ? '—' : date($format, $ts);
}

function days_active(string $createdAt): int
{
    $ts = strtotime($createdAt);
    if ($ts === false) {
        return 1;
    }

    return max(1, (int) floor((time() - $ts) / 86400));
}

/*
 * A short, human-readable deposit reference — the client writes this (or their
 * sending address) in the payment memo so the operator can match funds that
 * land in the shared trust wallet back to a specific account.
 */
function generate_deposit_ref(): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; /* no 0/O/1/I */
    $code = '';
    $len = strlen($alphabet);

    for ($i = 0; $i < 6; $i++) {
        $code .= $alphabet[random_int(0, $len - 1)];
    }

    return 'TW-' . $code;
}

/*
 * Loose sanity check for a deposit from-address: no spaces, sensible length,
 * and only characters legal in a BTC/Tron(-USDT)/EVM address. We don't do a
 * full validation (that needs a chain API), just enough to keep attribution
 * clean.
 */
function valid_deposit_from(string $addr): bool
{
    $addr = trim($addr);

    if (strlen($addr) < 25 || strlen($addr) > 200) {
        return false;
    }

    /* Only word characters — covers Base58 (BTC/Tron), Bech32 (bc1…), and
     * EVM hex (0x…). Length + no-whitespace is enough to keep matching
     * clean; the admin verifies the real address on-chain. */
    return preg_match('/^[A-Za-z0-9]+$/', $addr) === 1;
}

/*
 * Live BTC/USD rate — the single source of truth for pricing across the app.
 *
 * Fetches from a free API, caches the result in the settings table (so the
 * fee shared host never blocks on the network), and falls back gracefully:
 *
 *   CoinGecko → Binance → last cached value → config default (BTC_USD_RATE)
 */
const BTC_RATE_CACHE_TTL = 60;

function btc_rate(): float
{
    static $rate = null;

    if ($rate !== null) {
        return $rate;
    }

    try {
        $cached = _btc_rate_cache();

        if ($cached !== null && time() - $cached['ts'] < BTC_RATE_CACHE_TTL) {
            return $rate = $cached['rate'];
        }

        $fresh = _fetch_btc_rate();

        if ($fresh > 0) {
            set_setting('btc_rate', (string) $fresh);
            set_setting('btc_rate_updated_at', (string) time());
            $rate = $fresh;
        } else {
            $rate = ($cached['rate'] ?? 0) > 0
                ? $cached['rate']
                : (defined('BTC_USD_RATE') ? (float) BTC_USD_RATE : 0.0);
        }
    } catch (Throwable $er) {
        error_log('btc_rate: ' . $er->getMessage());
        $rate = defined('BTC_USD_RATE') ? (float) BTC_USD_RATE : 0.0;
    }

    return $rate;
}

function _btc_rate_cache(): ?array
{
    $rate = setting('btc_rate');
    $ts = setting('btc_rate_updated_at');

    if ($rate !== '' && is_numeric($rate) && $ts !== '' && is_numeric($ts)) {
        return ['rate' => (float) $rate, 'ts' => (int) $ts];
    }

    return null;
}

function _fetch_btc_rate(): float
{
    $coingecko = _http_get_json(
        'https://api.coingecko.com/api/v3/simple/price?ids=bitcoin&vs_currencies=usd'
    );
    $value = $coingecko['bitcoin']['usd'] ?? null;

    if (is_numeric($value) && (float) $value > 0) {
        return (float) $value;
    }

    $binance = _http_get_json(
        'https://api.binance.com/api/v3/ticker/price?symbol=BTCUSDT'
    );
    $value = $binance['price'] ?? null;

    return is_numeric($value) && (float) $value > 0 ? (float) $value : 0.0;
}

function _http_get_json(string $url, int $timeout = 4): ?array
{
    $body = null;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => defined('APP_NAME') ? APP_NAME : 'Trust Wealth',
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        if ($status >= 400) {
            return null;
        }
    } elseif (ini_get('allow_url_fopen')) {
        $ctx = stream_context_create([
            'http' => [
                'timeout' => $timeout,
                'user_agent' => defined('APP_NAME') ? APP_NAME : 'Trust Wealth',
                'ignore_errors' => true,
            ],
        ]);
        $body = @file_get_contents($url, false, $ctx);
    }

    if ($body === false || $body === null || $body === '') {
        return null;
    }

    $data = json_decode($body, true);

    return is_array($data) ? $data : null;
}