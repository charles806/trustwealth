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