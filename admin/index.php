<?php
declare(strict_types=1);

require __DIR__ . '/../app/config.php';
require __DIR__ . '/../app/auth.php';
require __DIR__ . '/../app/helpers.php';

$user = require_admin();

$pdo = db();

$tab = (string) ($_GET['tab'] ?? 'overview');
$valid = ['overview', 'deposits', 'withdrawals', 'returns', 'users', 'settings'];
if (!in_array($tab, $valid, true)) {
    $tab = 'overview';
}

$flash = null;

/* ---- credit balance to a user (used by deposits + returns) ---- */
function credit_user(PDO $pdo, int $userId, float $amount): void
{
    $pdo->prepare(
        'UPDATE portfolios SET balance_usd = balance_usd + ?, btc_amount = btc_amount + ?
         WHERE user_id = ?'
    )->execute([$amount, $amount / BTC_USD_RATE, $userId]);
}

/* ---- actions -------------------------------------------------- */
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        $action = (string) ($_POST['action'] ?? '');

        switch ($action) {
            case 'approve_deposit':
                $id = (int) ($_POST['id'] ?? 0);
                $stmt = $pdo->prepare(
                    'SELECT * FROM transactions WHERE id = ? AND type = "deposit" AND status = "pending" LIMIT 1'
                );
                $stmt->execute([$id]);
                $tx = $stmt->fetch();

                if ($tx) {
                    $pdo->beginTransaction();
                    credit_user($pdo, (int) $tx['user_id'], (float) $tx['amount']);
                    $pdo->prepare(
                        "UPDATE transactions SET status = 'completed', note = CONCAT(note, ' · confirmed') WHERE id = ?"
                    )->execute([$id]);
                    $pdo->commit();
                    $flash = ['ok', 'Deposit confirmed and balance credited.'];
                }
                break;

            case 'reject_deposit':
                $id = (int) ($_POST['id'] ?? 0);
                $pdo->prepare(
                    "UPDATE transactions SET status = 'cancelled', note = CONCAT(note, ' · rejected') WHERE id = ? AND type = 'deposit' AND status = 'pending'"
                )->execute([$id]);
                $flash = ['ok', 'Deposit request cancelled.'];
                break;

            case 'approve_withdraw':
                $id = (int) ($_POST['id'] ?? 0);
                $stmt = $pdo->prepare(
                    'SELECT * FROM transactions WHERE id = ? AND type = "withdraw" AND status = "pending" LIMIT 1'
                );
                $stmt->execute([$id]);
                $tx = $stmt->fetch();

                if ($tx) {
                    $balance = $pdo->prepare('SELECT balance_usd FROM portfolios WHERE user_id = ? LIMIT 1');
                    $balance->execute([$tx['user_id']]);
                    $avail = (float) $balance->fetchColumn();

                    if ($avail < (float) $tx['amount']) {
                        $flash = ['error', 'Insufficient balance for that withdrawal — reject it instead.'];
                    } else {
                        $pdo->beginTransaction();
                        $pdo->prepare(
                            'UPDATE portfolios SET balance_usd = balance_usd - ?, btc_amount = btc_amount - ?
                             WHERE user_id = ?'
                        )->execute([$tx['amount'], $tx['amount'] / BTC_USD_RATE, $tx['user_id']]);
                        $pdo->prepare(
                            "UPDATE transactions SET status = 'completed', note = CONCAT(note, ' · sent') WHERE id = ?"
                        )->execute([$id]);
                        $pdo->commit();
                        $flash = ['ok', 'Withdrawal approved and balance debited.'];
                    }
                }
                break;

            case 'reject_withdraw':
                $id = (int) ($_POST['id'] ?? 0);
                $pdo->prepare(
                    "UPDATE transactions SET status = 'cancelled', note = CONCAT(note, ' · rejected') WHERE id = ? AND type = 'withdraw' AND status = 'pending'"
                )->execute([$id]);
                $flash = ['ok', 'Withdrawal request cancelled.'];
                break;

            case 'credit_return':
                $userId = (int) ($_POST['user_id'] ?? 0);
                $amount = (float) ($_POST['amount'] ?? 0);
                $note = trim((string) ($_POST['note'] ?? ''));

                if ($amount <= 0) {
                    $flash = ['error', 'Return amount must be positive.'];
                } else {
                    $pdo->beginTransaction();
                    credit_user($pdo, $userId, $amount);
                    $pdo->prepare(
                        'INSERT INTO transactions (user_id, type, amount, status, note) VALUES (?, "return", ?, "completed", ?)'
                    )->execute([$userId, $amount, $note !== '' ? $note : 'Manual return credit']);
                    $pdo->prepare("UPDATE portfolios SET plan_paid_out = 1 WHERE user_id = ? AND plan_id IS NOT NULL")
                        ->execute([$userId]);
                    $pdo->commit();
                    $flash = ['ok', 'Return credited and plan marked paid out.'];
                }
                break;

            case 'save_settings':
                set_setting('btc_deposit_address', trim((string) ($_POST['btc_deposit_address'] ?? '')));
                set_setting('usdt_deposit_address', trim((string) ($_POST['usdt_deposit_address'] ?? '')));
                $flash = ['ok', 'Deposit addresses updated.'];
                break;

            case 'toggle_admin':
                $userId = (int) ($_POST['user_id'] ?? 0);
                if ($userId !== (int) $user['id']) {
                    $pdo->prepare(
                        'UPDATE users SET is_admin = 1 - is_admin WHERE id = ?'
                    )->execute([$userId]);
                    $flash = ['ok', 'Admin role toggled.'];
                } else {
                    $flash = ['error', 'You can\u2019t revoke your own admin role.'];
                }
                break;
        }

        header('Location: index.php?tab=' . urlencode($tab));
        exit;
    }
} catch (Throwable $er) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('admin action: ' . $er->getMessage());
    $flash = ['error', 'That action failed. Check the log.'];
}

/* ---- data per tab ---------------------------------------------- */
$pendingDeposits = $pdo->query("SELECT t.*, u.fullname, u.email FROM transactions t JOIN users u ON u.id = t.user_id WHERE t.type = 'deposit' AND t.status = 'pending' ORDER BY t.created_at DESC")->fetchAll();
$pendingWithdrawals = $pdo->query("SELECT t.*, u.fullname, u.email FROM transactions t JOIN users u ON u.id = t.user_id WHERE t.type = 'withdraw' AND t.status = 'pending' ORDER BY t.created_at DESC")->fetchAll();

$users = $pdo->query(
    'SELECT u.id, u.fullname, u.username, u.email, u.withdrawal_address, u.is_admin, u.created_at,
            p.balance_usd, pl.name AS plan_name
     FROM users u
     LEFT JOIN portfolios p ON p.user_id = u.id
     LEFT JOIN plans pl ON pl.id = p.plan_id
     ORDER BY u.is_admin DESC, u.created_at DESC'
)->fetchAll();

$portCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$totDeposited = (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type = 'deposit' AND status = 'completed'")->fetchColumn();
$totReturns = (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type = 'return' AND status = 'completed'")->fetchColumn();
$totCurrBal = (float) $pdo->query('SELECT COALESCE(SUM(balance_usd),0) FROM portfolios')->fetchColumn();

$pageTitle = 'Admin';
$bodyClass = 'console-page';
$extraCss = 'console.css';
$activeNav = 'admin';

include __DIR__ . '/../partials/header.php';

$nav = [
    ['dashboard.php', 'fa-gauge', 'Overview', 'overview'],
    ['deposit.php', 'fa-arrow-down-to-bracket', 'Deposit', 'deposit'],
    ['invest.php', 'fa-layer-group', 'Plans', 'invest'],
    ['withdraw.php', 'fa-arrow-up-from-bracket', 'Withdraw', 'withdraw'],
    ['profile.php', 'fa-user', 'Profile', 'profile'],
];
?>

<div class="console">
    <?php include __DIR__ . '/../partials/console_rail.php'; ?>

    <main class="console-main" id="main">
        <div class="console-top">
            <p class="eyebrow">Operator console</p>
            <span class="ledger-time" id="clock" role="timer" aria-live="off">&#8212;</span>
        </div>

        <?php if ($flash !== null) : ?>
            <p class="flash <?= $flash[0] === 'ok' ? 'flash-ok' : 'flash-err' ?>" role="status">
                <i class="fa-solid <?= $flash[0] === 'ok' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
                <?= e($flash[1]) ?>
            </p>
        <?php endif; ?>

        <div class="tab-bar" role="navigation" aria-label="Admin sections">
            <?php foreach ([
                'overview' => ['fa-gauge', 'Overview'],
                'deposits' => ['fa-arrow-down-to-bracket', 'Deposits'],
                'withdrawals' => ['fa-arrow-up-from-bracket', 'Withdrawals'],
                'returns' => ['fa-arrow-trend-up', 'Returns'],
                'users' => ['fa-users', 'Users'],
                'settings' => ['fa-gear', 'Settings'],
            ] as $slug => [$icon, $label]) : ?>
                <a class="tab<?= $tab === $slug ? ' is-active' : '' ?>" href="index.php?tab=<?= urlencode($slug) ?>">
                    <i class="fa-solid <?= $icon ?>"></i><?= $label ?>
                    <?php if ($slug === 'deposits' && count($pendingDeposits) > 0) : ?>
                        <span class="badge"><?= count($pendingDeposits) ?></span>
                    <?php elseif ($slug === 'withdrawals' && count($pendingWithdrawals) > 0) : ?>
                        <span class="badge"><?= count($pendingWithdrawals) ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if ($tab === 'overview') : ?>
            <div class="kpis">
                <div class="panel kpi">
                    <span class="panel-label">Users</span>
                    <span class="kpi-val"><?= $portCount ?></span>
                    <span class="kpi-sub">registered accounts</span>
                </div>
                <div class="panel kpi">
                    <span class="panel-label">Deposits in</span>
                    <span class="kpi-val pos"><?= fmt_money($totDeposited) ?></span>
                    <span class="kpi-sub">confirmed lifetime</span>
                </div>
                <div class="panel kpi">
                    <span class="panel-label">Returns out</span>
                    <span class="kpi-val pos"><?= fmt_money($totReturns) ?></span>
                    <span class="kpi-sub">credited lifetime</span>
                </div>
                <div class="panel kpi">
                    <span class="panel-label">Current ledger</span>
                    <span class="kpi-val gold"><?= fmt_money($totCurrBal) ?></span>
                    <span class="kpi-sub">sum of user balances</span>
                </div>
            </div>

            <div class="c-grid" style="margin-top:1.3rem;">
                <div class="panel">
                    <p class="panel-label">// Pending deposits</p>
                    <?php if (count($pendingDeposits) === 0) : ?>
                        <p class="kpi-sub">Nothing waiting.</p>
                    <?php else : ?>
                        <p class="countdown-sub"><strong><?= count($pendingDeposits) ?></strong> deposit<?= count($pendingDeposits) === 1 ? '' : 's' ?> awaiting confirmation.</p>
                        <a href="index.php?tab=deposits" class="btn btn-gold" style="margin-top:1.1rem;">Review & approve</a>
                    <?php endif; ?>
                </div>
                <div class="panel">
                    <p class="panel-label">// Pending withdrawals</p>
                    <?php if (count($pendingWithdrawals) === 0) : ?>
                        <p class="kpi-sub">Nothing waiting.</p>
                    <?php else : ?>
                        <p class="countdown-sub"><strong><?= count($pendingWithdrawals) ?></strong> withdrawal<?= count($pendingWithdrawals) === 1 ? '' : 's' ?> awaiting approval.</p>
                        <a href="index.php?tab=withdrawals" class="btn btn-gold" style="margin-top:1.1rem;">Review & approve</a>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($tab === 'deposits') : ?>
            <div class="section-title">
                <h2>Deposits</h2>
                <p>Confirm deposits once the client wallet matches the sent amount.</p>
            </div>
            <?php if (count($pendingDeposits) === 0) : ?>
                <div class="panel empty"><i class="fa-regular fa-receipt"></i><p>No pending deposits.</p></div>
            <?php else : ?>
                <div class="table-wrap">
                    <table class="tx-table">
                        <thead><tr><th>Date</th><th>User</th><th>Amount</th><th>From-address</th><th>Reference</th><th>Review</th></tr></thead>
                        <tbody>
                            <?php foreach ($pendingDeposits as $tx) : ?>
                                <tr>
                                    <td class="mono muted"><?= e(fmt_date($tx['created_at'])) ?></td>
                                    <td><strong><?= e($tx['fullname']) ?></strong><br><span class="muted mono"><?= e($tx['email']) ?></span></td>
                                    <td class="num mono pos">+<?= fmt_money((float) $tx['amount']) ?></td>
                                    <td class="mono muted"><?= e((string) ($tx['deposit_from'] ?? '')) ?></td>
                                    <td>
                                        <span class="mono gold ref-pill" id="ref-<?= (int) $tx['id'] ?>"><?= e((string) ($tx['deposit_ref'] ?? '')) ?></span>
                                        <button type="button" class="btn btn-ghost btn-sm" data-copy-ref="ref-<?= (int) $tx['id'] ?>"><i class="fa-regular fa-copy"></i>Copy</button>
                                    </td>
                                    <td>
                                        <div class="row-actions">
                                            <form action="index.php?tab=deposits" method="POST">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="approve_deposit">
                                                <input type="hidden" name="id" value="<?= (int) $tx['id'] ?>">
                                                <button class="btn btn-gold btn-sm" type="submit">Confirm</button>
                                            </form>
                                            <form action="index.php?tab=deposits" method="POST">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="reject_deposit">
                                                <input type="hidden" name="id" value="<?= (int) $tx['id'] ?>">
                                                <button class="btn btn-ghost btn-sm" type="submit">Cancel</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p class="ledger-note"><i class="fa-solid fa-shield-halved"></i> Before confirming, match the on-chain payment's <strong>sending address</strong> and <strong>memo/reference</strong> to the from-address and reference above. Ask the client for their reference if it isn't in the memo.</p>
                </div>
            <?php endif; ?>

        <?php elseif ($tab === 'withdrawals') : ?>
            <div class="section-title">
                <h2>Withdrawals</h2>
                <p>Approve payouts — the amount is debited from the user's balance.</p>
            </div>
            <?php if (count($pendingWithdrawals) === 0) : ?>
                <div class="panel empty"><i class="fa-regular fa-receipt"></i><p>No pending withdrawals.</p></div>
            <?php else : ?>
                <div class="table-wrap">
                    <table class="tx-table">
                        <thead><tr><th>Date</th><th>User</th><th>Amount</th><th>Note</th><th>Review</th></tr></thead>
                        <tbody>
                            <?php foreach ($pendingWithdrawals as $tx) : ?>
                                <tr>
                                    <td class="mono muted"><?= e(fmt_date($tx['created_at'])) ?></td>
                                    <td><strong><?= e($tx['fullname']) ?></strong><br><span class="muted mono"><?= e($tx['email']) ?></span></td>
                                    <td class="num mono neg">-<?= fmt_money((float) $tx['amount']) ?></td>
                                    <td class="muted"><?= e((string) ($tx['note'] ?? '')) ?></td>
                                    <td>
                                        <div class="row-actions">
                                            <form action="index.php?tab=withdrawals" method="POST">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="approve_withdraw">
                                                <input type="hidden" name="id" value="<?= (int) $tx['id'] ?>">
                                                <button class="btn btn-gold btn-sm" type="submit">Approve</button>
                                            </form>
                                            <form action="index.php?tab=withdrawals" method="POST">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="reject_withdraw">
                                                <input type="hidden" name="id" value="<?= (int) $tx['id'] ?>">
                                                <button class="btn btn-ghost btn-sm" type="submit">Reject</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

        <?php elseif ($tab === 'returns') : ?>
            <div class="section-title">
                <h2>Returns</h2>
                <p>Manually credit a payout to a user's balance — also marks their active plan as paid out.</p>
            </div>
            <div class="panel deposit-form">
                <form action="index.php?tab=returns" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="credit_return">
                    <div class="field">
                        <label class="visually-hidden" for="return-user">User</label>
                        <select id="return-user" name="user_id" required>
                            <option value="" disabled selected>Select user…</option>
                            <?php foreach ($users as $u) : ?>
                                <option value="<?= (int) $u['id'] ?>">
                                    <?= e($u['fullname']) ?> — <?= fmt_money((float) $u['balance_usd']) ?>
                                    <?= $u['plan_name'] ? ' · ' . e($u['plan_name']) . ' plan' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label class="visually-hidden" for="return-amount">Amount</label>
                        <input type="number" id="return-amount" name="amount" min="0.01" step="any" placeholder="Return amount (USD)" required>
                    </div>
                    <div class="field field-stack">
                        <label class="visually-hidden" for="return-note">Note</label>
                        <input type="text" id="return-note" name="note" placeholder="Note (optional)">
                    </div>
                    <button type="submit" class="btn btn-gold">Credit return</button>
                </form>
            </div>

        <?php elseif ($tab === 'users') : ?>
            <div class="section-title">
                <h2>Users</h2>
                <p>Every account and its current ledger.</p>
            </div>
            <div class="table-wrap">
                <table class="tx-table">
                    <thead><tr><th>User</th><th class="num">Balance</th><th>Plan</th><th>Withdrawal addr</th><th>Access</th></tr></thead>
                    <tbody>
                        <?php foreach ($users as $u) : ?>
                            <tr>
                                <td>
                                    <strong><?= e($u['fullname']) ?></strong><br>
                                    <span class="muted mono">@<?= e($u['username']) ?> · <?= e($u['email']) ?></span>
                                </td>
                                <td class="num mono"><?= fmt_money((float) $u['balance_usd']) ?></td>
                                <td class="muted"><?= $u['plan_name'] ? e($u['plan_name']) : '—' ?></td>
                                <td class="mono muted" style="max-width:180px;word-break:break-all;"><?= $u['withdrawal_address'] ? e($u['withdrawal_address']) : '—' ?></td>
                                <td>
                                    <span class="pill <?= (int) $u['is_admin'] === 1 ? 'pill-ok' : 'pill-wait' ?>">
                                        <?= (int) $u['is_admin'] === 1 ? 'Admin' : 'Member' ?>
                                    </span>
                                    <?php if ((int) $u['id'] !== (int) $user['id']) : ?>
                                        <form action="index.php?tab=users" method="POST" style="display:inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="toggle_admin">
                                            <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                            <button class="btn btn-ghost btn-sm" type="submit">Toggle</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($tab === 'settings') : ?>
            <div class="section-title">
                <h2>Settings</h2>
                <p>Deposit wallet addresses shown to users on the deposit page.</p>
            </div>
            <div class="panel deposit-form">
                <form action="index.php?tab=settings" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save_settings">
                    <div class="field field-stack">
                        <label class="panel-label" for="btc-addr">Bitcoin address</label>
                        <input type="text" id="btc-addr" name="btc_deposit_address" value="<?= e(setting('btc_deposit_address')) ?>" required>
                    </div>
                    <div class="field field-stack">
                        <label class="panel-label" for="usdt-addr">Tether USDT address</label>
                        <input type="text" id="usdt-addr" name="usdt_deposit_address" value="<?= e(setting('usdt_deposit_address')) ?>" required>
                    </div>
                    <button type="submit" class="btn btn-gold">Save addresses</button>
                </form>
            </div>
        <?php endif; ?>

        <footer class="console-footer">
            <p>&copy; <?= date('Y') ?> <?= e(APP_NAME) ?> — Operator console</p>
            <a href="<?= $base ?>index.php">Back to site</a>
        </footer>
    </main>
</div>

<script src="<?= $base ?>main.js"></script>
<script src="<?= $base ?>dashboard.js"></script>
</body>
</html>