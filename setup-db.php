<?php
declare(strict_types=1);

header('Content-Type: text/plain');

$host = getenv('DB_HOST');
$port = (int)(getenv('DB_PORT') ?: '3306');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS') ?: '';
$sslCa = getenv('DB_SSL_CA') ?: '';

if ($host === false || $user === false) {
    echo 'ERROR: DB_HOST and DB_USER must be set in the environment';
    exit(1);
}

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
];

if ($sslCa !== '') {
    $options[PDO::MYSQL_ATTR_SSL_CA] = $sslCa;
    $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
}

try {
    $pdo = new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host, $port), $user, $pass, $options);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `trustwealth` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo 'OK: database trustwealth ready';
} catch (Throwable $e) {
    echo 'ERROR: ' . $e->getMessage();
}