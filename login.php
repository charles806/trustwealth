<?php
declare(strict_types=1);

require __DIR__ . '/app/config.php';
require __DIR__ . '/app/auth.php';
require __DIR__ . '/app/helpers.php';

if (current_user() !== null) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$identity = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $identity = trim((string) ($_POST['identity'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($identity === '' || $password === '') {
        $error = 'Enter your username or email and your password.';
    } else {
        try {
            $pdo = db();
            $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1');
            $stmt->execute([$identity, $identity]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                login_user((int) $user['id']);
                header('Location: dashboard.php');
                exit;
            }

            /* Same message whether the account or the password was wrong. */
            $error = 'That username or password didn&#8217;t match.';
        } catch (Throwable $er) {
            error_log('login: ' . $er->getMessage());
            $error = 'Could not reach the account system. Please try again.';
        }
    }
}

$pageTitle = 'Log In';
$bodyClass = 'signup-page';

include __DIR__ . '/partials/header.php';
?>

<main class="signup-main" id="login">
    <div class="signup-card">
        <h1>Welcome back</h1>
        <p>Log in to open your trading desk.</p>

        <?php if ($error !== '') : ?>
            <p class="form-alert" role="alert"><?= $error ?></p>
        <?php endif; ?>

        <form action="login.php" method="POST" class="signup-form" autocomplete="on">
            <?= csrf_field() ?>

            <div class="form-group">
                <input type="text" name="identity" id="identity" placeholder="Username or email" autocomplete="username" value="<?= e($identity) ?>" required>
            </div>

            <div class="form-group">
                <input type="password" name="password" id="password" placeholder="Password" autocomplete="current-password" required>
            </div>

            <button type="submit" class="btn btn-gold btn-block btn-lg">Log in</button>
        </form>

        <p class="signup-meta">New here? <a href="signup.php">Create an account</a>.</p>
    </div>
</main>

<?php
include __DIR__ . '/partials/footer.php';