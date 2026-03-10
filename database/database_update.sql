-- Database update script for Lovejoy App
-- Add new columns to requests table for file upload and contact preference

-- Add contact_preference column (phone or email)
ALTER TABLE requests 
ADD COLUMN contact_preference VARCHAR(10) DEFAULT 'email' 
AFTER description;

-- Add photo_path column to store uploaded photo file path
ALTER TABLE requests 
ADD COLUMN photo_path VARCHAR(255) NULL 
AFTER contact_preference;

-- Update existing records to have default contact preference
UPDATE requests SET contact_preference = 'email' WHERE contact_preference IS NULL;

