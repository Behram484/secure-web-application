<?php

session_start();
require __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/mailer.php';
require __DIR__ . '/includes/csrf.php';

/**
 * The question list offered at registration, in registration order.
 */
function securityQuestionChoices() {
    return [
        'What was the name of your first pet?',
        'What city were you born in?',
        "What was your mother's maiden name?",
        'What was the name of your elementary school?',
        'What is your favorite movie?',
        'What was your childhood nickname?',
        'What is the name of your best friend?',
        'What was your favorite food as a child?',
    ];
}

/**
 * A stand-in security question for an address that has no account, or an
 * account with no question set.
 *
 * This flow used to answer "does this address have an account?" for anyone who
 * asked: a registered address advanced to the security question, an
 * unregistered one was refused with "Invalid email or account not found.". The
 * question shown is the whole point of the step, so the step itself was the
 * oracle and no rewording could close it.
 *
 * Every address now advances, and one with no account is given a question
 * chosen by hashing the address. Deterministic, so the same address always
 * gets the same question and repeat probing looks identical; the answer is
 * never accepted, and the refusal is worded exactly as a wrong answer against
 * a real account.
 */
function decoySecurityQuestion($email) {
    $choices = securityQuestionChoices();
    $digest = hash('sha256', strtolower(trim($email)));
    $index = hexdec(substr($digest, 0, 8)) % count($choices);
    return $choices[$index];
}

$errors = [];
$successMessage = '';
$resetLink = '';
$emailSent = false;
$emailError = '';
$step = isset($_SESSION['reset_step']) ? $_SESSION['reset_step'] : 1; // Step 1: email, Step 2: security question
$userEmail = isset($_SESSION['reset_email']) ? $_SESSION['reset_email'] : '';
$securityQuestion = '';

// Handle step 1: Email submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 1) {
    // CSRF Protection
    if (!verifyCSRFToken()) {
        $errors['csrf'] = 'Invalid security token. Please try again.';
    }
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';

    // Simple validation
    if ($email === '') {
        $errors['email'] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid email format';
    }

    if (empty($errors)) {
        // Find user and get security question
        $stmt = $pdo->prepare('SELECT id, email, full_name, security_question FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Every address advances to step two, whether or not it has an
        // account. An account with no security question set is treated the
        // same way as one that does not exist, because "this account has no
        // security question" gives the game away just as plainly.
        $isRealAccount = $user && !empty($user['security_question']);

        $_SESSION['reset_step']     = 2;
        $_SESSION['reset_email']    = $email;
        $_SESSION['reset_user_id']  = $isRealAccount ? $user['id'] : null;
        $_SESSION['reset_decoy']    = $isRealAccount ? 0 : 1;
        $_SESSION['reset_question'] = $isRealAccount
                ? $user['security_question']
                : decoySecurityQuestion($email);

        $step = 2;
        $userEmail = $email;
        $securityQuestion = $_SESSION['reset_question'];
    }
}

// Handle step 2: Security answer verification
//
// elseif, not if. As two separate if blocks the first one set $step = 2 and
// then this one ran inside the same request with an empty security answer, so
// submitting a valid email address returned the security question and
// "Security answer is required" together - a validation failure for a field
// the user had not been shown yet.
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 2) {
    // CSRF Protection
    if (!verifyCSRFToken()) {
        $errors['csrf'] = 'Invalid security token. Please try again.';
    }
    
    $email = isset($_SESSION['reset_email']) ? $_SESSION['reset_email'] : '';
    $userId = isset($_SESSION['reset_user_id']) ? $_SESSION['reset_user_id'] : null;
    $securityAnswer = isset($_POST['security_answer']) ? trim($_POST['security_answer']) : '';

    $isDecoy = !empty($_SESSION['reset_decoy']);

    if (empty($errors)) {
        if ($securityAnswer === '') {
            $errors['security_answer'] = 'Security answer is required';
            $securityQuestion = isset($_SESSION['reset_question'])
                    ? $_SESSION['reset_question'] : '';
        } elseif ($isDecoy) {
            // No account behind this address. Refuse in exactly the words a
            // real account uses for a wrong answer, and leave the wizard on
            // step two, so the shape of the response matches as well as the
            // wording.
            $errors['security_answer'] = 'Incorrect security answer. Please try again.';
            $securityQuestion = isset($_SESSION['reset_question'])
                    ? $_SESSION['reset_question'] : '';
        } elseif ($userId === null || $email === '') {
            $errors['security_answer'] = 'Session expired. Please start over.';
            // Clear session
            unset($_SESSION['reset_step']);
            unset($_SESSION['reset_email']);
            unset($_SESSION['reset_user_id']);
            unset($_SESSION['reset_decoy']);
            unset($_SESSION['reset_question']);
            $step = 1;
        } else {
            // Get user's security answer hash
            $stmt = $pdo->prepare('SELECT id, email, full_name, security_question, security_answer_hash FROM users WHERE id = ? AND email = ? LIMIT 1');
            $stmt->execute([$userId, $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && !empty($user['security_answer_hash'])) {
                // Verify security answer (case-insensitive)
                if (password_verify(strtolower(trim($securityAnswer)), $user['security_answer_hash'])) {
                    // Answer is correct - generate reset token and send email
                    $token = bin2hex(random_bytes(32));   // 64 hex characters
                    $expires = date('Y-m-d H:i:s', time() + 3600);

                    // Write to database
                    $update = $pdo->prepare(
                        'UPDATE users SET reset_token = :token, reset_expires = :expires WHERE id = :id'
                    );
                    $update->execute([
                        'token'   => $token,
                        'expires' => $expires,
                        'id'      => $user['id'],
                    ]);

                    // Send password reset email
                    $userName = $user['full_name'] ?? $user['email'];
                    $emailResult = sendPasswordResetEmail($user['email'], $userName, $token);
                    
                    // Store reset link for display (if needed)
                    $config = require __DIR__ . '/includes/mail_config.php';
                    if ($emailResult['link']) {
                        $resetLink = $emailResult['link'];
                    }
                    
                    $emailSent = $emailResult['success'];
                    
                    // Store email result message for debugging
                    if (!$emailSent && isset($emailResult['message'])) {
                        $emailError = $emailResult['message'];
                    }

                    // Clear session
                    unset($_SESSION['reset_step']);
                    unset($_SESSION['reset_email']);
                    unset($_SESSION['reset_user_id']);
                    unset($_SESSION['reset_decoy']);
                    unset($_SESSION['reset_question']);

                    // Show success message
                    if ($emailSent) {
                        $successMessage = 'Security answer verified! A password reset link has been sent to your email address.';
                    } else {
                        $successMessage = 'Security answer verified! A reset link has been generated.';
                    }
                    $step = 1; // Reset to step 1
                } else {
                    // Wrong answer
                    $errors['security_answer'] = 'Incorrect security answer. Please try again.';
                    $securityQuestion = $user['security_question'];
                }
            } else {
                $errors['security_answer'] = 'Account error. Please start over.';
                // Clear session
                unset($_SESSION['reset_step']);
                unset($_SESSION['reset_email']);
                unset($_SESSION['reset_user_id']);
                unset($_SESSION['reset_decoy']);
                unset($_SESSION['reset_question']);
                $step = 1;
            }
        }
    }
}

// If step 2, take the question from the session rather than re-reading the
// database. Looking it up again here would drop a decoy back to step one and
// reintroduce exactly the difference in behaviour this is meant to remove.
if ($step === 2 && empty($securityQuestion)) {
    if (!empty($_SESSION['reset_question'])) {
        $securityQuestion = $_SESSION['reset_question'];
    } else {
        unset($_SESSION['reset_step']);
        unset($_SESSION['reset_email']);
        unset($_SESSION['reset_user_id']);
        unset($_SESSION['reset_decoy']);
        unset($_SESSION['reset_question']);
        $step = 1;
    }
}

// Handle "Start Over" button
if (isset($_GET['reset']) && $_GET['reset'] === '1') {
    unset($_SESSION['reset_step']);
    unset($_SESSION['reset_email']);
    unset($_SESSION['reset_user_id']);
    unset($_SESSION['reset_decoy']);
    unset($_SESSION['reset_question']);
    $step = 1;
    $userEmail = '';
    $securityQuestion = '';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Password Reset Request - Lovejoy App</title>
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

        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            font-size: 14px;
            margin-bottom: 15px;
        }

        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
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

        .dev-link {
            margin-top: 10px;
            font-size: 12px;
            color: #444;
            background: #f8f9fa;
            border-radius: 6px;
            padding: 10px;
            word-break: break-all;
        }

        .security-question-box {
            background: #f3f4ff;
            border: 2px solid #d6dafc;
            border-radius: 6px;
            padding: 16px;
            margin-bottom: 18px;
            font-size: 15px;
            color: #333;
            font-weight: 600;
        }

        .hint {
            font-size: 12px;
            color: #888;
            margin-top: 4px;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>Reset your password</h1>
        
        <?php if ($step === 1): ?>
            <p class="intro">Enter your account email to begin the password reset process.</p>
        <?php elseif ($step === 2): ?>
            <p class="intro">Please answer your security question to verify your identity.</p>
        <?php endif; ?>

        <?php if (!empty($emailError)): ?>
            <div class="alert alert-error">
                <strong>Email Error:</strong> <?php echo htmlspecialchars($emailError); ?>
            </div>
        <?php endif; ?>

        <?php if ($successMessage): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($successMessage); ?>
            </div>
            <?php 
            // Only show development link if email sending failed (as fallback)
            if ($resetLink && !$emailSent):
            ?>
                <div class="dev-link">
                    <strong>Development reset link (email sending failed):</strong><br>
                    <a href="<?php echo htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8'); ?>" target="_blank">
                        <?php echo htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <strong>Please fix the following errors:</strong>
                <ul style="margin: 8px 0 0 20px;">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
            <!-- Step 1: Email Input -->
            <form method="POST" action="password_reset_request.php">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                        class="<?php echo isset($errors['email']) ? 'error' : ''; ?>"
                        required
                        autofocus
                    >
                    <?php if (isset($errors['email'])): ?>
                        <span class="field-error"><?php echo htmlspecialchars($errors['email']); ?></span>
                    <?php endif; ?>
                </div>

                <?php echo csrfField(); ?>

                <button type="submit">Continue</button>
            </form>
        <?php elseif ($step === 2): ?>
            <!-- Step 2: Security Question -->
            <div class="security-question-box">
                <?php echo htmlspecialchars($securityQuestion); ?>
            </div>

            <form method="POST" action="password_reset_request.php">
                <div class="form-group">
                    <label for="security_answer">Your Answer</label>
                    <input
                        type="text"
                        id="security_answer"
                        name="security_answer"
                        value="<?php echo isset($_POST['security_answer']) ? htmlspecialchars($_POST['security_answer']) : ''; ?>"
                        class="<?php echo isset($errors['security_answer']) ? 'error' : ''; ?>"
                        required
                        autofocus
                    >
                    <?php if (isset($errors['security_answer'])): ?>
                        <span class="field-error"><?php echo htmlspecialchars($errors['security_answer']); ?></span>
                    <?php else: ?>
                        <div class="hint">Answer is case-insensitive</div>
                    <?php endif; ?>
                </div>

                <?php echo csrfField(); ?>

                <button type="submit">Verify and Send Reset Link</button>
            </form>

            <div class="extra" style="margin-top: 12px;">
                <a href="password_reset_request.php?reset=1">Start Over</a>
            </div>
        <?php endif; ?>

        <div class="extra">
            Remembered your password? <a href="login.php">Back to login</a>
        </div>
    </div>
</body>
</html>
