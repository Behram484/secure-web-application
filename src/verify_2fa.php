<?php
session_start();
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/csrf.php';

// =====================================================
// 2FA Verification Page
// =====================================================
// User must have passed password verification to access this page
// Session should contain: pending_2fa_user_id, pending_2fa_email

// Check if user has pending 2FA verification
if (!isset($_SESSION['pending_2fa_user_id']) || !isset($_SESSION['pending_2fa_email'])) {
    // No pending 2FA, redirect to login
    header('Location: login.php');
    exit;
}

$errors = [];
$pendingEmail = $_SESSION['pending_2fa_email'];
$pendingUserId = $_SESSION['pending_2fa_user_id'];

// Get development code if available (for testing when email is disabled)
$devCode = $_SESSION['dev_2fa_code'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    if (!verifyCSRFToken()) {
        $errors['csrf'] = 'Invalid security token. Please try again.';
    }
    
    $enteredCode = isset($_POST['code']) ? trim($_POST['code']) : '';
    
    // Validate code format (6 digits)
    if ($enteredCode === '') {
        $errors['code'] = 'Please enter the verification code';
    } elseif (!preg_match('/^\d{6}$/', $enteredCode)) {
        $errors['code'] = 'Verification code must be 6 digits';
    }
    
    if (empty($errors)) {
        // Fetch user with 2FA data
        $stmt = $pdo->prepare(
            "SELECT id, email, full_name, phone, is_admin, two_fa_code, two_fa_expires 
             FROM users WHERE id = ? LIMIT 1"
        );
        $stmt->execute([$pendingUserId]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Check if code has expired
            if ($user['two_fa_expires'] && strtotime($user['two_fa_expires']) < time()) {
                $errors['code'] = 'Verification code has expired. Please login again to get a new code.';
                // Clear the pending 2FA session
                unset($_SESSION['pending_2fa_user_id']);
                unset($_SESSION['pending_2fa_email']);
                unset($_SESSION['pending_2fa_fullname']);
                unset($_SESSION['dev_2fa_code']);
            } elseif ($user['two_fa_code'] === $enteredCode) {
                // =====================================================
                // 2FA VERIFICATION SUCCESSFUL
                // =====================================================
                
                // Clear 2FA code from database
                $clearStmt = $pdo->prepare(
                    "UPDATE users SET two_fa_code = NULL, two_fa_expires = NULL WHERE id = ?"
                );
                $clearStmt->execute([$user['id']]);
                
                // Clear pending 2FA session data
                unset($_SESSION['pending_2fa_user_id']);
                unset($_SESSION['pending_2fa_email']);
                unset($_SESSION['pending_2fa_fullname']);
                unset($_SESSION['pending_2fa_phone']);
                unset($_SESSION['pending_2fa_is_admin']);
                unset($_SESSION['dev_2fa_code']);
                
                // Set actual login session
                $_SESSION['user_id']        = $user['id'];
                $_SESSION['user_email']     = htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8');
                $_SESSION['user_fullname']  = htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8');
                $_SESSION['user_phone']     = htmlspecialchars($user['phone'], ENT_QUOTES, 'UTF-8');
                $_SESSION['user_is_admin']  = (int)$user['is_admin'];
                
                // Redirect to dashboard
                header('Location: dashboard.php');
                exit;
            } else {
                $errors['code'] = 'Invalid verification code. Please try again.';
            }
        } else {
            $errors['code'] = 'User not found. Please login again.';
            // Clear the pending 2FA session
            unset($_SESSION['pending_2fa_user_id']);
            unset($_SESSION['pending_2fa_email']);
        }
    }
}

// Handle resend code request
if (isset($_GET['resend']) && $_GET['resend'] === '1') {
    require_once __DIR__ . '/includes/mailer.php';
    
    // Generate new 6-digit code
    $newCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $codeExpires = date('Y-m-d H:i:s', time() + (10 * 60)); // 10 minutes
    
    // Update database
    $stmt = $pdo->prepare(
        "UPDATE users SET two_fa_code = ?, two_fa_expires = ? WHERE id = ?"
    );
    $stmt->execute([$newCode, $codeExpires, $pendingUserId]);
    
    // Send email
    $fullname = $_SESSION['pending_2fa_fullname'] ?? $pendingEmail;
    $emailResult = send2FACodeEmail($pendingEmail, $fullname, $newCode);
    
    // Store dev code if email sending is disabled
    if (!$emailResult['success'] && isset($emailResult['code'])) {
        $_SESSION['dev_2fa_code'] = $emailResult['code'];
        $devCode = $emailResult['code'];
    }
    
    $_SESSION['flash_resend'] = 'A new verification code has been sent to your email.';
    header('Location: verify_2fa.php');
    exit;
}

// Mask email for display (e.g., b***@outlook.com)
function maskEmail($email) {
    $parts = explode('@', $email);
    if (count($parts) !== 2) return $email;
    
    $name = $parts[0];
    $domain = $parts[1];
    
    if (strlen($name) <= 2) {
        $masked = $name[0] . '***';
    } else {
        $masked = $name[0] . str_repeat('*', strlen($name) - 2) . substr($name, -1);
    }
    
    return $masked . '@' . $domain;
}

$maskedEmail = maskEmail($pendingEmail);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Login - Lovejoy App</title>
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

        .verify-card {
            width: 100%;
            max-width: 440px;
            background: #fff;
            border-radius: 10px;
            padding: 40px;
            box-shadow: 0 15px 35px rgba(0,0,0,.2);
            text-align: center;
        }

        .icon-wrapper {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 36px;
        }

        h1 {
            font-size: 24px;
            color: #333;
            margin-bottom: 10px;
        }

        .subtitle {
            color: #666;
            font-size: 14px;
            margin-bottom: 25px;
            line-height: 1.5;
        }

        .email-highlight {
            color: #667eea;
            font-weight: 600;
        }

        .form-group { margin-bottom: 18px; }

        .code-input {
            width: 100%;
            padding: 16px;
            border-radius: 8px;
            border: 2px solid #e0e0e0;
            font-size: 24px;
            text-align: center;
            letter-spacing: 8px;
            font-weight: 600;
            transition: border-color .2s;
        }

        .code-input:focus {
            border-color: #667eea;
            outline: none;
        }

        .code-input.error {
            border-color: #c33;
        }

        .code-input::placeholder {
            letter-spacing: 2px;
            font-size: 16px;
        }

        button {
            width: 100%;
            padding: 14px;
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
            margin-top: 8px;
            display: block;
        }

        .resend-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            font-size: 14px;
            color: #666;
        }

        .resend-link {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }

        .resend-link:hover {
            text-decoration: underline;
        }

        .back-link {
            display: block;
            margin-top: 15px;
            color: #888;
            text-decoration: none;
            font-size: 13px;
        }

        .back-link:hover {
            color: #667eea;
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

        .dev-code {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 6px;
            padding: 12px 16px;
            margin-bottom: 20px;
            color: #856404;
            font-size: 13px;
        }

        .dev-code strong {
            display: block;
            font-size: 24px;
            letter-spacing: 4px;
            margin-top: 5px;
            color: #664d03;
        }

        .timer {
            font-size: 13px;
            color: #888;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="verify-card">
        <div class="icon-wrapper">
            🔐
        </div>
        
        <h1>Two-Factor Authentication</h1>
        <p class="subtitle">
            We've sent a 6-digit verification code to<br>
            <span class="email-highlight"><?php echo htmlspecialchars($maskedEmail); ?></span>
        </p>

        <?php if (!empty($_SESSION['flash_resend'])): ?>
            <div class="flash-success">
                <?php echo htmlspecialchars($_SESSION['flash_resend']); ?>
            </div>
            <?php unset($_SESSION['flash_resend']); ?>
        <?php endif; ?>

        <?php if ($devCode): ?>
            <div class="dev-code">
                <span>🔧 Development Mode - Your code:</span>
                <strong><?php echo htmlspecialchars($devCode); ?></strong>
            </div>
        <?php endif; ?>

        <form method="POST" action="verify_2fa.php">
            <div class="form-group">
                <input
                    type="text"
                    name="code"
                    class="code-input <?php echo isset($errors['code']) ? 'error' : ''; ?>"
                    placeholder="000000"
                    maxlength="6"
                    pattern="\d{6}"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    autofocus
                    required
                >
                <?php if (isset($errors['code'])): ?>
                    <span class="field-error"><?php echo htmlspecialchars($errors['code']); ?></span>
                <?php endif; ?>
            </div>

            <p class="timer">Code expires in 10 minutes</p>

            <?php echo csrfField(); ?>

            <button type="submit">Verify & Login</button>
        </form>

        <div class="resend-section">
            Didn't receive the code? 
            <a href="verify_2fa.php?resend=1" class="resend-link">Resend Code</a>
        </div>

        <a href="login.php" class="back-link">← Back to Login</a>
    </div>

    <script>
        // Auto-focus and format code input
        const codeInput = document.querySelector('.code-input');
        if (codeInput) {
            codeInput.addEventListener('input', function(e) {
                // Only allow digits
                this.value = this.value.replace(/\D/g, '');
                
                // Auto-submit when 6 digits entered
                if (this.value.length === 6) {
                    // Optional: auto-submit
                    // this.form.submit();
                }
            });
        }
    </script>
</body>
</html>

