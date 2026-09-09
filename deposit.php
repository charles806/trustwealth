<?php
declare(strict_types=1);

require __DIR__ . '/app/config.php';
require __DIR__ . '/app/auth.php';
require __DIR__ . '/app/helpers.php';

$user = require_login();

$pdo = db();

$btcAddr = setting('btc_deposit_address');
$usdtAddr = setting('usdt_deposit_address');

/* Record a pending deposit — an admin confirms and credits the balance. */
$errMsg = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'deposit') {
    csrf_check();
    $coin = strtoupper(trim((string) ($_POST['coin'] ?? '')));
    $amount = (float) ($_POST['amount'] ?? 0);
    $txid = trim((string) ($_POST['txid'] ?? ''));
    $fromAddr = trim((string) ($_POST['from_address'] ?? ''));

    if (!in_array($coin, ['BTC', 'USDT'], true)) {
        $errMsg = 'Choose Bitcoin or USDT.';
    } elseif ($amount < 50) {
        $errMsg = 'Minimum deposit is $50.';
    } elseif ($amount > 500000) {
        $errMsg = 'Deposit amount is too large.';
    } elseif ($txid === '' || strlen($txid) < 8) {
        $errMsg = 'Please provide the transaction hash so we can match your transfer.';
    } elseif (!valid_deposit_from($fromAddr)) {
        $errMsg = 'Enter the exact wallet address you are sending FROM so we can match your payment.';
    } else {
        $ref = generate_deposit_ref();
        $stmt = $pdo->prepare(
            'INSERT INTO transactions (user_id, type, amount, deposit_from, deposit_ref, status, note)
             VALUES (?, "deposit", ?, ?, ?, "pending", ?)'
        );
        $stmt->execute([
            $user['id'],
            $amount,
            $fromAddr,
            $ref,
            $coin . ' deposit · hash ' . $txid . ' · ref ' . $ref . ' · from ' . $fromAddr,
        ]);

        header('Location: deposit.php?submitted=1');
        exit;
    }
}

$flash = null;
if (isset($_GET['submitted'])) {
    $flash = ['ok', 'Deposit request received. Send exactly that amount from your address, then our team confirms your balance once the payment and reference match.'];
}

$pdoStmt = $pdo->prepare(
    'SELECT * FROM transactions WHERE user_id = ? AND type = "deposit" ORDER BY created_at DESC, id DESC LIMIT 10'
);
$pdoStmt->execute([$user['id']]);
$deposits = $pdoStmt->fetchAll();

$pageTitle = 'Deposit';
$bodyClass = 'console-page';
$extraCss = 'console.css';
$activeNav = 'deposit';

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

        <div class="guide-strip">
            <ol>
                <li>Choose Bitcoin or USDT you already hold.</li>
                <li>Enter the exact address you'll send FROM — it's how we match your payment.</li>
                <li>Send the exact amount to the address shown and paste the transaction hash.</li>
                <li>We approve your balance once the on-chain payment and your reference line up.</li>
            </ol>
        </div>

        <section id="deposit">
            <div class="c-grid c-reverse">
                <div class="panel deposit-form">
                    <p class="panel-label"><i class="fa-solid fa-arrow-down-to-bracket"></i> New deposit</p>
                    <form action="deposit.php" method="POST" id="deposit-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="deposit">

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
                            <input type="number" name="amount" inputmode="decimal" min="10" max="500000" step="any"
                                   placeholder="100.00" required aria-label="Deposit amount in US dollars">
                            <span class="unit">USD</span>
                        </div>

                        <div class="field field-stack">
                            <input type="text" name="txid" inputmode="text" placeholder="Transaction hash (start with the amount you sent)"
                                   required aria-label="Transaction hash" minlength="8">
                        </div>

                        <div class="field field-stack">
                            <input type="text" name="from_address" inputmode="text" placeholder="Your sending address (from-address)"
                                   required aria-label="Your sending wallet address" minlength="25">
                            <p class="ledger-note"><i class="fa-solid fa-user-check"></i> This must be the exact wallet you send from — it proves the payment is yours.</p>
                        </div>

                        <button type="submit" class="btn btn-gold btn-block">Submit for approval</button>
                        <p class="ledger-note"><i class="fa-solid fa-clock"></i> Credits apply once our team matches your transfer.</p>
                    </form>
                </div>

                <div class="board">
                    <div class="panel address-card" data-coin="BTC" style="display:block;">
                        <p class="panel-label"><i class="fa-brands fa-bitcoin"></i> Bitcoin address</p>
                        <p class="addr" id="addr-btc"><?= e($btcAddr) ?></p>
                        <div class="qr" id="qr-btc" aria-label="Bitcoin address QR code"></div>
                        <button type="button" class="btn btn-ghost" data-copy="addr-btc"><i class="fa-regular fa-copy"></i>Copy BTC address</button>
                    </div>
                    <div class="panel address-card" data-coin="USDT" style="display:none;">
                        <p class="panel-label"><i class="fa-solid fa-coins"></i> Tether USDT address</p>
                        <p class="addr" id="addr-usdt"><?= e($usdtAddr) ?></p>
                        <div class="qr" id="qr-usdt" aria-label="Tether USDT address QR code"></div>
                        <button type="button" class="btn btn-ghost" data-copy="addr-usdt"><i class="fa-regular fa-copy"></i>Copy USDT address</button>
                    </div>
                    <p class="deposit-note"><i class="fa-solid fa-shield-halved"></i> Send once, exact amount. Don't reuse a filled address.</p>
                </div>
            </div>
        </section>

        <section id="history">
            <div class="section-title">
                <h2>Deposit history</h2>
                <p>Your submitted deposits, and their confirmation status.</p>
            </div>

            <?php if (count($deposits) === 0) : ?>
                <div class="panel empty">
                    <i class="fa-regular fa-arrow-down-to-bracket"></i>
                    <p>No deposits yet — send funds above to get started.</p>
                </div>
            <?php else : ?>
                <div class="table-wrap">
                    <table class="tx-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Currency</th>
                                <th class="num">Amount</th>
                                <th>Reference</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($deposits as $tx) : ?>
                                <tr>
                                    <td class="mono muted"><?= e(fmt_date($tx['created_at'])) ?></td>
                                    <td class="mono"><?= str_contains((string) $tx['note'], 'USDT') ? 'USDT' : 'BTC' ?></td>
                                    <td class="num mono"><?= fmt_money((float) $tx['amount']) ?></td>
                                    <td class="mono gold"><?= e((string) ($tx['deposit_ref'] ?? '')) ?></td>
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
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
</body>
</html>