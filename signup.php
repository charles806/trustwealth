<?php
declare(strict_types=1);

require __DIR__ . '/app/config.php';
require __DIR__ . '/app/auth.php';
require __DIR__ . '/app/helpers.php';

if (current_user() !== null) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$old = ['fullname' => '', 'username' => '', 'email' => '', 'confirm_email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $old = [
        'fullname' => trim((string) ($_POST['fullname'] ?? '')),
        'username' => trim((string) ($_POST['username'] ?? '')),
        'email' => trim((string) ($_POST['email'] ?? '')),
        'confirm_email' => trim((string) ($_POST['confirm_email'] ?? '')),
    ];
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
    $terms = isset($_POST['terms']);

    if (mb_strlen($old['fullname']) < 2) {
        $errors['fullname'] = 'Enter your full name.';
    }

    if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $old['username'])) {
        $errors['username'] = '3–20 characters, letters, numbers or underscore.';
    }

    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'That email address doesn&#8217;t look right.';
    } elseif ($old['email'] !== $old['confirm_email']) {
        $errors['confirm_email'] = 'Emails don&#8217;t match.';
    }

    if (strlen($password) < 8) {
        $errors['password'] = 'Use at least 8 characters.';
    } elseif ($password !== $confirmPassword) {
        $errors['confirm_password'] = 'Passwords don&#8217;t match.';
    }

    if (!$terms) {
        $errors['terms'] = 'You must accept the Terms &amp; Conditions.';
    }

    if (!$errors) {
        try {
            $pdo = db();

            $u = $pdo->prepare('SELECT username, email FROM users WHERE username = ? OR email = ? LIMIT 1');
            $u->execute([$old['username'], $old['email']]);
            if ($existing = $u->fetch()) {
                if (strcasecmp($existing['username'], $old['username']) === 0) {
                    $errors['username'] = 'That username is taken.';
                }
                if (strcasecmp($existing['email'], $old['email']) === 0) {
                    $errors['email'] = 'An account with that email already exists.';
                }
            }
        } catch (Throwable $er) {
            error_log('signup uniqueness check: ' . $er->getMessage());
            $errors['global'] = 'Something went wrong. Please try again.';
        }
    }

    if (!$errors) {
        try {
            $pdo = db();
            $pdo->beginTransaction();

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO users (fullname, username, email, password_hash) VALUES (?, ?, ?, ?)');
            $stmt->execute([$old['fullname'], $old['username'], $old['email'], $hash]);
            $userId = (int) $pdo->lastInsertId();

            /* ---- demo portfolio seed -------------------------------------- */
            $planStmt = $pdo->prepare('SELECT id, min_amount, yield_pct FROM plans WHERE name = ? LIMIT 1');
            $planStmt->execute(['Business']);
            $business = $planStmt->fetch();

            $started = new DateTimeImmutable('-2 days');
            $planAmount = $business ? (float) $business['min_amount'] : 1000.0;
            $interest = $business ? round($planAmount * (float) $business['yield_pct'] / 100, 2) : 150.0;

            $balance = 4000.0 + $interest + 250.0;
            $btc = $balance / BTC_USD_RATE;

            $pdo->prepare('INSERT INTO portfolios (user_id, balance_usd, btc_amount, plan_id, plan_amount, plan_started_at) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$userId, $balance, $btc, $business['id'] ?? null, $planAmount, $started->format('Y-m-d H:i:s')]);

            $txSeed = [
                ['deposit', 4000.0, 'completed', 'Initial deposit', '-6 days'],
                ['return', $interest, 'completed', null, '-2 days'],
                ['referral', 250.0, 'completed', 'Referral bonus', '-1 days'],
            ];
            $insertTx = $pdo->prepare(
                'INSERT INTO transactions (user_id, type, amount, status, note, created_at) VALUES (?, ?, ?, ?, ?, ?)'
            );
            foreach ($txSeed as [$type, $amount, $status, $note, $offset]) {
                $when = (new DateTimeImmutable($offset))->format('Y-m-d H:i:s');
                $insertTx->execute([$userId, $type, $amount, $status, $note, $when]);
            }
            /* ---------------------------------------------------------------- */

            $pdo->commit();

            login_user($userId);
            header('Location: dashboard.php');
            exit;
        } catch (Throwable $er) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('signup: ' . $er->getMessage());
            $errors['global'] = 'Could not create the account. Please try again.';
        }
    }
}

$pageTitle = 'Create Account';
$bodyClass = 'signup-page';

include __DIR__ . '/partials/header.php';
?>

<main class="signup-main" id="signup">
    <div class="signup-card">
        <h1>Create your account</h1>
        <p>It takes a few minutes. You&#8217;ll be investing in minutes after that.</p>

        <?php if (!empty($errors['global'])) : ?>
            <p class="form-alert" role="alert"><?= e($errors['global']) ?></p>
        <?php endif; ?>

        <form action="signup.php" method="POST" class="signup-form" id="signup-form" autocomplete="on">
            <?= csrf_field() ?>

            <div class="form-group">
                <input type="text" name="fullname" id="fullname" placeholder="Your full name" autocomplete="name" value="<?= e($old['fullname']) ?>" required>
                <?php if (!empty($errors['fullname'])) : ?><p class="error-msg show"><?= $errors['fullname'] ?></p><?php endif; ?>
            </div>

            <div class="form-group">
                <input type="text" name="username" id="username" placeholder="Your username" autocomplete="username" value="<?= e($old['username']) ?>" required>
                <?php if (!empty($errors['username'])) : ?><p class="error-msg show"><?= $errors['username'] ?></p><?php endif; ?>
            </div>

            <div class="form-group">
                <input type="email" name="email" id="email" placeholder="Email" autocomplete="email" value="<?= e($old['email']) ?>" required>
                <?php if (!empty($errors['email'])) : ?><p class="error-msg show"><?= $errors['email'] ?></p><?php endif; ?>
            </div>

            <div class="form-group">
                <input type="email" name="confirm_email" id="confirm_email" placeholder="Confirm email" autocomplete="email" value="<?= e($old['confirm_email']) ?>" required>
                <?php if (!empty($errors['confirm_email'])) : ?><p class="error-msg show"><?= $errors['confirm_email'] ?></p><?php endif; ?>
            </div>

            <div class="form-group">
                <input type="password" name="password" id="password" placeholder="Password" autocomplete="new-password" minlength="8" required>
                <?php if (!empty($errors['password'])) : ?><p class="error-msg show"><?= $errors['password'] ?></p><?php endif; ?>
            </div>

            <div class="form-group">
                <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm password" autocomplete="new-password" required>
                <?php if (!empty($errors['confirm_password'])) : ?><p class="error-msg show"><?= $errors['confirm_password'] ?></p><?php endif; ?>
            </div>

            <div class="terms-group">
                <input type="checkbox" id="terms" name="terms" <?= isset($_POST['terms']) ? 'checked' : '' ?> required>
                <label for="terms">I agree to the <a href="#">Terms &amp; Conditions</a> and the <a href="#">Privacy Policy</a>.</label>
            </div>
            <?php if (!empty($errors['terms'])) : ?><p class="error-msg show terms-error"><?= $errors['terms'] ?></p><?php endif; ?>

            <button type="submit" class="btn btn-gold btn-block btn-lg">Create account</button>
        </form>

        <p class="signup-meta">Already have an account? <a href="login.php">Log in</a>.</p>
    </div>
</main>

<?php
include __DIR__ . '/partials/footer.php';