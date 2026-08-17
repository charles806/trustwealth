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

$txStmt = $pdo->prepare('SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 20');
$txStmt->execute([$user['id']]);
$transactions = $txStmt->fetchAll();

$plans = $pdo->query('SELECT * FROM plans ORDER BY sort')->fetchAll();

$totStmt = $pdo->prepare(
    'SELECT
        COALESCE(SUM(CASE WHEN type = "return" AND status = "completed" THEN amount END), 0) AS returns_total,
        COALESCE(SUM(CASE WHEN type = "referral" AND status = "completed" THEN amount END), 0) AS referral_total
     FROM transactions WHERE user_id = ?'
);
$totStmt->execute([$user['id']]);
$tot = $totStmt->fetch();

$planActive = $portfolio !== false && $portfolio['plan_id'] !== null;
$nextTs = 0;
$progressPct = 0;
$elapsedDays = 0;
$nextLabel = '—';

if ($planActive && $portfolio['plan_started_at'] !== null && $portfolio['plan_started_at'] !== '') {
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
    $flash = ['ok', 'Your plan is live. A deposit of ' . fmt_money((float) $portfolio['plan_amount']) . ' is pending confirmation.'];
} elseif (isset($_GET['login'])) {
    $flash = ['ok', 'You are logged in.'];
}

/* Start-a-plan action. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'start_plan') {
    csrf_check();
    $planId = (int) ($_POST['plan_id'] ?? 0);
    $errMsg = null;

    try {
        if ($planActive) {
            $errMsg = 'You already have an active plan.';
        } else {
            $planStmt = $pdo->prepare('SELECT * FROM plans WHERE id = ? LIMIT 1');
            $planStmt->execute([$planId]);
            $plan = $planStmt->fetch();

            if (!$plan) {
                $errMsg = 'That plan does not exist.';
            } else {
                $amount = (float) $plan['min_amount'];
                $btc = $amount / BTC_USD_RATE;

                $pdo->beginTransaction();
                $pdo->prepare(
                    'UPDATE portfolios
                     SET plan_id = ?, plan_amount = ?, plan_started_at = ?,
                         balance_usd = balance_usd + ?, btc_amount = btc_amount + ?
                     WHERE user_id = ?'
                )->execute([$planId, $amount, date('Y-m-d H:i:s'), $amount, $btc, $user['id']]);

                $pdo->prepare(
                    'INSERT INTO transactions (user_id, type, amount, status, note) VALUES (?, "deposit", ?, "pending", ?)'
                )->execute([$user['id'], $amount, $plan['name'] . ' plan deposit']);

                $pdo->commit();
                header('Location: dashboard.php?started=1');
                exit;
            }
        }
    } catch (Throwable $er) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('dashboard start_plan: ' . $er->getMessage());
        $errMsg = 'Could not start the plan. Please try again.';
    }

    if ($errMsg !== null) {
        $flash = ['error', $errMsg];
    }
}

$pageTitle = 'Dashboard';
$bodyClass = 'console-page';
$extraCss = 'console.css';
$active = '';

include __DIR__ . '/partials/header.php';
?>

<div class="console">
    <aside class="rail">
        <span class="rail-brand"><span class="mark"></span>Trust&thinsp;Wealth</span>

        <nav class="rail-nav" aria-label="Dashboard sections">
            <a class="rail-link is-active" href="#overview"><i class="fa-solid fa-gauge"></i>Overview</a>
            <a class="rail-link" href="#plans"><i class="fa-solid fa-layer-group"></i>Plans</a>
            <a class="rail-link" href="#deposit"><i class="fa-solid fa-arrow-down-to-bracket"></i>Deposit</a>
            <a class="rail-link" href="#transactions"><i class="fa-solid fa-receipt"></i>Transactions</a>
        </nav>

        <div class="rail-foot">
            <div class="rail-user">
                <span class="avatar"><?= e(strtoupper(substr($user['fullname'], 0, 1))) ?></span>
                <div class="rail-user-meta">
                    <strong><?= e($user['fullname']) ?></strong>
                    <span>@<?= e($user['username']) ?></span>
                </div>
            </div>
            <a href="logout.php" class="btn btn-ghost rail-logout"><i class="fa-solid fa-arrow-right-from-bracket"></i>Log out</a>
        </div>
    </aside>

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
                    <p class="panel-label">Portfolio value</p>
                    <p class="balance-value"><?= fmt_money((float) $portfolio['balance_usd']) ?></p>
                    <p class="balance-sub">
                        <?= number_format((float) $portfolio['btc_amount'], 6) ?> BTC
                        <span class="dot">·</span>
                        <?php if ($planActive) : ?>
                            <span class="pos">+<?= fmt_money((float) $tot['returns_total']) ?> realized</span>
                        <?php else : ?>
                            no active plan yet
                        <?php endif; ?>
                    </p>
                </div>
                <a href="#deposit" class="btn btn-gold">Deposit</a>
            </div>

            <div class="kpis">
                <div class="panel kpi">
                    <span class="panel-label">Active plan</span>
                    <span class="kpi-val"><?= $planActive ? e($portfolio['plan_name']) : 'None' ?></span>
                    <span class="kpi-sub"><?= $planActive ? e((string) $portfolio['plan_yield']) . '% per ' . (int) $portfolio['period_days'] . ' days' : 'Pick one below' ?></span>
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
                    <span class="panel-label">Days active</span>
                    <span class="kpi-val"><?= days_active($user['created_at']) ?></span>
                    <span class="kpi-sub">Since <?= e(date('M Y', strtotime($user['created_at']))) ?></span>
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
                <?php else : ?>
                    <p class="countdown">--:--:--</p>
                    <p class="countdown-sub">Start a plan to begin the payout clock.</p>
                <?php endif; ?>
            </div>

            <div class="panel convert-panel">
                <p class="panel-label">// Converter</p>
                <p class="panel-price">1 BTC = <span class="gold" id="price-value">$94,500.00</span></p>
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

        <section id="plans">
            <div class="section-title">
                <h2>Your plans</h2>
                <?php if ($planActive) : ?>
                    <p>Your <strong><?= e($portfolio['plan_name']) ?></strong> plan is running. You can stack a new one after it pays out.</p>
                <?php else : ?>
                    <p>Start a plan — your deposit is credited instantly, then the payout clock starts.</p>
                <?php endif; ?>
            </div>

            <?php if ($planActive) : ?>
                <div class="panel active-plan">
                    <div>
                        <p class="panel-label">Running plan</p>
                        <p class="active-plan-name"><?= e($portfolio['plan_name']) ?></p>
                        <p class="kpi-sub"><?= e((string) $portfolio['plan_yield']) ?>% on <?= fmt_money((float) $portfolio['plan_amount']) ?> · <?= (int) $portfolio['period_days'] ?>-day cycle</p>
                    </div>
                    <span class="pill pill-ok"><i class="fa-solid fa-circle-check"></i>Active</span>
                </div>
            <?php else : ?>
                <div class="console-plans">
                    <?php foreach ($plans as $plan) : ?>
                        <form action="dashboard.php" method="POST" class="plan">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="start_plan">
                            <input type="hidden" name="plan_id" value="<?= (int) $plan['id'] ?>">
                            <p class="plan-name"><?= e($plan['name']) ?></p>
                            <p class="plan-range">$<?= number_format((float) $plan['min_amount']) ?> +</p>
                            <p class="plan-yield"><?= e((string) $plan['yield_pct']) ?>% after <?= (int) $plan['period_days'] ?> day<?= (int) $plan['period_days'] === 1 ? '' : 's' ?></p>
                            <button type="submit" class="btn btn-ghost">Start plan</button>
                        </form>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section id="deposit">
            <div class="section-title">
                <h2>Deposit</h2>
                <p>Send your balance in either currency, then come back — deposits appear within minutes.</p>
            </div>

            <div class="deposit-grid">
                <div class="panel address-card">
                    <p class="panel-label"><i class="fa-brands fa-bitcoin"></i> Bitcoin</p>
                    <p class="addr" id="addr-btc">bc1q7wln9r6qw2kxwvrs8t3gr0k9z4hx7tkm</p>
                    <button type="button" class="btn btn-ghost" data-copy="addr-btc"><i class="fa-regular fa-copy"></i>Copy address</button>
                </div>
                <div class="panel address-card">
                    <p class="panel-label"><i class="fa-solid fa-coins"></i> Tether USDT</p>
                    <p class="addr" id="addr-usdt">TXj9yQw3VJzJfTb2HcN5pLk8mQw6Ad3R1c</p>
                    <button type="button" class="btn btn-ghost" data-copy="addr-usdt"><i class="fa-regular fa-copy"></i>Copy address</button>
                </div>
            </div>

            <p class="deposit-note"><i class="fa-solid fa-flask"></i> Demo environment — transfers are simulated and credited to the account instantly.</p>
        </section>

        <section id="transactions">
            <div class="section-title">
                <h2>Transactions</h2>
                <p>Your deposits, returns and referral credits, newest first.</p>
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
                                <tr>
                                    <td class="mono muted"><?= e(fmt_date($tx['created_at'])) ?></td>
                                    <td>
                                        <span class="tx-type tx-<?= e($tx['type']) ?>">
                                            <i class="fa-solid <?= $tx['type'] === 'deposit' ? 'fa-arrow-down-to-bracket' : ($tx['type'] === 'return' ? 'fa-arrow-trend-up' : 'fa-user-plus') ?>"></i>
                                            <?= ucfirst(e($tx['type'])) ?>
                                        </span>
                                    </td>
                                    <td class="muted"><?= e((string) ($tx['note'] ?? '')) ?></td>
                                    <td class="num mono <?= $tx['type'] !== 'deposit' ? 'pos' : '' ?>">
                                        <?= $tx['type'] !== 'deposit' ? '+' : '' ?><?= fmt_money((float) $tx['amount']) ?>
                                    </td>
                                    <td>
                                        <span class="pill <?= $tx['status'] === 'completed' ? 'pill-ok' : 'pill-wait' ?>">
                                            <?= $tx['status'] === 'completed' ? 'Completed' : 'Pending' ?>
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