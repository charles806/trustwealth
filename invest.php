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

$planActive = $portfolio !== false && $portfolio['plan_id'] !== null && (int) ($portfolio['plan_paid_out'] ?? 0) === 0;
$planDone = $portfolio !== false && $portfolio['plan_id'] !== null && (int) ($portfolio['plan_paid_out'] ?? 0) === 1;

$plans = $pdo->query('SELECT * FROM plans ORDER BY sort')->fetchAll();

/* Invest-from-balance action. */
$errMsg = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'start_plan') {
    csrf_check();
    $planId = (int) ($_POST['plan_id'] ?? 0);
    $amount = (float) ($_POST['amount'] ?? 0);

    try {
        if ($planActive) {
            $errMsg = 'You already have an active plan. Reinvest once it pays out.';
        } else {
            $planStmt = $pdo->prepare('SELECT * FROM plans WHERE id = ? LIMIT 1');
            $planStmt->execute([$planId]);
            $plan = $planStmt->fetch();

            if (!$plan) {
                $errMsg = 'That plan does not exist.';
            } elseif ($amount < (float) $plan['min_amount'] || $amount > (float) $plan['max_amount']) {
                $errMsg = 'Amount must be between $' . number_format((float) $plan['min_amount']) . ' and $' . number_format((float) $plan['max_amount']) . ' for this plan.';
            } elseif ($amount > (float) $portfolio['balance_usd']) {
                $errMsg = 'Not enough available balance — deposit first or lower the amount.';
            } else {
                $btc = $amount / btc_rate();

                $pdo->beginTransaction();
                $pdo->prepare(
                    'UPDATE portfolios
                     SET plan_id = ?, plan_amount = ?, plan_started_at = ?, plan_paid_out = 0,
                         balance_usd = balance_usd - ?, btc_amount = btc_amount - ?
                     WHERE user_id = ?'
                )->execute([$planId, $amount, date('Y-m-d H:i:s'), $amount, $btc, $user['id']]);

                $pdo->prepare(
                    'INSERT INTO transactions (user_id, type, amount, status, note) VALUES (?, "invest", ?, "completed", ?)'
                )->execute([$user['id'], $amount, 'Invested in ' . $plan['name'] . ' plan']);

                $pdo->commit();
                header('Location: dashboard.php?started=1');
                exit;
            }
        }
    } catch (Throwable $er) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('invest start_plan: ' . $er->getMessage());
        $errMsg = 'Could not start the plan. Please try again.';
    }
}

$pageTitle = 'Plans';
$bodyClass = 'console-page';
$extraCss = 'console.css';
$activeNav = 'invest';

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

        <?php if ($errMsg !== null) : ?>
            <p class="flash flash-err" role="alert"><i class="fa-solid fa-circle-exclamation"></i><?= e($errMsg) ?></p>
        <?php endif; ?>

        <section id="plans">
            <div class="section-title">
                <h2>Your plans</h2>
                <p>Choose a plan and invest from your balance. One plan runs at a time.</p>
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
                <p class="ledger-note" style="margin-top: 1rem;">This plan locks <?= fmt_money((float) $portfolio['plan_amount']) ?>. Your returns are credited when it completes.</p>
            <?php elseif ($planDone) : ?>
                <div class="panel active-plan">
                    <div>
                        <p class="panel-label">Previous plan</p>
                        <p class="active-plan-name"><?= e($portfolio['plan_name']) ?></p>
                        <p class="kpi-sub">Concluded — reinvest from your balance to start the clock again.</p>
                    </div>
                    <span class="pill pill-cancel"><i class="fa-solid fa-circle-check"></i>Paid out</span>
                </div>
            <?php endif; ?>

            <div class="console-plans invest-plans">
                <?php foreach ($plans as $plan) : ?>
                    <form action="invest.php" method="POST" class="plan" data-plan-id="<?= (int) $plan['id'] ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="start_plan">
                        <input type="hidden" name="plan_id" value="<?= (int) $plan['id'] ?>">
                        <p class="plan-name"><?= e($plan['name']) ?></p>
                        <p class="plan-range">$<?= number_format((float) $plan['min_amount']) ?> – $<?= number_format((float) $plan['max_amount']) ?></p>
                        <p class="plan-yield"><?= e((string) $plan['yield_pct']) ?>% after <?= (int) $plan['period_days'] ?> day<?= (int) $plan['period_days'] === 1 ? '' : 's' ?></p>
                        <div class="field">
                            <input type="number" name="amount" inputmode="decimal" min="<?= (float) $plan['min_amount'] ?>"
                                   max="<?= (float) $plan['max_amount'] ?>" step="any"
                                   placeholder="<?= number_format((float) $plan['min_amount']) ?>" required
                                   aria-label="Investment amount for <?= e($plan['name']) ?>" class="plan-amount">
                            <span class="unit">USD</span>
                        </div>
                        <button type="submit" class="btn btn-gold" <?= $planActive ? 'disabled' : '' ?>>
                            <?= $planActive ? 'Active plan running' : 'Invest' ?>
                        </button>
                    </form>
                <?php endforeach; ?>
            </div>
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