<?php
declare(strict_types=1);

require __DIR__ . '/app/config.php';
require __DIR__ . '/app/auth.php';
require __DIR__ . '/app/helpers.php';

$user = require_login();

$pdo = db();

$portStmt = $pdo->prepare(
    'SELECT p.*, pl.name AS plan_name, pl.yield_pct AS plan_yield, pl.period_days, pl.min_amount AS plan_min, pl.max_amount AS plan_max
     FROM portfolios p LEFT JOIN plans pl ON pl.id = p.plan_id
     WHERE p.user_id = ? LIMIT 1'
);
$portStmt->execute([$user['id']]);
$portfolio = $portStmt->fetch();

if ($portfolio === false) {
    $pdo->prepare('INSERT INTO portfolios (user_id, balance_usd, btc_amount) VALUES (?, 0, 0)')
        ->execute([$user['id']]);
    $portStmt->execute([$user['id']]);
    $portfolio = $portStmt->fetch();
}

$txStmt = $pdo->prepare('SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 8');
$txStmt->execute([$user['id']]);
$transactions = $txStmt->fetchAll();

$totStmt = $pdo->prepare(
    'SELECT
        COALESCE(SUM(CASE WHEN type = "return" AND status = "completed" THEN amount END), 0) AS returns_total,
        COALESCE(SUM(CASE WHEN type = "referral" AND status = "completed" THEN amount END), 0) AS referral_total,
        COALESCE(SUM(CASE WHEN type = "withdraw" AND status = "completed" THEN amount END), 0) AS withdrawn_total
     FROM transactions WHERE user_id = ?'
);
$totStmt->execute([$user['id']]);
$tot = $totStmt->fetch();

$planActive = $portfolio !== false && $portfolio['plan_id'] !== null && (int) ($portfolio['plan_paid_out'] ?? 0) === 0;
$planDone = $portfolio !== false && $portfolio['plan_id'] !== null && (int) ($portfolio['plan_paid_out'] ?? 0) === 1;
$nextTs = 0;
$progressPct = 0;
$elapsedDays = 0;
$nextLabel = '—';

if (($planActive || $planDone) && $portfolio['plan_started_at'] !== null && $portfolio['plan_started_at'] !== '') {
    $started = strtotime($portfolio['plan_started_at']);
    $period = max(1, (int) $portfolio['period_days']) * 86400;
    $nextTs = $started + $period;
    $remaining = $nextTs - time();
    $elapsed = time() - $started;
    $elapsedDays = max(0, (int) floor($elapsed / 86400));
    $progressPct = (int) max(0, min(100, round((1 - max(0, $remaining) / $period) * 100)));
    $nextLabel = date('M j, H:i', $nextTs);
}

/* Flash banner (querystring-driven, so it survives redirects). */
$flash = null;
if (isset($_GET['started'])) {
    $flash = ['ok', 'Your plan is live. The payout clock has started.'];
} elseif (isset($_GET['login'])) {
    $flash = ['ok', 'You are logged in.'];
}

$pageTitle = 'Dashboard';
$bodyClass = 'console-page';
$extraCss = 'console.css';
$activeNav = 'overview';

include __DIR__ . '/partials/header.php';

$nav = [
    ['dashboard.php', 'fa-gauge', 'Overview', 'overview'],
    ['deposit.php', 'fa-arrow-down-to-bracket', 'Deposit', 'deposit'],
    ['invest.php', 'fa-layer-group', 'Plans', 'invest'],
    ['withdraw.php', 'fa-arrow-up-from-bracket', 'Withdraw', 'withdraw'],
    ['profile.php', 'fa-user', 'Profile', 'profile'],
];
?>

<div class="console">
    <?php include __DIR__ . '/partials/console_rail.php'; ?>

    <main class="console-main" id="main">
        <div class="console-top">
            <p class="eyebrow">Account console</p>
            <span class="ledger-time" id="clock" role="timer" aria-live="off">&#8212;</span>
        </div>

        <?php if ($flash !== null) : ?>
            <p class="flash <?= $flash[0] === 'ok' ? 'flash-ok' : 'flash-err' ?>" role="status">
                <i class="fa-solid <?= $flash[0] === 'ok' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
                <?= e($flash[1]) ?>
            </p>
        <?php endif; ?>

        <section id="overview">
            <div class="panel balance-hero">
                <div>
                    <p class="panel-label">Available balance</p>
                    <p class="balance-value"><?= fmt_money((float) $portfolio['balance_usd']) ?></p>
                    <p class="balance-sub">
                        <?= number_format((float) $portfolio['btc_amount'], 6) ?> BTC
                        <span class="dot">·</span>
                        <?php if ($planActive) : ?>
                            <span class="pos"><?= fmt_money((float) $tot['returns_total']) ?> realized</span>
                        <?php elseif ($planDone) : ?>
                            plan concluded — reinvest below
                        <?php else : ?>
                            nothing deposited yet
                        <?php endif; ?>
                    </p>
                </div>
                <div class="hero-actions">
                    <a href="deposit.php" class="btn btn-ghost">Deposit</a>
                    <a href="withdraw.php" class="btn btn-gold">Withdraw</a>
                </div>
            </div>

            <div class="kpis">
                <div class="panel kpi">
                    <span class="panel-label">Active plan</span>
                    <span class="kpi-val"><?= $planActive ? e($portfolio['plan_name']) : ($planDone ? 'Concluded' : 'None') ?></span>
                    <span class="kpi-sub"><?= $planActive ? e((string) $portfolio['plan_yield']) . '% · ' . (int) $portfolio['period_days'] . ' day cycle' : 'See Plans below' ?></span>
                </div>
                <div class="panel kpi">
                    <span class="panel-label">Total returns</span>
                    <span class="kpi-val pos"><?= fmt_money((float) $tot['returns_total']) ?></span>
                    <span class="kpi-sub">Realized payouts</span>
                </div>
                <div class="panel kpi">
                    <span class="panel-label">Referral bonus</span>
                    <span class="kpi-val pos"><?= fmt_money((float) $tot['referral_total']) ?></span>
                    <span class="kpi-sub">10% per invite</span>
                </div>
                <div class="panel kpi">
                    <span class="panel-label">Withdrawn</span>
                    <span class="kpi-val"><?= fmt_money((float) $tot['withdrawn_total']) ?></span>
                    <span class="kpi-sub">Lifetime payouts out</span>
                </div>
            </div>
        </section>

        <section id="payout" class="c-grid">
            <div class="panel payout-panel">
                <p class="panel-label">// Next payout</p>
                <?php if ($planActive) : ?>
                    <p class="countdown" id="payout-countdown" data-target="<?= (int) $nextTs ?>" role="timer" aria-live="off">--:--:--</p>
                    <p class="countdown-sub">from <strong><?= e($portfolio['plan_name']) ?></strong> on <strong><?= fmt_money((float) $portfolio['plan_amount']) ?></strong></p>
                    <p class="countdown-date">maturing <?= e($nextLabel) ?></p>
                    <div class="progress"><span style="width: <?= (int) $progressPct ?>%"></span></div>
                    <p class="progress-meta"><span><?= (int) $elapsedDays ?> day<?= $elapsedDays === 1 ? '' : 's' ?> running</span><span><?= (int) $progressPct ?>% to payout</span></p>
                <?php elseif ($planDone) : ?>
                    <p class="countdown">00:00:00</p>
                    <p class="countdown-sub">Your <strong><?= e($portfolio['plan_name']) ?></strong> plan has been paid out. Start a new one when you're ready.</p>
                    <a href="invest.php" class="btn btn-gold" style="margin-top: 1.2rem;">Reinvest</a>
                <?php else : ?>
                    <p class="countdown">--:--:--</p>
                    <p class="countdown-sub">Invest from your balance to begin the payout clock.</p>
                    <a href="invest.php" class="btn btn-gold" style="margin-top: 1.2rem;">Start a plan</a>
                <?php endif; ?>
            </div>

            <div class="panel convert-panel">
                <p class="panel-label">// Converter</p>
                <p class="panel-price">1 BTC = <span class="gold" id="price-value">$64316.16</span></p>
                <div class="converter">
                    <div class="field">
                        <input type="number" id="btcAmount" inputmode="decimal" placeholder="0.00" aria-label="Bitcoin amount" min="0" step="any">
                        <span class="unit">BTC</span>
                    </div>
                    <div class="field">
                        <input type="text" id="usdAmount" readonly aria-label="US dollar value">
                        <span class="unit muted">USD</span>
                    </div>
                </div>
                <p class="ledger-note">Live rate at market close. <a href="index.php#rates">Full calculator</a></p>
            </div>
        </section>

        <section id="activity">
            <div class="section-title">
                <h2>Recent activity</h2>
                <p>Deposits, investments, returns and withdrawals, newest first.</p>
            </div>

            <?php if (count($transactions) === 0) : ?>
                <div class="panel empty">
                    <i class="fa-regular fa-receipt"></i>
                    <p>No transactions yet — make your first deposit above.</p>
                </div>
            <?php else : ?>
                <div class="table-wrap">
                    <table class="tx-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Note</th>
                                <th class="num">Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $tx) : ?>
                                <?php
                                $sign = in_array($tx['type'], ['withdraw'], true) ? '-' : '+';
                                $cls = $tx['type'] === 'return' ? 'pos' : ($tx['type'] === 'referral' ? 'gold' : ($tx['type'] === 'withdraw' ? 'neg' : ''));
                                $icon = match ($tx['type']) {
                                    'deposit' => 'fa-arrow-down-to-bracket',
                                    'invest' => 'fa-layer-group',
                                    'return' => 'fa-arrow-trend-up',
                                    'referral' => 'fa-user-plus',
                                    'withdraw' => 'fa-arrow-up-from-bracket',
                                    default => 'fa-receipt',
                                };
                                ?>
                                <tr>
                                    <td class="mono muted"><?= e(fmt_date($tx['created_at'])) ?></td>
                                    <td>
                                        <span class="tx-type tx-<?= e($tx['type']) ?>">
                                            <i class="fa-solid <?= $icon ?>"></i>
                                            <?= ucfirst(e($tx['type'])) ?>
                                        </span>
                                    </td>
                                    <td class="muted"><?= e((string) ($tx['note'] ?? '')) ?></td>
                                    <td class="num mono <?= $cls ?>"><?= $sign ?><?= fmt_money((float) $tx['amount']) ?></td>
                                    <td>
                                        <span class="pill <?= $tx['status'] === 'completed' ? 'pill-ok' : ($tx['status'] === 'cancelled' ? 'pill-cancel' : 'pill-wait') ?>">
                                            <?= ucfirst(e($tx['status'])) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <footer class="console-footer">
            <p>&copy; <?= date('Y') ?> <?= e(APP_NAME) ?> — Account console</p>
            <a href="index.php">Back to site</a>
        </footer>
    </main>
</div>

<script src="main.js"></script>
<script src="dashboard.js"></script>
</body>
</html>