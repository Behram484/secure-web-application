<?php

session_start();
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/csrf.php';

$errors = [];
$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$validToken = false;
$userId = null;

// Helper function: find user by token
function findUserByToken($pdo, $token) {
    $stmt = $pdo->prepare(
        'SELECT id, email, reset_expires 
         FROM users 
         WHERE reset_token = ? 
           AND reset_expires IS NOT NULL 
           AND reset_expires > NOW()
         LIMIT 1'
    );
    $stmt->execute([$token]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($token === '') {
        $errors['token'] = 'Invalid or missing reset token.';
    } else {
        $user = findUserByToken($pdo, $token);
        if ($user) {
            $validToken = true;
            $userId = $user['id'];
        } else {
            $errors['token'] = 'This reset link is invalid or has expired.';
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    if (!verifyCSRFToken()) {
        $errors['csrf'] = 'Invalid security token. Please try again.';
    }
    
    // POST: read token from form
    $token = isset($_POST['token']) ? trim($_POST['token']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    $passwordConfirm = isset($_POST['password_confirm']) ? trim($_POST['password_confirm']) : '';

    if ($token === '') {
        $errors['token'] = 'Invalid reset token.';
    } else {
        $user = findUserByToken($pdo, $token);
        if ($user) {
            $validToken = true;
            $userId = $user['id'];
        } else {
            $errors['token'] = 'This reset link is invalid or has expired.';
        }
    }

    // Only validate password if token is valid
    if ($validToken) {
        if ($password === '') {
            $errors['password'] = 'Password is required';
        } elseif (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters long';
        } elseif (!preg_match('/^(?=.*[A-Za-z])(?=.*\d).{8,}$/', $password)) {
            $errors['password'] = 'Password must contain both letters and numbers';
        }

        if ($passwordConfirm === '') {
            $errors['password_confirm'] = 'Please confirm your password';
        } elseif ($password !== $passwordConfirm) {
            $errors['password_confirm'] = 'Passwords do not match';
        }

        // All passed: update password
        if (empty($errors)) {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare(
                'UPDATE users 
                 SET password_hash = :hash,
                     reset_token = NULL,
                     reset_expires = NULL
                 WHERE id = :id'
            );
            $stmt->execute([
                'hash' => $passwordHash,
                'id'   => $userId,
            ]);

            $_SESSION['flash_success'] = 'Your password has been reset. You can now log in with your new password.';
            header('Location: login.php');
            exit;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Set New Password - Lovejoy App</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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

        .card {
            width: 100%;
            max-width: 440px;
            background: #fff;
            border-radius: 10px;
            padding: 40px;
            box-shadow: 0 15px 35px rgba(0,0,0,.2);
        }

        h1 {
            font-size: 24px;
            text-align: center;
            color: #333;
            margin-bottom: 20px;
        }

        p.intro {
            font-size: 14px;
            color: #666;
            margin-bottom: 20px;
            text-align: center;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            font-size: 14px;
            margin-bottom: 15px;
        }

        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
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

        input.error { border-color: #c33; }

        .field-error {
            color: #c33;
            font-size: 13px;
            margin-top: 5px;
            display: block;
        }

        .hint {
            font-size: 12px;
            color: #888;
            margin-top: 4px;
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

        .extra {
            margin-top: 16px;
            font-size: 13px;
            text-align: center;
            color: #666;
        }

        .extra a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }

        .password-strength-bar {
            height: 6px;
            border-radius: 3px;
            margin-top: 6px;
            transition: all 0.3s ease;
        }

        .strength-weak {
            background: #e55353;
            width: 33%;
        }

        .strength-medium {
            background: #ffa500;
            width: 66%;
        }

        .strength-strong {
            background: #28a745;
            width: 100%;
        }

        .strength-very-strong {
            background: #155724;
            width: 100%;
        }

        .strength-text {
            font-size: 12px;
            font-weight: 600;
            margin-top: 4px;
        }

        .strength-weak-text { color: #e55353; }
        .strength-medium-text { color: #ffa500; }
        .strength-strong-text { color: #28a745; }
        .strength-very-strong-text { color: #155724; }
    </style>
    <script>
        function calculatePasswordEntropy(password) {
            if (!password) return 0;
            
            let charsetSize = 0;
            let hasLower = /[a-z]/.test(password);
            let hasUpper = /[A-Z]/.test(password);
            let hasDigit = /[0-9]/.test(password);
            let hasSpecial = /[^a-zA-Z0-9]/.test(password);
            
            if (hasLower) charsetSize += 26;
            if (hasUpper) charsetSize += 26;
            if (hasDigit) charsetSize += 10;
            if (hasSpecial) charsetSize += 32;
            
            if (charsetSize === 0) return 0;
            
            return Math.log2(Math.pow(charsetSize, password.length));
        }

        function checkPasswordStrength(password) {
            const strengthDiv = document.getElementById('password-strength');
            const entropyDiv = document.getElementById('password-entropy');
            
            if (!password) {
                strengthDiv.innerHTML = '';
                entropyDiv.innerHTML = '';
                return;
            }

            let strength = 0;
            let feedback = [];

            if (password.length >= 8) strength += 1; else feedback.push('At least 8 characters');
            if (/[a-z]/.test(password)) strength += 1; else feedback.push('Add lowercase letters');
            if (/[A-Z]/.test(password)) strength += 1; else feedback.push('Add uppercase letters');
            if (/[0-9]/.test(password)) strength += 1; else feedback.push('Add numbers');
            if (/[^a-zA-Z0-9]/.test(password)) strength += 1; else feedback.push('Add special characters');
            if (password.length >= 12) strength += 1;

            const entropy = calculatePasswordEntropy(password);

            let strengthClass = '';
            let strengthText = '';
            let strengthBarClass = '';

            if (strength <= 2) {
                strengthClass = 'strength-weak-text';
                strengthText = 'Weak';
                strengthBarClass = 'strength-weak';
            } else if (strength <= 4) {
                strengthClass = 'strength-medium-text';
                strengthText = 'Medium';
                strengthBarClass = 'strength-medium';
            } else if (strength <= 5) {
                strengthClass = 'strength-strong-text';
                strengthText = 'Strong';
                strengthBarClass = 'strength-strong';
            } else {
                strengthClass = 'strength-very-strong-text';
                strengthText = 'Very Strong';
                strengthBarClass = 'strength-very-strong';
            }

            strengthDiv.innerHTML = `
                <div class="password-strength-bar ${strengthBarClass}"></div>
                <div class="strength-text ${strengthClass}">Password Strength: ${strengthText}</div>
                ${feedback.length > 0 ? '<div style="font-size: 11px; color: #888; margin-top: 4px;">Suggestions: ' + feedback.slice(0, 2).join(', ') + '</div>' : ''}
            `;

            let entropyLevel = '';
            if (entropy < 28) entropyLevel = 'Low';
            else if (entropy < 35) entropyLevel = 'Medium';
            else if (entropy < 50) entropyLevel = 'High';
            else entropyLevel = 'Very High';

            entropyDiv.innerHTML = `Password Entropy: ${entropy.toFixed(1)} bits (${entropyLevel})`;
        }
    </script>
</head>
<body>
    <div class="card">
        <h1>Set a new password</h1>
        <p class="intro">Choose a strong password and keep it safe.</p>

        <?php if (isset($errors['token'])): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($errors['token']); ?>
            </div>
        <?php endif; ?>

        <?php if ($validToken): ?>
            <form method="POST" action="password_reset.php">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

                <div class="form-group">
                    <label for="password">New Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="<?php echo isset($errors['password']) ? 'error' : ''; ?>"
                        required
                        oninput="checkPasswordStrength(this.value)"
                    >
                    <div class="hint">At least 8 characters, letters + numbers</div>
                    <?php if (isset($errors['password'])): ?>
                        <span class="field-error"><?php echo htmlspecialchars($errors['password']); ?></span>
                    <?php endif; ?>
                    <div id="password-strength" style="margin-top: 8px;"></div>
                    <div id="password-entropy" style="font-size: 12px; color: #666; margin-top: 4px;"></div>
                </div>

                <div class="form-group">
                    <label for="password_confirm">Confirm Password</label>
                    <input
                        type="password"
                        id="password_confirm"
                        name="password_confirm"
                        class="<?php echo isset($errors['password_confirm']) ? 'error' : ''; ?>"
                        required
                    >
                    <?php if (isset($errors['password_confirm'])): ?>
                        <span class="field-error"><?php echo htmlspecialchars($errors['password_confirm']); ?></span>
                    <?php endif; ?>
                </div>

                <?php echo csrfField(); ?>

                <button type="submit">Update Password</button>
            </form>
        <?php else: ?>
            <div class="extra">
                <a href="password_reset_request.php">Request a new reset link</a> ·
                <a href="login.php">Back to login</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

