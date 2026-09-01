-- Trust Wealth Ltd — XAMPP MySQL schema
-- Import this into phpMyAdmin against a database named `trust_wealth`
-- (or run app: database/install.php, which does it for you).

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fullname VARCHAR(120) NOT NULL,
    username VARCHAR(60) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    is_admin TINYINT(1) NOT NULL DEFAULT 0,
    withdrawal_address VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_username (username),
    UNIQUE KEY uq_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE plans (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(40) NOT NULL,
    min_amount DECIMAL(14,2) NOT NULL,
    max_amount DECIMAL(14,2) NULL,
    yield_pct DECIMAL(5,2) NOT NULL,
    period_days INT UNSIGNED NOT NULL,
    featured TINYINT(1) NOT NULL DEFAULT 0,
    sort INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO plans (name, min_amount, max_amount, yield_pct, period_days, featured, sort) VALUES
    ('Basic',    50,    3000,   10.00,  1, 0, 1),
    ('Business', 3500,  9999,   15.00,  3, 0, 2),
    ('Gold',     19000, NULL,   30.00,  7, 1, 3),
    ('Advanced', 100000, NULL,  50.00, 30, 0, 4);

CREATE TABLE portfolios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    balance_usd DECIMAL(16,2) NOT NULL DEFAULT 0,
    btc_amount DECIMAL(16,8) NOT NULL DEFAULT 0,
    plan_id INT UNSIGNED NULL,
    plan_amount DECIMAL(16,2) NULL,
    plan_started_at DATETIME NULL,
    plan_paid_out TINYINT(1) NOT NULL DEFAULT 0,
    CONSTRAINT fk_portfolio_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_portfolio_plan FOREIGN KEY (plan_id) REFERENCES plans (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE transactions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    type ENUM('deposit','return','referral','invest','withdraw') NOT NULL,
    amount DECIMAL(16,2) NOT NULL,
    deposit_from VARCHAR(255) NULL,
    deposit_ref VARCHAR(32) NULL,
    status ENUM('completed','pending','cancelled') NOT NULL DEFAULT 'completed',
    note VARCHAR(160) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tx_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sessions (
    id VARCHAR(128) NOT NULL PRIMARY KEY,
    data TEXT NULL,
    last_activity INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_sessions_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE settings (
    s_key VARCHAR(64) NOT NULL PRIMARY KEY,
    s_value VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (s_key, s_value) VALUES
    ('btc_deposit_address',  'bc1q7wln9r6qw2kxwvrs8t3gr0k9z4hx7tkm'),
    ('usdt_deposit_address', 'TXj9yQw3VJzJfTb2HcN5pLk8mQw6Ad3R1c')
ON DUPLICATE KEY UPDATE s_value = s_value;