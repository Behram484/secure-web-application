-- =====================================================
-- Base Schema - Lovejoy App
-- =====================================================
-- Run this FIRST, before any of the database_*.sql migration
-- scripts in this directory. Those scripts are all ALTER TABLE
-- statements and assume these two tables already exist.
--
-- Column set reconstructed from application source:
--   users.reset_token / reset_expires  -> password_reset.php,
--                                         password_reset_request.php
--   users.is_admin                     -> admin/*.php RBAC checks
--   requests.status                    -> admin/update_status.php
--
-- Apply order:
--   000_base_schema.sql
--   database_email_verification.sql
--   database_security_question.sql
--   database_account_lockout.sql
--   database_2fa.sql
--   database_update.sql
-- =====================================================

CREATE DATABASE IF NOT EXISTS lovejoy_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE lovejoy_db;

CREATE TABLE IF NOT EXISTS users (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    email             VARCHAR(255) NOT NULL,
    password_hash     VARCHAR(255) NOT NULL COMMENT 'bcrypt hash from password_hash()',
    full_name         VARCHAR(255) NOT NULL,
    phone             VARCHAR(20)  NOT NULL COMMENT 'UK format: 11 digits starting 07',
    is_admin          TINYINT(1)   NOT NULL DEFAULT 0,
    reset_token       VARCHAR(64)  DEFAULT NULL COMMENT 'Password reset token (bin2hex of 32 bytes)',
    reset_expires     DATETIME     DEFAULT NULL COMMENT 'Password reset token expiry (1 hour)',
    created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_reset_token (reset_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS requests (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED NOT NULL,
    subject     VARCHAR(255) NOT NULL,
    description TEXT         NOT NULL,
    status      VARCHAR(20)  NOT NULL DEFAULT 'pending'
                COMMENT 'pending | open | in_progress | resolved | closed',
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_requests_user_id (user_id),
    KEY idx_requests_status (status),
    CONSTRAINT fk_requests_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
