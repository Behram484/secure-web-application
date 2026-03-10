<?php

/**
 * Email Configuration
 * 
 * Configure your email settings here.
 * 
 * For Gmail SMTP:
 * 1. Enable 2-Step Verification in your Google Account
 * 2. Generate an App Password: https://myaccount.google.com/apppasswords
 * 3. Use the 16-character app password (not your regular Gmail password)
 */

return [
    // SMTP Settings
    'smtp_host'       => 'smtp.gmail.com',        // Gmail SMTP server
    'smtp_port'       => 587,                      // TLS port (use 465 for SSL)
    'smtp_username'   => 'behramalyas@gmail.com',   // Your Gmail address
    'smtp_password'   => 'ssomuofqszpljbxk',      // Your 16-character app password (no spaces)
    'smtp_encryption' => 'tls',                    // 'tls' or 'ssl'
    
    // Email Settings
    'from_email'      => 'behramalyas@gmail.com',   // Sender email (usually same as username)
    'from_name'       => 'Lovejoy App',            // Sender display name
    
    // Application Settings
    'app_url'         => 'http://localhost/lovejoy_app',  // Your application URL (change for production)
    
    // Enable/Disable email sending (for development)
    'enabled'         => true,                     // Set to false to disable email sending (will show link instead)
    
    // Development mode: show link on page even when email is sent
    'dev_show_link'   => false,                   // Set to false to hide link when email is sent successfully
];

