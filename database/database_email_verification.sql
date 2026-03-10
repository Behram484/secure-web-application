-- Database update script for Email Verification
-- Add email verification fields to users table

-- Add email_verified column (0 = not verified, 1 = verified)
ALTER TABLE users 
ADD COLUMN email_verified TINYINT(1) DEFAULT 0 
AFTER email;

-- Add verification_token column to store email verification token
ALTER TABLE users 
ADD COLUMN verification_token VARCHAR(64) NULL 
AFTER email_verified;

-- Add verification_token_expires column for token expiration (24 hours)
ALTER TABLE users 
ADD COLUMN verification_token_expires DATETIME NULL 
AFTER verification_token;

-- Update existing users to be verified (for backward compatibility)
UPDATE users SET email_verified = 1 WHERE email_verified IS NULL;

