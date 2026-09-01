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
    return number_format($usd / BTC_USD_RATE, 6) . ' BTC';
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