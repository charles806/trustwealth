<?php
declare(strict_types=1);

if (!function_exists('current_user')) {
    require_once __DIR__ . '/../app/auth.php';
}

/* First-request warm-up: guarantees the schema is built on any page load. */
db();

$user = current_user();
$isAuth = $user !== null;
$pageTitle = $pageTitle ?? APP_NAME;
$bodyClass = $bodyClass ?? '';
$extraCss = $extraCss ?? '';
$active = $active ?? '';

/*
 * Pages under /admin/ include this partial from a subdirectory, so every
 * relative asset/nav link needs a "../" prefix pointing back at the site root.
 * Computed from the request path so it works at ./?run? both locally and on pxxl.
 */
$basePrefix ??= str_repeat(
    '../',
    count(array_filter(explode('/', trim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/'))))
);
$base = $basePrefix;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= htmlspecialchars($pageDescription ?? 'Trust Wealth Ltd manages pooled capital with a long-term, transparent philosophy. Trade Bitcoin and USDT on plans that pay out daily.') ?>">
    <title><?= htmlspecialchars($pageTitle) ?> — Trust Wealth</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://api.fontshare.com">
    <link href="https://api.fontshare.com/v2/css?f[]=clash-display@500,600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=Space+Grotesk:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= $base ?>BTC.css">
    <?php if ($extraCss !== '') : ?><link rel="stylesheet" href="<?= $base ?><?= e($extraCss) ?>"><?php endif; ?>
</head>
<body class="<?= e($bodyClass) ?>">
    <a class="skip-link" href="#main">Skip to content</a>

    <header>
        <nav>
            <a class="logo" href="<?= $base ?>index.php" aria-label="<?= e(APP_NAME) ?> — home">
                <span class="mark"></span>Trust<span class="muted">&nbsp;Wealth</span>
            </a>
            <ul id="nav-links">
                <li><a href="<?= $base ?>index.php#about" class="<?= $active === 'about' ? 'is-active' : '' ?>">About</a></li>
                <li><a href="<?= $base ?>index.php#plans" class="<?= $active === 'plans' ? 'is-active' : '' ?>">Plans</a></li>
                <li><a href="<?= $base ?>index.php#rates" class="<?= $active === 'rates' ? 'is-active' : '' ?>">Rates</a></li>
                <li><a href="<?= $base ?>index.php#contact" class="<?= $active === 'contact' ? 'is-active' : '' ?>">Contact</a></li>
            </ul>
            <div class="nav-actions">
                <?php if ($isAuth) : ?>
                    <a href="<?= $base ?>dashboard.php" class="btn btn-ghost nav-cta">Dashboard</a>
                    <?php if ((int)($user['is_admin'] ?? 0) === 1) : ?>
                        <a href="<?= $base ?>admin/index.php" class="btn btn-ghost nav-cta">Admin</a>
                    <?php endif; ?>
                    <a href="<?= $base ?>logout.php" class="btn btn-gold nav-cta">Log out</a>
                <?php else : ?>
                    <a href="<?= $base ?>login.php" class="btn btn-ghost nav-cta">Log in</a>
                    <a href="<?= $base ?>signup.php" class="btn btn-gold nav-cta">Get started</a>
                    <a href="<?= $base ?>admin/login.php" class="nav-operator" aria-label="Operator login">Operator</a>
                <?php endif; ?>
            </div>
            <button id="menu-btn" aria-label="Toggle menu" aria-expanded="false" aria-controls="nav-links">&#9776;</button>
        </nav>
    </header>