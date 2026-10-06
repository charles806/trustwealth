<?php
declare(strict_types=1);

function db(bool $reconnect = false): PDO
{
    static $pdo = null;
    static $schema = null;

    if ($reconnect) {
        $pdo = null;
    }

    if ($pdo === null) {
        $schema = $schema ?? _configured_schema();
        $options = _db_options();

        try {
            $pdo = _db_connect($schema, $options);
        } catch (PDOException $er) {
            if (_is_missing_database($er)) {
                error_log('db: database ' . $schema . ' is missing, creating it');
                _create_database($schema, $options);
                $pdo = _db_connect($schema, $options);
            } elseif (_is_access_denied($er)) {
                $schema = _resolve_app_schema($schema, $options);
                $pdo = _db_connect($schema, $options);
            } else {
                throw $er;
            }
        }

        if (_is_system_schema($schema)) {
            $schema = _resolve_app_schema($schema, $options);
            $pdo = _db_connect($schema, $options);
        }

        _schema_bootstrap($pdo);
        _migrate($pdo);
    }

    return $pdo;
}

function _configured_schema(): string
{
    $schema = DB_NAME;

    if ($schema === '') {
        throw new RuntimeException('DB_NAME is empty. Set it to the application database in the environment.');
    }

    if (strpbrk($schema, ';=') !== false) {
        throw new RuntimeException('DB_NAME contains characters that are not allowed in a DSN: ' . $schema);
    }

    return $schema;
}

function _db_options(): array
{
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    if (defined('DB_SSL_CA') && DB_SSL_CA !== '') {
        if (is_file(DB_SSL_CA)) {
            $options[PDO::MYSQL_ATTR_SSL_CA] = DB_SSL_CA;
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
        } else {
            error_log('db: DB_SSL_CA file not found (' . DB_SSL_CA . '), connecting without CA verification');
        }
    }

    return $options;
}

function _db_connect(?string $schema, array $options): PDO
{
    $dsn = sprintf(
        'mysql:host=%s;port=%d%s;charset=utf8mb4',
        DB_HOST,
        DB_PORT,
        $schema === null ? '' : ';dbname=' . $schema
    );

    return new PDO($dsn, DB_USER, DB_PASS, $options);
}

function _create_database(string $schema, array $options): void
{
    _db_connect(null, $options)->exec(
        'CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', $schema) . '`
         CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
    );
}

/*
 * The configured DB_NAME is unusable (it points at a system schema such as
 * "mysql", or the user has no rights on it). Find the real application
 * database by the table every install must have, fall back to provisioning
 * the default one, and fail with an actionable message if neither works.
 */
function _resolve_app_schema(string $current, array $options): string
{
    $stmt = _db_connect(null, $options)->prepare(
        "SELECT DISTINCT table_schema FROM information_schema.tables
         WHERE table_name = 'users'
           AND table_schema NOT IN ('mysql', 'information_schema', 'performance_schema', 'sys')"
    );
    $stmt->execute();
    $found = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if ($found === []) {
        error_log('db: no application database found (DB_NAME was "' . $current . '"), creating trustwealth');
        _create_database('trustwealth', $options);

        return 'trustwealth';
    }

    if (in_array('trustwealth', $found, true)) {
        $found = ['trustwealth'];
    }

    if (count($found) === 1) {
        if ($found[0] !== $current) {
            error_log('db: database "' . $current . '" is unusable; using "' . $found[0] . '" instead');
        }

        return $found[0];
    }

    sort($found);

    throw new RuntimeException(
        'DB_NAME "' . $current . '" is unusable and several candidate databases exist (' .
        implode(', ', $found) . '). Set DB_NAME to one of them.'
    );
}

function _is_system_schema(string $schema): bool
{
    return in_array(strtolower($schema), ['mysql', 'information_schema', 'performance_schema', 'sys'], true);
}

function _is_missing_database(PDOException $er): bool
{
    return (int) ($er->errorInfo[1] ?? 0) === 1049
        || stripos($er->getMessage(), 'Unknown database') !== false;
}

function _is_access_denied(PDOException $er): bool
{
    return (int) ($er->errorInfo[1] ?? 0) === 1044
        || stripos($er->getMessage(), ' to database ') !== false;
}

/*
 * First-run setup: creates any missing tables and seeds the plans. Runs on
 * every request but only does one information_schema lookup and touches what
 * is absent, so a healthy database costs a single query. Building the schema
 * this way (instead of skipping whenever `users` exists) also repairs a
 * half-provisioned database. Idempotent — safe on every request. This is what
 * builds the database on hosts without a SQL console: create an empty
 * database in the panel, push the code, and the first page load finishes the
 * rest.
 */
function _schema_bootstrap(PDO $pdo): void
{
    try {
        $statements = [
            'users' => "CREATE TABLE users (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                fullname VARCHAR(120) NOT NULL,
                username VARCHAR(60) NOT NULL,
                email VARCHAR(190) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_username (username),
                UNIQUE KEY uq_email (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'plans' => "CREATE TABLE plans (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(40) NOT NULL,
                min_amount DECIMAL(14,2) NOT NULL,
                max_amount DECIMAL(14,2) NULL,
                yield_pct DECIMAL(5,2) NOT NULL,
                period_days INT UNSIGNED NOT NULL,
                featured TINYINT(1) NOT NULL DEFAULT 0,
                sort INT UNSIGNED NOT NULL DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'portfolios' => "CREATE TABLE portfolios (
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
            'transactions' => "CREATE TABLE transactions (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                type ENUM('deposit','return','referral') NOT NULL,
                amount DECIMAL(16,2) NOT NULL,
                status ENUM('completed','pending') NOT NULL DEFAULT 'completed',
                note VARCHAR(160) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_tx_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];

        $missing = _missing_tables($pdo, array_keys($statements));
        foreach ($missing as $table) {
            $pdo->exec($statements[$table]);
        }

        _ensure_sessions_table($pdo);

        if ($missing !== []) {
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
        }
    } catch (Throwable $er) {
        error_log('schema bootstrap: ' . $er->getMessage());
    }
}

function _missing_tables(PDO $pdo, array $tables): array
{
    $in = implode(',', array_fill(0, count($tables), '?'));
    $stmt = $pdo->prepare(
        "SELECT table_name FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name IN ($in)"
    );
    $stmt->execute($tables);
    $found = $stmt->fetchAll(PDO::FETCH_COLUMN);

    return array_values(array_diff(
        array_map('strtolower', $tables),
        array_map('strtolower', $found)
    ));
}

function _ensure_sessions_table(PDO $pdo): void
{
    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS sessions (
                id VARCHAR(128) NOT NULL PRIMARY KEY,
                data MEDIUMTEXT NULL,
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
        _ensure_column($pdo, 'transactions', 'deposit_from', 'ADD COLUMN deposit_from VARCHAR(255) NULL AFTER amount');
        _ensure_column($pdo, 'transactions', 'deposit_ref', 'ADD COLUMN deposit_ref VARCHAR(32) NULL AFTER deposit_from');

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

        $sessionData = _column_type($pdo, 'sessions', 'data');
        if ($sessionData !== null && stripos($sessionData, 'mediumtext') === false) {
            $pdo->exec('ALTER TABLE sessions MODIFY COLUMN data MEDIUMTEXT NULL');
        }

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
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);

    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->exec('ALTER TABLE `' . $table . '` ' . $alter);
    }
}

function _column_type(PDO $pdo, string $table, string $column): ?string
{
    $stmt = $pdo->prepare(
        'SELECT COLUMN_TYPE FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
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