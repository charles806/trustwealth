<?php
declare(strict_types=1);

require_once __DIR__ . '/app/config.php';

$hash = '$2y$12$p.bL/IOwgVPK7848TSTDyOBzKZF0Me/3mHs2Wui8JVCxn4s3b30aG';
$email = 'c08445333@gmail.com';
$pdo = db();

try {
    $stmt = $pdo->prepare(
        "INSERT INTO users (fullname, username, email, password_hash, is_admin)
         VALUES ('Admin', 'admin', ?, ?, 1)
         ON DUPLICATE KEY UPDATE fullname='Admin', username='admin', password_hash=?, is_admin=1"
    );
    $stmt->execute([$email, $hash, $hash]);
    header('Content-Type: text/plain');
    echo 'OK: admin ensured (' . $email . ')';
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain');
    echo 'ERR: ' . $e->getMessage();
}
