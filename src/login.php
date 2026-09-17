<?php
session_start();
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/captcha.php';
require_once __DIR__ . '/includes/mailer.php';

// =====================================================
// Account Lockout Configuration
// =====================================================
define('MAX_LOGIN_ATTEMPTS', 5);        // Maximum failed attempts before lockout
define('LOCKOUT_DURATION', 15 * 60);    // Lockout duration in seconds (15 minutes)

// =====================================================
// 2FA Configuration
// =====================================================
define('TWO_FA_CODE_LENGTH', 6);        // 6-digit code
define('TWO_FA_EXPIRY', 10 * 60);       // Code expires in 10 minutes

// =====================================================
// Failure message
// =====================================================
// One message for every credential failure, so the response is the same
// whether or not the address belongs to an account.
define('LOGIN_FAILED_MESSAGE', 'Invalid email or password');

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];  // Array to store field-specific validation errors
$lockoutMessage = ''; // Special message for account lockout

// Handle CAPTCHA refresh (AJAX request)
if (isset($_GET['refresh_captcha']) && $_GET['refresh_captcha'] === '1') {
    header('Content-Type: application/json');
    $newQuestion = refreshCaptcha();
    echo json_encode(['question' => $newQuestion]);
    exit;
}

/**
 * Check if account is currently locked
 * @param array $user User data from database
 * @return array ['locked' => bool, 'remaining_minutes' => int]
 */
function checkAccountLockout($user) {
    if (empty($user['lockout_until'])) {
        return ['locked' => false, 'remaining_minutes' => 0];
    }
    
    $lockoutUntil = strtotime($user['lockout_until']);
    $now = time();
    
    if ($lockoutUntil > $now) {
        $remainingSeconds = $lockoutUntil - $now;
        $remainingMinutes = ceil($remainingSeconds / 60);
        return ['locked' => true, 'remaining_minutes' => $remainingMinutes];
    }
    
    return ['locked' => false, 'remaining_minutes' => 0];
}

/**
 * Record failed login attempt and potentially lock account
 * @param PDO $pdo Database connection
 * @param int $userId User ID
 * @param int $currentAttempts Current number of failed attempts
 * @return array ['locked' => bool, 'attempts_remaining' => int]
 */
function recordFailedAttempt($pdo, $userId, $currentAttempts) {
    $newAttempts = $currentAttempts + 1;
    
    if ($newAttempts >= MAX_LOGIN_ATTEMPTS) {
        // Lock the account
        $lockoutUntil = date('Y-m-d H:i:s', time() + LOCKOUT_DURATION);
        $stmt = $pdo->prepare(
            'UPDATE users SET 
                failed_login_attempts = :attempts,
                lockout_until = :lockout_until,
                last_failed_login = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'attempts' => $newAttempts,
            'lockout_until' => $lockoutUntil,
            'id' => $userId
        ]);
        return ['locked' => true, 'attempts_remaining' => 0];
    } else {
        // Just increment the counter
        $stmt = $pdo->prepare(
            'UPDATE users SET 
                failed_login_attempts = :attempts,
                last_failed_login = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'attempts' => $newAttempts,
            'id' => $userId
        ]);
        return ['locked' => false, 'attempts_remaining' => MAX_LOGIN_ATTEMPTS - $newAttempts];
    }
}

/**
 * Reset login attempts on successful login
 * @param PDO $pdo Database connection
 * @param int $userId User ID
 */
function resetLoginAttempts($pdo, $userId) {
    $stmt = $pdo->prepare(
        'UPDATE users SET 
            failed_login_attempts = 0,
            lockout_until = NULL,
            last_failed_login = NULL
         WHERE id = :id'
    );
    $stmt->execute(['id' => $userId]);
}

/**
 * Generate and store 2FA code
 * @param PDO $pdo Database connection
 * @param int $userId User ID
 * @return string The generated 6-digit code
 */
function generate2FACode($pdo, $userId) {
    // Generate random 6-digit code
    $code = str_pad(random_int(0, 999999), TWO_FA_CODE_LENGTH, '0', STR_PAD_LEFT);
    $expires = date('Y-m-d H:i:s', time() + TWO_FA_EXPIRY);
    
    // Store in database
    $stmt = $pdo->prepare(
        'UPDATE users SET two_fa_code = ?, two_fa_expires = ? WHERE id = ?'
    );
    $stmt->execute([$code, $expires, $userId]);
    
    return $code;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // CSRF Protection
    if (!verifyCSRFToken()) {
        $errors['csrf'] = 'Invalid security token. Please try again.';
    }
    $email    = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';

    // 1) Required fields validation
    if ($email === '') {
        $errors['email'] = 'Email is required';
    }
    if ($password === '') {
        $errors['password'] = 'Password is required';
    }

    // 2) Email format validation
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid email format';
    }

    // 3) CAPTCHA validation
    $captchaAnswer = isset($_POST['captcha_answer']) ? trim($_POST['captcha_answer']) : '';
    if ($captchaAnswer === '') {
        $errors['captcha'] = 'Please solve the CAPTCHA';
    } elseif (!validateCaptcha($captchaAnswer)) {
        $errors['captcha'] = 'Incorrect CAPTCHA answer. Please try again.';
        // Generate new CAPTCHA for retry
        generateCaptcha();
    }

    // 4) Check credentials if no validation errors
    if (empty($errors)) {
        // Fetch user with lockout fields
        $stmt = $pdo->prepare(
            "SELECT id, email, password_hash, full_name, phone, is_admin, email_verified,
                    failed_login_attempts, lockout_until, last_failed_login 
             FROM users WHERE email = ? LIMIT 1"
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // =====================================================
            // ACCOUNT LOCKOUT CHECK
            // =====================================================
            $lockoutStatus = checkAccountLockout($user);
            
            if ($lockoutStatus['locked']) {
                // Account is locked - show lockout message
                $lockoutMessage = "Account is temporarily locked due to too many failed login attempts. Please try again in {$lockoutStatus['remaining_minutes']} minute(s).";
                $errors['lockout'] = $lockoutMessage;
            } else {
                // Account not locked - verify password
                if (password_verify($password, $user['password_hash'])) {
                    // Check if email is verified
                    if (isset($user['email_verified']) && $user['email_verified'] == 0) {
                        $errors['email'] = 'Please verify your email address before logging in. Check your email for the verification link.';
                    } else {
                        // =====================================================
                        // PASSWORD CORRECT - Now initiate 2FA
                        // =====================================================
                        
                        // Reset failed login attempts
                        resetLoginAttempts($pdo, $user['id']);
                        
                        // Generate 2FA code
                        $twoFACode = generate2FACode($pdo, $user['id']);
                        
                        // Send 2FA code via email
                        $emailResult = send2FACodeEmail($user['email'], $user['full_name'], $twoFACode);
                        
                        // Store pending 2FA data in session
                        $_SESSION['pending_2fa_user_id'] = $user['id'];
                        $_SESSION['pending_2fa_email'] = $user['email'];
                        $_SESSION['pending_2fa_fullname'] = $user['full_name'];
                        $_SESSION['pending_2fa_phone'] = $user['phone'];
                        $_SESSION['pending_2fa_is_admin'] = $user['is_admin'];
                        
                        // If email sending failed, store code for development testing
                        if (!$emailResult['success'] && isset($emailResult['code'])) {
                            $_SESSION['dev_2fa_code'] = $emailResult['code'];
                        }
                        
                        // Redirect to 2FA verification page
                        header('Location: verify_2fa.php');
                        exit;
                    }
                } else {
                    // =====================================================
                    // LOGIN FAILED - Record failed attempt
                    // =====================================================
                    $currentAttempts = (int)($user['failed_login_attempts'] ?? 0);
                    $result = recordFailedAttempt($pdo, $user['id'], $currentAttempts);
                    
                    if ($result['locked']) {
                        $lockoutMinutes = ceil(LOCKOUT_DURATION / 60);
                        $lockoutMessage = "Too many failed login attempts. Your account has been locked for {$lockoutMinutes} minutes.";
                        $errors['lockout'] = $lockoutMessage;
                    } else {
                        // The message must not depend on whether the account
                        // exists. An attempts-remaining counter can only be
                        // computed for a row that exists, so printing it here
                        // told an attacker which addresses are registered.
                        $errors['password'] = LOGIN_FAILED_MESSAGE;
                    }
                }
            }
        } else {
            // No such account. Identical wording, and identical placement, to
            // the wrong-password case above: a second error rendered against
            // the email field would be a tell in itself.
            $errors['password'] = LOGIN_FAILED_MESSAGE;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Lovejoy App</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: linear-gradient(135deg,rgb(32, 70, 240) 0%,rgba(3, 28, 252, 0.71) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-card {
            width: 100%;
            max-width: 440px;
            background: #fff;
            border-radius: 10px;
            padding: 40px;
            box-shadow: 0 15px 35px rgba(0,0,0,.2);
        }

        h1 {
            font-size: 26px;
            text-align: center;
            color: #333;
            margin-bottom: 25px;
        }

        .form-group { margin-bottom: 18px; }
        label {
            display: block;
            font-weight: 600;
            color: #555;
            margin-bottom: 6px;
        }

        input {
            width: 100%;
            padding: 11px 14px;
            border-radius: 6px;
            border: 2px solid #e0e0e0;
            font-size: 14px;
            transition: border-color .2s;
        }

        input:focus {
            border-color: #6a73e6;
            outline: none;
        }

        button {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            color: #fff;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            cursor: pointer;
            transition: transform .2s, box-shadow .2s;
        }

        button:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 18px rgba(102, 126, 234, .35);
        }

        .field-error {
            color: #c33;
            font-size: 13px;
            margin-top: 5px;
            display: block;
        }

        input.error {
            border-color: #c33;
        }

        .register-link {
            text-align: center;
            margin-top: 16px;
            font-size: 14px;
            color: #666;
        }

        .register-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }

        .register-link a:hover {
            text-decoration: underline;
        }

        .flash-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 6px;
            padding: 12px 16px;
            margin-bottom: 20px;
            color: #155724;
            font-size: 14px;
        }

        /* Account Lockout Warning Style */
        .form-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-left: 4px solid #dc3545;
            border-radius: 6px;
            padding: 14px 16px;
            margin-bottom: 20px;
            color: #721c24;
            font-size: 14px;
        }
        .lockout-warning {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-left: 4px solid #ff9800;
            border-radius: 6px;
            padding: 14px 16px;
            margin-bottom: 20px;
            color: #856404;
            font-size: 14px;
        }

        .lockout-warning strong {
            display: block;
            margin-bottom: 4px;
            color: #664d03;
        }

        .lockout-warning.locked {
            background: #f8d7da;
            border-color: #f5c6cb;
            border-left-color: #dc3545;
            color: #721c24;
        }

        .lockout-warning.locked strong {
            color: #491217;
        }

        /* 2FA Info Box */
        .info-box {
            background: #e7f3ff;
            border: 1px solid #b6d4fe;
            border-left: 4px solid #0d6efd;
            border-radius: 6px;
            padding: 12px 16px;
            margin-bottom: 20px;
            color: #084298;
            font-size: 13px;
        }

        .info-box strong {
            display: block;
            margin-bottom: 4px;
        }
    </style>
    <script>
        // CAPTCHA refresh functionality
        document.addEventListener('DOMContentLoaded', function() {
            const refreshBtn = document.getElementById('refresh-captcha');
            if (refreshBtn) {
                refreshBtn.addEventListener('click', function() {
                    fetch('login.php?refresh_captcha=1')
                        .then(response => response.json())
                        .then(data => {
                            const captchaDisplay = document.querySelector('#captcha_answer').previousElementSibling;
                            if (captchaDisplay) {
                                captchaDisplay.textContent = data.question;
                            }
                            document.getElementById('captcha_answer').value = '';
                            document.getElementById('captcha_answer').focus();
                        })
                        .catch(error => {
                            console.error('Error refreshing CAPTCHA:', error);
                            location.reload(); // Fallback: reload page
                        });
                });
            }
        });
    </script>
</head>
<body>
    <div class="login-card">
        <h1>Login</h1>

        <div class="info-box">
            <strong>🔐 Two-Factor Authentication Enabled</strong>
            After entering your credentials, a verification code will be sent to your email.
        </div>

        <?php if (!empty($_SESSION['flash_success'])): ?>
            <div class="flash-success">
                <?php echo htmlspecialchars($_SESSION['flash_success']); ?>
            </div>
            <?php unset($_SESSION['flash_success']); ?>
        <?php endif; ?>

        <?php if (!empty($errors['csrf'])): ?>
            <div class="form-error">
                <?php echo htmlspecialchars($errors['csrf']); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors['lockout'])): ?>
            <div class="lockout-warning locked">
                <strong>🔒 Account Locked</strong>
                <?php echo htmlspecialchars($errors['lockout']); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                    class="<?php echo isset($errors['email']) ? 'error' : ''; ?>"
                    required
                >
                <?php if (isset($errors['email']) && !isset($errors['lockout'])): ?>
                    <span class="field-error"><?php echo htmlspecialchars($errors['email']); ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    class="<?php echo isset($errors['password']) ? 'error' : ''; ?>"
                    required
                >
                <?php if (isset($errors['password']) && !isset($errors['lockout'])): ?>
                    <span class="field-error"><?php echo htmlspecialchars($errors['password']); ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="captcha_answer">Security Check (CAPTCHA)</label>
                <div style="display: flex; gap: 10px; align-items: center; margin-bottom: 8px;">
                    <div style="font-size: 20px; font-weight: 700; color: #667eea; padding: 10px 16px; background: #f3f4ff; border-radius: 6px; min-width: 120px; text-align: center; border: 2px solid #d6dafc;">
                        <?php echo htmlspecialchars(getCaptchaQuestion()); ?>
                    </div>
                    <input
                        type="text"
                        id="captcha_answer"
                        name="captcha_answer"
                        placeholder="Enter your answer"
                        class="<?php echo isset($errors['captcha']) ? 'error' : ''; ?>"
                        required
                        style="flex: 1; padding: 11px 14px; border-radius: 6px; border: 2px solid #e0e0e0; font-size: 14px;"
                    >
                </div>
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                    <button 
                        type="button" 
                        id="refresh-captcha"
                        style="padding: 6px 12px; background: #f3f4ff; border: 2px solid #d6dafc; border-radius: 6px; cursor: pointer; font-size: 13px; color: #667eea;"
                        title="Get a new question"
                    >
                        🔄 Refresh
                    </button>
                    <?php if (isset($errors['captcha'])): ?>
                        <span class="field-error"><?php echo htmlspecialchars($errors['captcha']); ?></span>
                    <?php else: ?>
                        <div class="hint" style="font-size: 12px; color: #888;">Solve the math problem to verify you're human</div>
                    <?php endif; ?>
                </div>
            </div>

            <?php echo csrfField(); ?>

            <button type="submit">Login</button>
        </form>

        <div class="register-link">
            <a href="password_reset_request.php">Forgot password?</a><br>
            Don't have an account? <a href="register.php">Register here</a>
        </div>
    </div>
</body>
</html>
