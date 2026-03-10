-- =====================================================
-- 2FA (Two-Factor Authentication) - Database Update
-- =====================================================
-- Run this SQL in phpMyAdmin or MySQL command line
-- Database: lovejoy_db
-- =====================================================

-- Add 2FA fields to users table
ALTER TABLE users 
ADD COLUMN two_fa_code VARCHAR(6) DEFAULT NULL COMMENT '6-digit 2FA verification code',
ADD COLUMN two_fa_expires DATETIME DEFAULT NULL COMMENT '2FA code expiration time';

-- Verify the changes (optional - run to check)
-- DESCRIBE users;

