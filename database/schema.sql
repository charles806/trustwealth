-- Trust Wealth Ltd — XAMPP MySQL schema
-- Import this into phpMyAdmin against a database named `trust_wealth`
-- (or run app: database/install.php, which does it for you).

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fullname VARCHAR(120) NOT NULL,
    username VARCHAR(60) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
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
    CONSTRAINT fk_portfolio_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_portfolio_plan FOREIGN KEY (plan_id) REFERENCES plans (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE transactions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    type ENUM('deposit','return','referral') NOT NULL,
    amount DECIMAL(16,2) NOT NULL,
    status ENUM('completed','pending') NOT NULL DEFAULT 'completed',
    note VARCHAR(160) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tx_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sessions (
    id VARCHAR(128) NOT NULL PRIMARY KEY,
    data TEXT NULL,
    last_activity INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_sessions_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;