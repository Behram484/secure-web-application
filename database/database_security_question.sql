-- Add Security Question fields to users table
-- This script adds security question and answer hash columns for password recovery

ALTER TABLE users
ADD COLUMN security_question VARCHAR(255) NULL COMMENT 'Security question selected by user during registration'
AFTER phone;

ALTER TABLE users
ADD COLUMN security_answer_hash VARCHAR(255) NULL COMMENT 'Hashed security answer for password recovery verification'
AFTER security_question;

-- Note: Existing users will have NULL values for these fields
-- They can be updated later if needed

