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
        _migrate($pdo);
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

        _ensure_sessions_table($pdo);

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

function _ensure_sessions_table(PDO $pdo): void
{
    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS sessions (
                id VARCHAR(128) NOT NULL PRIMARY KEY,
                data TEXT NULL,
                last_activity INT UNSIGNED NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                KEY idx_sessions_activity (last_activity)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } catch (Throwable $er) {
        error_log('schema bootstrap: sessions table: ' . $er->getMessage());
    }
}

/*
 * In-place migration for databases that already exist (the CREATE-only
 * bootstrap above skips them). Keeps the production ledger up to date
 * without touching existing rows. Idempotent — safe on every request.
 */
function _migrate(PDO $pdo): void
{
    try {
        _ensure_column($pdo, 'users', 'is_admin', 'ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER email');
        _ensure_column($pdo, 'users', 'withdrawal_address', 'ADD COLUMN withdrawal_address VARCHAR(255) NULL AFTER password_hash');
        _ensure_column($pdo, 'portfolios', 'plan_paid_out', 'ADD COLUMN plan_paid_out TINYINT(1) NOT NULL DEFAULT 0 AFTER plan_started_at');

        $type = _column_type($pdo, 'transactions', 'type');
        if ($type !== null && stripos($type, 'invest') === false) {
            $pdo->exec("ALTER TABLE transactions MODIFY COLUMN type ENUM('deposit','return','referral','invest','withdraw') NOT NULL");
        }

        $status = _column_type($pdo, 'transactions', 'status');
        if ($status !== null && stripos($status, 'cancelled') === false) {
            $pdo->exec("ALTER TABLE transactions MODIFY COLUMN status ENUM('completed','pending','cancelled') NOT NULL DEFAULT 'completed'");
        }

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS settings (
                s_key VARCHAR(64) NOT NULL PRIMARY KEY,
                s_value VARCHAR(255) NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        _seed_setting($pdo, 'btc_deposit_address', defined('BTC_DEPOSIT_ADDRESS') ? BTC_DEPOSIT_ADDRESS : '');
        _seed_setting($pdo, 'usdt_deposit_address', defined('USDT_DEPOSIT_ADDRESS') ? USDT_DEPOSIT_ADDRESS : '');

        _reset_seeded_demo($pdo);
    } catch (Throwable $er) {
        error_log('migrate: ' . $er->getMessage());
    }
}

/*
 * One-time cleanup for accounts created under the old signup, which seeded a
 * fake balance + demo transactions. New signups start at $0.00 so this only
 * ever touches accounts carrying the seeding marker. Idempotent — the marker
 * rows are deleted, so it runs once and then finds nothing.
 */
function _reset_seeded_demo(PDO $pdo): void
{
    try {
        $stmt = $pdo->query(
            "SELECT DISTINCT user_id FROM transactions WHERE type = 'deposit' AND note = 'Initial deposit'"
        );
        $userIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        if (count($userIds) === 0) {
            return;
        }

        $in = implode(',', array_fill(0, count($userIds), '?'));

        $pdo->prepare(
            "UPDATE portfolios
             SET balance_usd = 0, btc_amount = 0,
                 plan_id = NULL, plan_amount = NULL, plan_started_at = NULL, plan_paid_out = 0
             WHERE user_id IN ($in)"
        )->execute($userIds);

        $pdo->prepare("DELETE FROM transactions WHERE user_id IN ($in)")->execute($userIds);

        error_log('reset_seeded_demo: cleared ' . count($userIds) . ' old seeded account(s)');
    } catch (Throwable $er) {
        error_log('reset_seeded_demo: ' . $er->getMessage());
    }
}

function _ensure_column(PDO $pdo, string $table, string $column, string $alter): void
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([DB_NAME, $table, $column]);

    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->exec('ALTER TABLE `' . $table . '` ' . $alter);
    }
}

function _column_type(PDO $pdo, string $table, string $column): ?string
{
    $stmt = $pdo->prepare(
        'SELECT COLUMN_TYPE FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([DB_NAME, $table, $column]);
    $value = $stmt->fetchColumn();

    return $value === false ? null : (string) $value;
}

function _seed_setting(PDO $pdo, string $key, string $value): void
{
    $stmt = $pdo->prepare('INSERT IGNORE INTO settings (s_key, s_value) VALUES (?, ?)');
    $stmt->execute([$key, $value]);
}

function setting(string $key): string
{
    $stmt = db()->prepare('SELECT s_value FROM settings WHERE s_key = ? LIMIT 1');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();

    return $value === false ? '' : (string) $value;
}

function set_setting(string $key, string $value): void
{
    $stmt = db()->prepare(
        'INSERT INTO settings (s_key, s_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE s_value = VALUES(s_value)'
    );
    $stmt->execute([$key, $value]);
}