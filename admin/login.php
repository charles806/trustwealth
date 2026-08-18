<?php
declare(strict_types=1);

require __DIR__ . '/../app/config.php';
require __DIR__ . '/../app/auth.php';
require __DIR__ . '/../app/helpers.php';

if (current_user() !== null && is_admin()) {
    header('Location: index.php');
    exit;
}

$error = '';
$identity = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $identity = trim((string) ($_POST['identity'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($identity === '' || $password === '') {
        $error = 'Enter your operator identity and password.';
    } else {
        try {
            $pdo = db();
            $stmt = $pdo->prepare(
                'SELECT * FROM users
                 WHERE (username = ? OR email = ?) AND is_admin = 1
                 LIMIT 1'
            );
            $stmt->execute([$identity, $identity]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                login_user((int) $user['id']);
                header('Location: index.php');
                exit;
            }

            $error = 'Operator access only. Those credentials are not recognised.';
        } catch (Throwable $er) {
            error_log('admin login: ' . $er->getMessage());
            $error = 'Could not reach the account system. Please try again.';
        }
    }
}

$pageTitle = 'Operator Login';
$bodyClass = 'signup-page';

include __DIR__ . '/../partials/header.php';
?>

<main class="signup-main" id="login">
    <div class="signup-card admin-card">
        <p class="eyebrow"><i class="fa-solid fa-shield-halved"></i> Operators only</p>
        <h1>Operator console</h1>
        <p>Sign in with an administrator account to manage the platform.</p>

        <?php if ($error !== '') : ?>
            <p class="form-alert" role="alert"><?= $error ?></p>
        <?php endif; ?>

        <form action="login.php" method="POST" class="signup-form" autocomplete="on">
            <?= csrf_field() ?>

            <div class="form-group">
                <input type="text" name="identity" id="identity" placeholder="Admin username or email" autocomplete="username" value="<?= e($identity) ?>" required>
            </div>

            <div class="form-group">
                <input type="password" name="password" id="password" placeholder="Admin password" autocomplete="current-password" required>
            </div>

            <button type="submit" class="btn btn-gold btn-block btn-lg">Enter operator console</button>
        </form>

        <p class="signup-meta"><a href="<?= $base ?>login.php">&laquo; Back to member login</a></p>
    </div>
</main>

<?php
include __DIR__ . '/../partials/footer.php';