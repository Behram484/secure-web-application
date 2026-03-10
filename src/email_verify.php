<?php
session_start();
require __DIR__ . '/includes/db.php';

$errors = [];
$success = false;
$token = isset($_GET['token']) ? trim($_GET['token']) : '';

if ($token === '') {
    $errors[] = 'Invalid or missing verification token.';
} else {
    // Find user by verification token
    $stmt = $pdo->prepare(
        'SELECT id, email, full_name, email_verified, verification_token_expires 
         FROM users 
         WHERE verification_token = ? 
           AND verification_token_expires IS NOT NULL 
           AND verification_token_expires > NOW()
         LIMIT 1'
    );
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        if ($user['email_verified'] == 1) {
            $errors[] = 'This email has already been verified.';
        } else {
            // Verify the email
            $update = $pdo->prepare(
                'UPDATE users 
                 SET email_verified = 1,
                     verification_token = NULL,
                     verification_token_expires = NULL
                 WHERE id = ?'
            );
            $update->execute([$user['id']]);
            
            $success = true;
        }
    } else {
        $errors[] = 'This verification link is invalid or has expired.';
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - Lovejoy App</title>
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
            max-width: 500px;
            background: #fff;
            border-radius: 10px;
            padding: 40px;
            box-shadow: 0 15px 35px rgba(0,0,0,.2);
            text-align: center;
        }

        h1 {
            font-size: 26px;
            color: #333;
            margin-bottom: 20px;
        }

        .success-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background: #d4edda;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            color: #28a745;
        }

        .error-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background: #f8d7da;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            color: #dc3545;
        }

        .alert {
            padding: 16px;
            border-radius: 6px;
            font-size: 14px;
            margin-bottom: 20px;
            text-align: left;
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

        .alert-error ul {
            margin: 8px 0 0 20px;
        }

        .btn {
            display: inline-block;
            padding: 12px 24px;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            transition: transform .2s, box-shadow .2s;
            margin-top: 20px;
        }

        .btn-primary {
            color: #fff;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 8px 18px rgba(102, 126, 234, .35);
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 20px rgba(0,0,0,.12);
        }

        p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="card">
        <?php if ($success): ?>
            <div class="success-icon">✓</div>
            <h1>Email Verified Successfully!</h1>
            <div class="alert alert-success">
                <p><strong>Congratulations!</strong> Your email address has been verified.</p>
                <p>You can now log in to your account.</p>
            </div>
            <a href="login.php" class="btn btn-primary">Go to Login</a>
        <?php else: ?>
            <div class="error-icon">✗</div>
            <h1>Verification Failed</h1>
            <div class="alert alert-error">
                <?php if (!empty($errors)): ?>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <p>If you need a new verification email, please contact support or try registering again.</p>
            <a href="register.php" class="btn btn-primary">Back to Registration</a>
            <a href="login.php" class="btn btn-primary" style="margin-left: 10px;">Go to Login</a>
        <?php endif; ?>
    </div>
</body>
</html>





