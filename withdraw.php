<?php
declare(strict_types=1);

require __DIR__ . '/app/config.php';
require __DIR__ . '/app/auth.php';
require __DIR__ . '/app/helpers.php';

$user = require_login();

$pdo = db();

$portStmt = $pdo->prepare('SELECT * FROM portfolios WHERE user_id = ? LIMIT 1');
$portStmt->execute([$user['id']]);
$portfolio = $portStmt->fetch();

$errMsg = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'withdraw') {
    csrf_check();
    $amount = (float) ($_POST['amount'] ?? 0);
    $address = trim((string) ($_POST['address'] ?? ''));
    $coin = strtoupper(trim((string) ($_POST['coin'] ?? '')));

    try {
        if (!in_array($coin, ['BTC', 'USDT'], true)) {
            $errMsg = 'Withdrawals go out as Bitcoin or USDT.';
        } elseif ($amount < 10) {
            $errMsg = 'Minimum withdrawal is $10.';
        } elseif ($portfolio === false || $amount > (float) $portfolio['balance_usd']) {
            $errMsg = 'Insufficient balance.';
        } elseif ($address === '' || strlen($address) < 8) {
            $errMsg = 'Enter a valid wallet address.';
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO transactions (user_id, type, amount, status, note) VALUES (?, "withdraw", ?, "pending", ?)'
            );
            $stmt->execute([$user['id'], $amount, $coin . ' withdrawal to ' . $address]);

            $pdo->prepare('UPDATE users SET withdrawal_address = ? WHERE id = ?')
                ->execute([$address, $user['id']]);

            header('Location: withdraw.php?submitted=1');
            exit;
        }
    } catch (Throwable $er) {
        error_log('withdraw request: ' . $er->getMessage());
        $errMsg = 'Could not submit the withdrawal. Try again.';
    }
}

$flash = null;
if (isset($_GET['submitted'])) {
    $flash = ['ok', 'Withdrawal request submitted. Our team reviews it against your available balance before payout.'];
}

$wdStmt = $pdo->prepare('SELECT * FROM transactions WHERE user_id = ? AND type = "withdraw" ORDER BY created_at DESC, id DESC LIMIT 10');
$wdStmt->execute([$user['id']]);
$withdrawals = $wdStmt->fetchAll();

$savedAddress = (string) ($user['withdrawal_address'] ?? '');

$pageTitle = 'Withdraw';
$bodyClass = 'console-page';
$extraCss = 'console.css';
$activeNav = 'withdraw';

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
        <?php elseif ($errMsg !== null) : ?>
            <p class="flash flash-err" role="alert"><i class="fa-solid fa-circle-exclamation"></i><?= e($errMsg) ?></p>
        <?php endif; ?>

        <section id="withdraw">
            <div class="c-grid">
                <div class="panel deposit-form">
                    <p class="panel-label"><i class="fa-solid fa-arrow-up-from-bracket"></i> Withdraw balance</p>

                    <p class="kpi-sub" style="margin-bottom:1.2rem;">
                        Available to withdraw: <strong class="pos" style="font-size:1.1rem;"><?= fmt_money((float) ($portfolio['balance_usd'] ?? 0)) ?></strong>
                    </p>

                    <form action="withdraw.php" method="POST" id="withdraw-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="withdraw">

                        <div class="coin-toggle" role="radiogroup" aria-label="Currency">
                            <label>
                                <input type="radio" name="coin" value="BTC" checked>
                                <span class="coin-opt active"><i class="fa-brands fa-bitcoin"></i>BTC</span>
                            </label>
                            <label>
                                <input type="radio" name="coin" value="USDT">
                                <span class="coin-opt"><i class="fa-solid fa-coins"></i>USDT</span>
                            </label>
                        </div>

                        <div class="field">
                            <input type="number" name="amount" inputmode="decimal" min="10" step="any"
                                   max="<?= (float) ($portfolio['balance_usd'] ?? 0) ?>"
                                   placeholder="100.00" required aria-label="Withdrawal amount in US dollars">
                            <span class="unit">USD</span>
                        </div>

                        <div class="field field-stack">
                            <label class="visually-hidden" for="address">Wallet address</label>
                            <input type="text" id="address" name="address" placeholder="Bitcoin / USDT address"
                                   value="<?= e($savedAddress) ?>" required aria-label="Wallet address" minlength="8">
                        </div>

                        <button type="submit" class="btn btn-gold btn-block">Request withdrawal</button>
                        <p class="ledger-note"><i class="fa-solid fa-clock"></i> Withdrawals are reviewed before payout.</p>
                    </form>
                </div>

                <div class="panel payout-panel">
                    <p class="panel-label">// Before you withdraw</p>
                    <ul class="check-list">
                        <li><i class="fa-solid fa-circle-check"></i>Double-check the address — crypto cannot be recovered.</li>
                        <li><i class="fa-solid fa-circle-check"></i>Funds are sent only after your request is approved.</li>
                        <li><i class="fa-solid fa-circle-check"></i>Active plan amounts stay locked until the plan pays out.</li>
                    </ul>
                    <p class="ledger-note" style="margin-top:1rem;">Your saved address on file: <span class="gold mono"><?= e($savedAddress !== '' ? $savedAddress : 'none yet') ?></span></p>
                </div>
            </div>
        </section>

        <section id="history">
            <div class="section-title">
                <h2>Withdrawal history</h2>
                <p>Every payout request, newest first.</p>
            </div>

            <?php if (count($withdrawals) === 0) : ?>
                <div class="panel empty">
                    <i class="fa-regular fa-arrow-up-from-bracket"></i>
                    <p>No withdrawals yet.</p>
                </div>
            <?php else : ?>
                <div class="table-wrap">
                    <table class="tx-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th class="num">Amount</th>
                                <th>Note</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($withdrawals as $tx) : ?>
                                <tr>
                                    <td class="mono muted"><?= e(fmt_date($tx['created_at'])) ?></td>
                                    <td class="num mono">-<?= fmt_money((float) $tx['amount']) ?></td>
                                    <td class="muted"><?= e((string) ($tx['note'] ?? '')) ?></td>
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