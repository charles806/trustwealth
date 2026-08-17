<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    static $user = null;

    if ($user === null) {
        $stmt = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch() ?: null;
    }

    return $user;
}

function require_login(): array
{
    $user = current_user();

    if ($user === null) {
        header('Location: login.php');
        exit;
    }

    return $user;
}

function login_user(int $id): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $id;
}

function csrf_token(): string
{
    return $_COOKIE['tw_csrf'] ?? '';
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="'
        . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8')
        . '">';
}

function csrf_check(): void
{
    $sent = $_POST['csrf'] ?? '';
    $expect = $_COOKIE['tw_csrf'] ?? '';

    if ($sent === '' || !hash_equals((string) $expect, (string) $sent)) {
        http_response_code(419);
        echo 'Session token missing or stale. Please go back, refresh, and try again.';
        exit;
    }
}