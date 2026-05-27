-- ============================================================
--  auth_system — Database Setup
--  Run this file once in phpMyAdmin or MySQL CLI:
--    mysql -u root -p < setup.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS auth_system
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE auth_system;

-- ─── Users Table ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    full_name     VARCHAR(100)    NOT NULL,
    email         VARCHAR(180)    NOT NULL UNIQUE,
    password_hash VARCHAR(255)    NOT NULL,
    avatar_color  VARCHAR(7)      NOT NULL DEFAULT '#6C63FF',
    role          ENUM('user','admin') NOT NULL DEFAULT 'user',
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login    TIMESTAMP       NULL,
    PRIMARY KEY (id),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Activity Log Table ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS activity_log (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    action     VARCHAR(100) NOT NULL,
    ip_address VARCHAR(45)  NOT NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
