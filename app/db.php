<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            DB_HOST,
            DB_PORT,
            DB_NAME
        );

        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        _schema_bootstrap($pdo);
    }

    return $pdo;
}

/*
 * First-run setup: creates the tables and seeds the plans if the schema is
 * missing. Idempotent — safe to run on every request. This is what builds the
 * database on hosts without a SQL console: create an empty database in the
 * panel, push the code, and the first page load finishes the rest.
 */
function _schema_bootstrap(PDO $pdo): void
{
    try {
        $exists = $pdo->query("SHOW TABLES LIKE 'users'")->fetchColumn();
        if ($exists) {
            return;
        }

        $statements = [
            "CREATE TABLE users (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                fullname VARCHAR(120) NOT NULL,
                username VARCHAR(60) NOT NULL,
                email VARCHAR(190) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_username (username),
                UNIQUE KEY uq_email (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE plans (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(40) NOT NULL,
                min_amount DECIMAL(14,2) NOT NULL,
                max_amount DECIMAL(14,2) NULL,
                yield_pct DECIMAL(5,2) NOT NULL,
                period_days INT UNSIGNED NOT NULL,
                featured TINYINT(1) NOT NULL DEFAULT 0,
                sort INT UNSIGNED NOT NULL DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE portfolios (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                balance_usd DECIMAL(16,2) NOT NULL DEFAULT 0,
                btc_amount DECIMAL(16,8) NOT NULL DEFAULT 0,
                plan_id INT UNSIGNED NULL,
                plan_amount DECIMAL(16,2) NULL,
                plan_started_at DATETIME NULL,
                CONSTRAINT fk_portfolio_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
                CONSTRAINT fk_portfolio_plan FOREIGN KEY (plan_id) REFERENCES plans (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE transactions (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                type ENUM('deposit','return','referral') NOT NULL,
                amount DECIMAL(16,2) NOT NULL,
                status ENUM('completed','pending') NOT NULL DEFAULT 'completed',
                note VARCHAR(160) NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_tx_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];

        foreach ($statements as $sql) {
            $pdo->exec($sql);
        }

        $planCount = (int) $pdo->query('SELECT COUNT(*) FROM plans')->fetchColumn();
        if ($planCount === 0) {
            $insert = $pdo->prepare(
                'INSERT INTO plans (name, min_amount, max_amount, yield_pct, period_days, featured, sort)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $rows = [
                ['Basic', 50, 3000, 10.0, 1, 0, 1],
                ['Business', 3500, 9999, 15.0, 3, 0, 2],
                ['Gold', 19000, null, 30.0, 7, 1, 3],
                ['Advanced', 100000, null, 50.0, 30, 0, 4],
            ];
            foreach ($rows as $row) {
                $insert->execute($row);
            }
        }
    } catch (Throwable $er) {
        error_log('schema bootstrap: ' . $er->getMessage());
    }
}