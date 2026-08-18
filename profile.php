<?php
declare(strict_types=1);

require __DIR__ . '/app/config.php';
require __DIR__ . '/app/auth.php';
require __DIR__ . '/app/helpers.php';

$user = require_login();

$pdo = db();

$errMsg = null;
$flash = null;

/* Update profile. */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'update_profile') {
        $fullname = trim((string) ($_POST['fullname'] ?? ''));
        $username = strtolower(trim((string) ($_POST['username'] ?? '')));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $withdrawalAddress = trim((string) ($_POST['withdrawal_address'] ?? ''));

        if ($fullname === '' || $username === '' || $email === '') {
            $errMsg = 'Name, username and email are required.';
        } elseif (!preg_match('/^[a-z0-9_]{3,20}$/', $username)) {
            $errMsg = 'Username must be 3–20 lowercase letters, digits or underscores.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errMsg = 'Enter a valid email address.';
        } else {
            $dup = $pdo->prepare('SELECT id FROM users WHERE (username = ? OR email = ?) AND id <> ? LIMIT 1');
            $dup->execute([$username, $email, $user['id']]);

            if ($dup->fetch()) {
                $errMsg = 'That username or email is already taken.';
            } else {
                try {
                    $pdo->prepare(
                        'UPDATE users SET fullname = ?, username = ?, email = ?, withdrawal_address = ? WHERE id = ?'
                    )->execute([$fullname, $username, $email, $withdrawalAddress !== '' ? $withdrawalAddress : null, $user['id']]);
                    $_SESSION['user']['fullname'] = $fullname;
                    $_SESSION['user']['username'] = $username;
                    header('Location: profile.php?saved=1');
                    exit;
                } catch (Throwable $er) {
                    error_log('profile update: ' . $er->getMessage());
                    $errMsg = 'Could not save your profile.';
                }
            }
        }
    } elseif ($action === 'change_password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $newPass = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');

        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$user['id']]);
        $hash = (string) $stmt->fetchColumn();

        if (!password_verify($current, $hash)) {
            $errMsg = 'Current password is incorrect.';
        } elseif (strlen($newPass) < 8) {
            $errMsg = 'New password must be at least 8 characters.';
        } elseif ($newPass !== $confirm) {
            $errMsg = 'New passwords do not match.';
        } else {
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($newPass, PASSWORD_DEFAULT), $user['id']]);
            header('Location: profile.php?pass=1');
            exit;
        }
    }
}

if (isset($_GET['saved'])) {
    $flash = ['ok', 'Profile updated.'];
} elseif (isset($_GET['pass'])) {
    $flash = ['ok', 'Password changed.'];
}

$pageTitle = 'Profile';
$bodyClass = 'console-page';
$extraCss = 'console.css';
$activeNav = 'profile';

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

        <section id="profile">
            <div class="section-title">
                <h2>Profile & settings</h2>
                <p>Your account details, withdrawal address and password.</p>
            </div>

            <div class="c-grid">
                <div class="panel deposit-form">
                    <p class="panel-label"><i class="fa-solid fa-user"></i> Account details</p>
                    <form action="profile.php" method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update_profile">

                        <div class="field field-stack">
                            <label class="panel-label" for="f-fullname">Full name</label>
                            <input type="text" id="f-fullname" name="fullname" value="<?= e($user['fullname']) ?>" required>
                        </div>
                        <div class="field field-stack">
                            <label class="panel-label" for="f-username">Username</label>
                            <input type="text" id="f-username" name="username" value="<?= e($user['username']) ?>" required>
                        </div>
                        <div class="field field-stack">
                            <label class="panel-label" for="f-email">Email</label>
                            <input type="email" id="f-email" name="email" value="<?= e($user['email']) ?>" required>
                        </div>
                        <div class="field field-stack">
                            <label class="panel-label" for="f-address">Withdrawal address (BTC / USDT)</label>
                            <input type="text" id="f-address" name="withdrawal_address" value="<?= e((string) ($user['withdrawal_address'] ?? '')) ?>" placeholder="Where withdrawals are sent">
                        </div>

                        <button type="submit" class="btn btn-gold">Save profile</button>
                    </form>
                </div>

                <div class="panel deposit-form">
                    <p class="panel-label"><i class="fa-solid fa-key"></i> Change password</p>
                    <form action="profile.php" method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="change_password">

                        <div class="field field-stack">
                            <label class="panel-label" for="f-current">Current password</label>
                            <input type="password" id="f-current" name="current_password" autocomplete="current-password" required>
                        </div>
                        <div class="field field-stack">
                            <label class="panel-label" for="f-new">New password</label>
                            <input type="password" id="f-new" name="new_password" autocomplete="new-password" minlength="8" required>
                        </div>
                        <div class="field field-stack">
                            <label class="panel-label" for="f-confirm">Confirm new password</label>
                            <input type="password" id="f-confirm" name="confirm_password" autocomplete="new-password" minlength="8" required>
                        </div>

                        <button type="submit" class="btn btn-ghost">Change password</button>
                    </form>
                </div>
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