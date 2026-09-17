<?php
header('Content-Type: text/plain');
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "DB_HOST=" . getenv('DB_HOST') . "\n";
echo "DB_PORT=" . getenv('DB_PORT') . "\n";
echo "DB_NAME=" . getenv('DB_NAME') . "\n";
echo "DB_USER=" . getenv('DB_USER') . "\n";
echo "DB_SSL_CA=" . getenv('DB_SSL_CA') . "\n";
echo "CA exists=" . (file_exists(getenv('DB_SSL_CA') ?: '') ? 'yes' : 'no') . "\n\n";

// 1. DNS
$host = getenv('DB_HOST');
echo "DNS: " . gethostbyname($host) . "\n";

// 2. TCP
$port = (int)(getenv('DB_PORT') ?: 3306);
$fp = @fsockopen($host, $port, $errno, $errstr, 5);
echo "TCP connect: " . ($fp ? 'ok' : "FAIL ($errno $errstr)") . "\n";
if ($fp) fclose($fp);

// 3. PDO without TLS
try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=" . getenv('DB_NAME'),
                   getenv('DB_USER'), getenv('DB_PASS'),
                   [PDO::ATTR_TIMEOUT => 5]);
    echo "PDO (no TLS): ok\n";
} catch (Throwable $e) {
    echo "PDO (no TLS): " . $e->getMessage() . "\n";
}

// 4. PDO with TLS
try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=" . getenv('DB_NAME'),
                   getenv('DB_USER'), getenv('DB_PASS'),
                   [PDO::MYSQL_ATTR_SSL_CA => getenv('DB_SSL_CA'),
                    PDO::ATTR_TIMEOUT => 5]);
    echo "PDO (TLS): ok\n";
} catch (Throwable $e) {
    echo "PDO (TLS): " . $e->getMessage() . "\n";
}