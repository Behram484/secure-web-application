<?php
session_start();
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/includes/captcha.php';

$errors = [];  // Array to store field-specific validation errors
$registrationSuccess = false;
$verificationLink = '';
$registeredEmail = ''; // Store registered email for success message

// Handle CAPTCHA refresh (AJAX request)
if (isset($_GET['refresh_captcha']) && $_GET['refresh_captcha'] === '1') {
    header('Content-Type: application/json');
    $newQuestion = refreshCaptcha();
    echo json_encode(['question' => $newQuestion]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // CSRF Protection
    if (!verifyCSRFToken()) {
        $errors['csrf'] = 'Invalid security token. Please try again.';
    }
    $email    = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    $fullname = isset($_POST['fullname']) ? trim($_POST['fullname']) : '';
    $phone    = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $securityQuestion = isset($_POST['security_question']) ? trim($_POST['security_question']) : '';
    $securityAnswer = isset($_POST['security_answer']) ? trim($_POST['security_answer']) : '';

    // 1) Required fields validation
    if ($fullname === '') {
        $errors['fullname'] = 'Fullname is required';
    }
    if ($email === '') {
        $errors['email'] = 'Email is required';
    }
    if ($password === '') {
        $errors['password'] = 'Password is required';
    }
    if ($phone === '') {
        $errors['phone'] = 'Phone is required';
    }
    if ($securityQuestion === '') {
        $errors['security_question'] = 'Security question is required';
    }
    if ($securityAnswer === '') {
        $errors['security_answer'] = 'Security answer is required';
    } elseif (strlen($securityAnswer) < 3) {
        $errors['security_answer'] = 'Security answer must be at least 3 characters';
    }

    // 2) Email format validation
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid email format';
    }

    // 3) Password strength validation (minimum 8 characters, must contain letters and numbers)
    if ($password !== '' && strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters long';
    } elseif ($password !== '' && !preg_match('/^(?=.*[A-Za-z])(?=.*\d).{8,}$/', $password)) {
        $errors['password'] = 'Password must contain both letters and numbers';
    }

    // 4) UK phone number format validation (11 digits starting with 07)
    if ($phone !== '' && !preg_match('/^07\d{9}$/', $phone)) {
        $errors['phone'] = 'Invalid phone format (must be 11 digits starting with 07)';
    }

    // 5) Check if email already exists in database
    if (empty($errors['email'])) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $existing_user = $stmt->fetch();

        if ($existing_user) {
            $errors['email'] = "This email is already registered";
        }
    }

    // 6) CAPTCHA validation
    $captchaAnswer = isset($_POST['captcha_answer']) ? trim($_POST['captcha_answer']) : '';
    if ($captchaAnswer === '') {
        $errors['captcha'] = 'Please solve the CAPTCHA';
    } elseif (!validateCaptcha($captchaAnswer)) {
        $errors['captcha'] = 'Incorrect CAPTCHA answer. Please try again.';
        // Generate new CAPTCHA for retry
        generateCaptcha();
    }

    // 7) If no errors, insert user data into database
    if (empty($errors)) {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        // Generate verification token
        $verificationToken = bin2hex(random_bytes(32)); // 64 hex characters
        $verificationExpires = date('Y-m-d H:i:s', time() + (24 * 3600)); // 24 hours
        
        // Hash security answer (similar to password hashing)
        $securityAnswerHash = password_hash(strtolower(trim($securityAnswer)), PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare(
            'INSERT INTO users (email, password_hash, full_name, phone, is_admin, email_verified, verification_token, verification_token_expires, security_question, security_answer_hash)
             VALUES (:email, :password_hash, :full_name, :phone, 0, 0, :verification_token, :verification_expires, :security_question, :security_answer_hash)'
        );
        $stmt->execute([
            'email'                 => $email,
            'password_hash'         => $passwordHash,
            'full_name'             => $fullname,
            'phone'                 => $phone,
            'verification_token'    => $verificationToken,
            'verification_expires'  => $verificationExpires,
            'security_question'     => $securityQuestion,
            'security_answer_hash'  => $securityAnswerHash,
        ]);

        $user_id = $pdo->lastInsertId();

        // Send verification email
        $emailResult = sendEmailVerification($email, $fullname, $verificationToken);
        
        if ($emailResult['success']) {
            $registrationSuccess = true;
            if ($emailResult['link']) {
                $verificationLink = $emailResult['link'];
            }
        } else {
            // Email sending failed, but account is created
            // Store verification link for display
            $config = require __DIR__ . '/includes/mail_config.php';
            $verificationLink = $config['app_url'] . '/email_verify.php?token=' . urlencode($verificationToken);
            $registrationSuccess = true;
        }
        
        // Don't log in automatically - user must verify email first
        // Keep email for success message display, clear other fields
        $registeredEmail = $email; // Save email for success message
        $fullname = '';
        $phone = '';
        $email = ''; // Clear email from form
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Lovejoy App</title>
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

        .register-card {
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

        .errors {
            background: #fee;
            border: 1px solid #fcc;
            border-radius: 6px;
            padding: 12px 16px;
            margin-bottom: 20px;
        }

        .errors li {
            color: #c33;
            font-size: 14px;
            margin-bottom: 4px;
            list-style: none;
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

        input:focus, select:focus {
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

        .hint {
            font-size: 12px;
            color: #888;
            margin-top: 4px;
        }

        .field-error {
            color: #c33;
            font-size: 13px;
            margin-top: 5px;
            display: block;
        }

        input.error, select.error {
            border-color: #c33;
        }

        .login-link {
            text-align: center;
            margin-top: 16px;
            font-size: 14px;
            color: #666;
        }

        .login-link a {
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
            if (hasSpecial) charsetSize += 32; // Common special characters
            
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

            // Length check
            if (password.length >= 8) {
                strength += 1;
            } else {
                feedback.push('At least 8 characters');
            }

            // Has lowercase
            if (/[a-z]/.test(password)) {
                strength += 1;
            } else {
                feedback.push('Add lowercase letters');
            }

            // Has uppercase
            if (/[A-Z]/.test(password)) {
                strength += 1;
            } else {
                feedback.push('Add uppercase letters');
            }

            // Has numbers
            if (/[0-9]/.test(password)) {
                strength += 1;
            } else {
                feedback.push('Add numbers');
            }

            // Has special characters
            if (/[^a-zA-Z0-9]/.test(password)) {
                strength += 1;
            } else {
                feedback.push('Add special characters (!@#$%^&*)');
            }

            // Length bonus
            if (password.length >= 12) {
                strength += 1;
            }

            // Calculate entropy
            const entropy = calculatePasswordEntropy(password);

            // Determine strength level
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

            // Display strength
            strengthDiv.innerHTML = `
                <div class="password-strength-bar ${strengthBarClass}"></div>
                <div class="strength-text ${strengthClass}">Password Strength: ${strengthText}</div>
                ${feedback.length > 0 ? '<div style="font-size: 11px; color: #888; margin-top: 4px;">Suggestions: ' + feedback.slice(0, 2).join(', ') + '</div>' : ''}
            `;

            // Display entropy
            let entropyLevel = '';
            if (entropy < 28) entropyLevel = 'Low';
            else if (entropy < 35) entropyLevel = 'Medium';
            else if (entropy < 50) entropyLevel = 'High';
            else entropyLevel = 'Very High';

            entropyDiv.innerHTML = `Password Entropy: ${entropy.toFixed(1)} bits (${entropyLevel})`;
        }

        // CAPTCHA refresh functionality
        document.addEventListener('DOMContentLoaded', function() {
            const refreshBtn = document.getElementById('refresh-captcha');
            if (refreshBtn) {
                refreshBtn.addEventListener('click', function() {
                    fetch('register.php?refresh_captcha=1')
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
    <div class="register-card">
        <h1>Create Account</h1>

        <?php if ($registrationSuccess): ?>
            <div style="background: #d4edda; border: 1px solid #c3e6cb; border-radius: 6px; padding: 16px; margin-bottom: 20px; color: #155724;">
                <h3 style="margin: 0 0 10px 0; color: #155724;">Registration Successful!</h3>
                <p style="margin: 0 0 10px 0;">We've sent a verification email to <strong><?php echo htmlspecialchars($registeredEmail ?? ''); ?></strong></p>
                <p style="margin: 0; font-size: 14px;">Please check your email and click the verification link to activate your account.</p>
                <?php if ($verificationLink): ?>
                    <div style="margin-top: 15px; padding: 10px; background: #f8f9fa; border-radius: 4px; font-size: 12px;">
                        <strong>Development Mode:</strong> Email sending may be disabled. Use this link to verify:<br>
                        <a href="<?php echo htmlspecialchars($verificationLink, ENT_QUOTES, 'UTF-8'); ?>" style="color: #667eea; word-break: break-all;">
                            <?php echo htmlspecialchars($verificationLink, ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    </div>
                <?php endif; ?>
                <div style="margin-top: 15px;">
                    <a href="login.php" style="color: #667eea; font-weight: 600;">Go to Login Page</a>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div style="background: #fee; border: 1px solid #fcc; border-radius: 6px; padding: 12px 16px; margin-bottom: 20px;">
                <strong style="color: #c33;">Please fix the following errors:</strong>
                <ul style="margin: 8px 0 0 20px; color: #c33;">
                    <?php foreach ($errors as $error): ?>
                        <?php if (is_array($error)): ?>
                            <?php foreach ($error as $err): ?>
                                <li><?php echo htmlspecialchars($err); ?></li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="register.php">
            <div class="form-group">
                <label for="fullname">Full Name</label>
                <input
                    type="text"
                    id="fullname"
                    name="fullname"
                    value="<?php echo isset($_POST['fullname']) ? htmlspecialchars($_POST['fullname']) : ''; ?>"
                    class="<?php echo isset($errors['fullname']) ? 'error' : ''; ?>"
                    required
                >
                <?php if (isset($errors['fullname'])): ?>
                    <span class="field-error"><?php echo htmlspecialchars($errors['fullname']); ?></span>
                <?php endif; ?>
            </div>

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
                <?php if (isset($errors['email'])): ?>
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
                    oninput="checkPasswordStrength(this.value)"
                >
                <?php if (isset($errors['password'])): ?>
                    <span class="field-error"><?php echo htmlspecialchars($errors['password']); ?></span>
                <?php else: ?>
                    <div class="hint">At least 8 characters, letters + numbers</div>
                <?php endif; ?>
                <div id="password-strength" style="margin-top: 8px;"></div>
                <div id="password-entropy" style="font-size: 12px; color: #666; margin-top: 4px;"></div>
            </div>

            <div class="form-group">
                <label for="phone">Phone</label>
                <input
                    type="tel"
                    id="phone"
                    name="phone"
                    value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>"
                    placeholder="07xxxxxxxxx"
                    class="<?php echo isset($errors['phone']) ? 'error' : ''; ?>"
                    required
                >
                <?php if (isset($errors['phone'])): ?>
                    <span class="field-error"><?php echo htmlspecialchars($errors['phone']); ?></span>
                <?php else: ?>
                    <div class="hint">UK format: 11 digits starting with 07</div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="security_question">Security Question</label>
                <select
                    id="security_question"
                    name="security_question"
                    class="<?php echo isset($errors['security_question']) ? 'error' : ''; ?>"
                    required
                    style="width: 100%; padding: 11px 14px; border-radius: 6px; border: 2px solid #e0e0e0; font-size: 14px; background: #fff;"
                >
                    <option value="">-- Select a security question --</option>
                    <option value="What was the name of your first pet?" <?php echo (isset($_POST['security_question']) && $_POST['security_question'] === 'What was the name of your first pet?') ? 'selected' : ''; ?>>What was the name of your first pet?</option>
                    <option value="What city were you born in?" <?php echo (isset($_POST['security_question']) && $_POST['security_question'] === 'What city were you born in?') ? 'selected' : ''; ?>>What city were you born in?</option>
                    <option value="What was your mother's maiden name?" <?php echo (isset($_POST['security_question']) && $_POST['security_question'] === 'What was your mother\'s maiden name?') ? 'selected' : ''; ?>>What was your mother's maiden name?</option>
                    <option value="What was the name of your elementary school?" <?php echo (isset($_POST['security_question']) && $_POST['security_question'] === 'What was the name of your elementary school?') ? 'selected' : ''; ?>>What was the name of your elementary school?</option>
                    <option value="What is your favorite movie?" <?php echo (isset($_POST['security_question']) && $_POST['security_question'] === 'What is your favorite movie?') ? 'selected' : ''; ?>>What is your favorite movie?</option>
                    <option value="What was your childhood nickname?" <?php echo (isset($_POST['security_question']) && $_POST['security_question'] === 'What was your childhood nickname?') ? 'selected' : ''; ?>>What was your childhood nickname?</option>
                    <option value="What is the name of your best friend?" <?php echo (isset($_POST['security_question']) && $_POST['security_question'] === 'What is the name of your best friend?') ? 'selected' : ''; ?>>What is the name of your best friend?</option>
                    <option value="What was your favorite food as a child?" <?php echo (isset($_POST['security_question']) && $_POST['security_question'] === 'What was your favorite food as a child?') ? 'selected' : ''; ?>>What was your favorite food as a child?</option>
                </select>
                <?php if (isset($errors['security_question'])): ?>
                    <span class="field-error"><?php echo htmlspecialchars($errors['security_question']); ?></span>
                <?php else: ?>
                    <div class="hint">Choose a question you can remember the answer to</div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="security_answer">Security Answer</label>
                <input
                    type="text"
                    id="security_answer"
                    name="security_answer"
                    value="<?php echo isset($_POST['security_answer']) ? htmlspecialchars($_POST['security_answer']) : ''; ?>"
                    placeholder="Enter your answer"
                    class="<?php echo isset($errors['security_answer']) ? 'error' : ''; ?>"
                    required
                >
                <?php if (isset($errors['security_answer'])): ?>
                    <span class="field-error"><?php echo htmlspecialchars($errors['security_answer']); ?></span>
                <?php else: ?>
                    <div class="hint">At least 3 characters (case-insensitive)</div>
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

            <button type="submit">Register</button>
        </form>

        <div class="login-link">
            Already registered? <a href="login.php">Log in</a>
        </div>
    </div>
</body>
</html>