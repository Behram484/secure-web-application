<?php

/**
 * Email Sender using PHPMailer
 * 
 * This file handles sending emails for password reset and other notifications.
 * Make sure PHPMailer is installed via Composer.
 * 
 * Installation:
 * 1. Install Composer: https://getcomposer.org/
 * 2. Run: composer require phpmailer/phpmailer
 */

// Check if PHPMailer is available
$phpmailerLoaded = false;

// Try Composer autoload first
$phpmailerPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($phpmailerPath)) {
    require_once $phpmailerPath;
    $phpmailerLoaded = true;
}

// Fallback: try to load PHPMailer manually if Composer autoload is not available
if (!$phpmailerLoaded) {
    $phpmailerManualPath = __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
    if (file_exists($phpmailerManualPath)) {
        // Load required files in correct order
        require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';
        require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
        require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';
        $phpmailerLoaded = true;
    }
}

require_once __DIR__ . '/mail_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Send password reset email
 * 
 * @param string $toEmail Recipient email address
 * @param string $toName Recipient name
 * @param string $resetToken Password reset token
 * @return array ['success' => bool, 'message' => string, 'link' => string]
 */
function sendPasswordResetEmail($toEmail, $toName, $resetToken) {
    $config = require __DIR__ . '/mail_config.php';
    
    // Create reset link
    $resetLink = $config['app_url'] . '/password_reset.php?token=' . urlencode($resetToken);
    
    // If email is disabled, return with link for development
    if (!$config['enabled']) {
        return [
            'success' => false,
            'message' => 'Email sending is disabled (development mode)',
            'link' => $resetLink
        ];
    }
    
    // Check if PHPMailer classes are available
    // Try different class name formats
    $phpmailerClassExists = false;
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        $phpmailerClassExists = true;
    } elseif (class_exists('PHPMailer')) {
        // Older version without namespace
        $phpmailerClassExists = true;
    }
    
    if (!$phpmailerClassExists) {
        $checkPath = __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
        $exists = file_exists($checkPath) ? 'exists' : 'does not exist';
        $loaded = $phpmailerLoaded ? 'loaded' : 'not loaded';
        return [
            'success' => false,
            'message' => 'PHPMailer file ' . $exists . ' but class not found. File loaded: ' . $loaded . '. Path: ' . $checkPath,
            'link' => $resetLink
        ];
    }
    
    // Create PHPMailer instance
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = $config['smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['smtp_username'];
        $mail->Password   = $config['smtp_password'];
        $mail->SMTPSecure = $config['smtp_encryption'];
        $mail->Port       = $config['smtp_port'];
        $mail->CharSet    = 'UTF-8';
        
        // Enable verbose debug output (optional, for troubleshooting)
        // $mail->SMTPDebug = 2;
        
        // Recipients
        $mail->setFrom($config['from_email'], $config['from_name']);
        $mail->addAddress($toEmail, $toName);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Request - Lovejoy App';
        
        // Load email template
        $emailBody = getPasswordResetEmailTemplate($toName, $resetLink);
        $mail->Body = $emailBody;
        $mail->AltBody = strip_tags($emailBody); // Plain text version
        
        $mail->send();
        
        return [
            'success' => true,
            'message' => 'Password reset email sent successfully',
            'link' => $config['dev_show_link'] ? $resetLink : null
        ];
    } catch (Exception $e) {
        // Log error (in production, use proper logging)
        error_log("Email sending failed: " . $mail->ErrorInfo);
        
        return [
            'success' => false,
            'message' => 'Failed to send email: ' . $mail->ErrorInfo,
            'link' => $resetLink // Return link as fallback
        ];
    }
}

/**
 * Get password reset email template
 * 
 * @param string $name User's name
 * @param string $resetLink Password reset link
 * @return string HTML email template
 */
function getPasswordResetEmailTemplate($name, $resetLink) {
    $html = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Password Reset</title>
    </head>
    <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f5f5f5;">
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
            <h1 style="color: #fff; margin: 0; font-size: 24px;">Lovejoy App</h1>
        </div>
        
        <div style="background: #fff; padding: 30px; border: 1px solid #e0e0e0; border-top: none; border-radius: 0 0 10px 10px;">
            <h2 style="color: #333; margin-top: 0;">Password Reset Request</h2>
            
            <p>Hello ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ',</p>
            
            <p>We received a request to reset your password for your Lovejoy App account.</p>
            
            <p>Click the button below to reset your password:</p>
            
            <div style="text-align: center; margin: 30px 0;">
                <a href="' . htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8') . '" 
                   style="display: inline-block; padding: 12px 30px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                          color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600;">
                    Reset Password
                </a>
            </div>
            
            <p style="font-size: 12px; color: #666; margin-top: 30px;">
                Or copy and paste this link into your browser:<br>
                <a href="' . htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8') . '" style="color: #667eea; word-break: break-all;">
                    ' . htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8') . '
                </a>
            </p>
            
            <p style="font-size: 12px; color: #999; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e0e0e0;">
                <strong>Important:</strong> This link will expire in 1 hour. If you did not request a password reset, 
                please ignore this email or contact support if you have concerns.
            </p>
        </div>
        
        <div style="text-align: center; margin-top: 20px; font-size: 12px; color: #999;">
            <p>© ' . date('Y') . ' Lovejoy App. All rights reserved.</p>
        </div>
    </body>
    </html>';
    
    return $html;
}

/**
 * Send email verification email
 * 
 * @param string $toEmail Recipient email address
 * @param string $toName Recipient name
 * @param string $verificationToken Email verification token
 * @return array ['success' => bool, 'message' => string, 'link' => string]
 */
function sendEmailVerification($toEmail, $toName, $verificationToken) {
    $config = require __DIR__ . '/mail_config.php';
    
    // Create verification link
    $verificationLink = $config['app_url'] . '/email_verify.php?token=' . urlencode($verificationToken);
    
    // If email is disabled, return with link for development
    if (!$config['enabled']) {
        return [
            'success' => false,
            'message' => 'Email sending is disabled (development mode)',
            'link' => $verificationLink
        ];
    }
    
    // Check if PHPMailer classes are available
    $phpmailerClassExists = false;
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        $phpmailerClassExists = true;
    } elseif (class_exists('PHPMailer')) {
        $phpmailerClassExists = true;
    }
    
    if (!$phpmailerClassExists) {
        $checkPath = __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
        $exists = file_exists($checkPath) ? 'exists' : 'does not exist';
        $loaded = $phpmailerLoaded ? 'loaded' : 'not loaded';
        return [
            'success' => false,
            'message' => 'PHPMailer file ' . $exists . ' but class not found. File loaded: ' . $loaded . '. Path: ' . $checkPath,
            'link' => $verificationLink
        ];
    }
    
    // Create PHPMailer instance
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = $config['smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['smtp_username'];
        $mail->Password   = $config['smtp_password'];
        $mail->SMTPSecure = $config['smtp_encryption'];
        $mail->Port       = $config['smtp_port'];
        $mail->CharSet    = 'UTF-8';
        
        // Recipients
        $mail->setFrom($config['from_email'], $config['from_name']);
        $mail->addAddress($toEmail, $toName);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Verify Your Email - Lovejoy App';
        
        // Load email template
        $emailBody = getEmailVerificationTemplate($toName, $verificationLink);
        $mail->Body = $emailBody;
        $mail->AltBody = strip_tags($emailBody); // Plain text version
        
        $mail->send();
        
        return [
            'success' => true,
            'message' => 'Verification email sent successfully',
            'link' => $config['dev_show_link'] ? $verificationLink : null
        ];
    } catch (Exception $e) {
        // Log error
        error_log("Email sending failed: " . $mail->ErrorInfo);
        
        return [
            'success' => false,
            'message' => 'Failed to send email: ' . $mail->ErrorInfo,
            'link' => $verificationLink // Return link as fallback
        ];
    }
}

/**
 * Send 2FA verification code email
 * 
 * @param string $toEmail Recipient email address
 * @param string $toName Recipient name
 * @param string $code 6-digit verification code
 * @return array ['success' => bool, 'message' => string]
 */
function send2FACodeEmail($toEmail, $toName, $code) {
    global $phpmailerLoaded;
    $config = require __DIR__ . '/mail_config.php';
    
    // If email is disabled, return with code for development
    if (!$config['enabled']) {
        return [
            'success' => false,
            'message' => 'Email sending is disabled (development mode)',
            'code' => $code  // Return code for development testing
        ];
    }
    
    // Check if PHPMailer classes are available
    $phpmailerClassExists = false;
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        $phpmailerClassExists = true;
    } elseif (class_exists('PHPMailer')) {
        $phpmailerClassExists = true;
    }
    
    if (!$phpmailerClassExists) {
        return [
            'success' => false,
            'message' => 'PHPMailer not available',
            'code' => $code
        ];
    }
    
    // Create PHPMailer instance
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = $config['smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['smtp_username'];
        $mail->Password   = $config['smtp_password'];
        $mail->SMTPSecure = $config['smtp_encryption'];
        $mail->Port       = $config['smtp_port'];
        $mail->CharSet    = 'UTF-8';
        
        // Recipients
        $mail->setFrom($config['from_email'], $config['from_name']);
        $mail->addAddress($toEmail, $toName);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Your Login Verification Code - Lovejoy App';
        
        // Load email template
        $emailBody = get2FACodeEmailTemplate($toName, $code);
        $mail->Body = $emailBody;
        $mail->AltBody = "Your Lovejoy App verification code is: $code. This code will expire in 10 minutes.";
        
        $mail->send();
        
        return [
            'success' => true,
            'message' => '2FA code sent successfully',
            'code' => $config['dev_show_link'] ? $code : null
        ];
    } catch (Exception $e) {
        error_log("2FA email sending failed: " . $mail->ErrorInfo);
        
        return [
            'success' => false,
            'message' => 'Failed to send email: ' . $mail->ErrorInfo,
            'code' => $code // Return code as fallback for development
        ];
    }
}

/**
 * Get 2FA code email template
 * 
 * @param string $name User's name
 * @param string $code 6-digit verification code
 * @return string HTML email template
 */
function get2FACodeEmailTemplate($name, $code) {
    $html = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Login Verification Code</title>
    </head>
    <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f5f5f5;">
        <div style="background-color: #667eea; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
            <h1 style="color: #ffffff; margin: 0; font-size: 24px;">Lovejoy App</h1>
        </div>
        
        <div style="background-color: #ffffff; padding: 30px; border: 1px solid #e0e0e0; border-top: none; border-radius: 0 0 10px 10px;">
            <h2 style="color: #333; margin-top: 0;">🔐 Login Verification Code</h2>
            
            <p>Hello ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ',</p>
            
            <p>You are attempting to log in to your Lovejoy App account. Use the verification code below to complete your login:</p>
            
            <div style="text-align: center; margin: 30px 0;">
                <div style="display: inline-block; padding: 20px 40px; background-color: #f5f5f5; border: 2px solid #e0e0e0; border-radius: 10px;">
                    <span style="font-size: 32px; font-weight: bold; letter-spacing: 8px; color: #333333;">' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '</span>
                </div>
            </div>
            
            <p style="text-align: center; font-size: 14px; color: #666;">
                <strong>⏰ This code will expire in 10 minutes.</strong>
            </p>
            
            <p style="font-size: 12px; color: #999; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e0e0e0;">
                <strong>Security Notice:</strong> If you did not attempt to log in, someone may be trying to access your account. 
                Please change your password immediately and contact support if needed.
            </p>
        </div>
        
        <div style="text-align: center; margin-top: 20px; font-size: 12px; color: #999;">
            <p>© ' . date('Y') . ' Lovejoy App. All rights reserved.</p>
        </div>
    </body>
    </html>';
    
    return $html;
}

/**
 * Get email verification template
 * 
 * @param string $name User's name
 * @param string $verificationLink Email verification link
 * @return string HTML email template
 */
function getEmailVerificationTemplate($name, $verificationLink) {
    $html = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Email Verification</title>
    </head>
    <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f5f5f5;">
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
            <h1 style="color: #fff; margin: 0; font-size: 24px;">Lovejoy App</h1>
        </div>
        
        <div style="background: #fff; padding: 30px; border: 1px solid #e0e0e0; border-top: none; border-radius: 0 0 10px 10px;">
            <h2 style="color: #333; margin-top: 0;">Verify Your Email Address</h2>
            
            <p>Hello ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ',</p>
            
            <p>Thank you for registering with Lovejoy App! Please verify your email address to activate your account.</p>
            
            <p>Click the button below to verify your email:</p>
            
            <div style="text-align: center; margin: 30px 0;">
                <a href="' . htmlspecialchars($verificationLink, ENT_QUOTES, 'UTF-8') . '" 
                   style="display: inline-block; padding: 12px 30px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                          color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600;">
                    Verify Email
                </a>
            </div>
            
            <p style="font-size: 12px; color: #666; margin-top: 30px;">
                Or copy and paste this link into your browser:<br>
                <a href="' . htmlspecialchars($verificationLink, ENT_QUOTES, 'UTF-8') . '" style="color: #667eea; word-break: break-all;">
                    ' . htmlspecialchars($verificationLink, ENT_QUOTES, 'UTF-8') . '
                </a>
            </p>
            
            <p style="font-size: 12px; color: #999; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e0e0e0;">
                <strong>Important:</strong> This verification link will expire in 24 hours. If you did not create an account, 
                please ignore this email or contact support if you have concerns.
            </p>
        </div>
        
        <div style="text-align: center; margin-top: 20px; font-size: 12px; color: #999;">
            <p>© ' . date('Y') . ' Lovejoy App. All rights reserved.</p>
        </div>
    </body>
    </html>';
    
    return $html;
}

