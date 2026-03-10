-- =====================================================
-- Account Lockout Feature - Database Update
-- =====================================================
-- Run this SQL in phpMyAdmin or MySQL command line
-- Database: lovejoy_db
-- =====================================================

-- Add account lockout fields to users table
ALTER TABLE users 
ADD COLUMN failed_login_attempts INT DEFAULT 0 COMMENT 'Number of consecutive failed login attempts',
ADD COLUMN lockout_until DATETIME DEFAULT NULL COMMENT 'Account locked until this time (NULL = not locked)',
ADD COLUMN last_failed_login DATETIME DEFAULT NULL COMMENT 'Timestamp of last failed login attempt';

-- Verify the changes (optional - run to check)
-- DESCRIBE users;

