<?php
session_start();

// 
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$fullName = isset($_SESSION['user_fullname']) ? $_SESSION['user_fullname'] : 'User';
$email    = isset($_SESSION['user_email']) ? $_SESSION['user_email'] : '';
$phone    = isset($_SESSION['user_phone']) ? $_SESSION['user_phone'] : '';
$isAdmin  = !empty($_SESSION['user_is_admin']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Lovejoy App</title>
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
            color: #333;
        }

        .dash-card {
            width: 100%;
            max-width: 720px;
            background: #fff;
            border-radius: 12px;
            padding: 32px 36px;
            box-shadow: 0 15px 35px rgba(0,0,0,.2);
        }

        .dash-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .dash-header h1 {
            font-size: 24px;
            margin-bottom: 4px;
        }

        .dash-header p {
            font-size: 14px;
            color: #777;
        }

        .user-badge {
            text-align: right;
            font-size: 13px;
            color: #666;
        }

        .user-badge strong {
            display: block;
            font-size: 14px;
            color: #333;
        }

        .section-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 12px;
            margin-top: 8px;
        }

        .actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 14px;
            margin-top: 8px;
            margin-bottom: 8px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 11px 14px;
            border-radius: 8px;
            border: none;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: transform .15s, box-shadow .15s, background .15s;
            text-align: center;
        }

        .btn-primary {
            color: #fff;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 8px 18px rgba(102, 126, 234, .35);
        }

        .btn-secondary {
            color: #444;
            background: #f3f4ff;
            border: 1px solid #d6dafc;
        }

        .btn-danger {
            color: #fff;
            background: #e55353;
            box-shadow: 0 6px 14px rgba(229, 83, 83, .3);
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 20px rgba(0,0,0,.12);
        }

        .info-row {
            font-size: 14px;
            color: #555;
            margin-bottom: 4px;
        }

        .admin-tag {
            display: inline-block;
            margin-left: 6px;
            padding: 2px 6px;
            border-radius: 999px;
            font-size: 11px;
            background: #ffe7b3;
            color: #9b5b00;
            vertical-align: middle;
        }
    </style>
</head>
<body>
<div class="dash-card">
    <div class="dash-header">
        <div>
            <h1>Welcome, <?php echo htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'); ?></h1>
            <p>Your support requests hub</p>
        </div>
        <div class="user-badge">
            <strong><?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?></strong>
            <?php if ($phone): ?>
                <span><?php echo htmlspecialchars($phone, ENT_QUOTES, 'UTF-8'); ?></span>
            <?php endif; ?>
            <?php if ($isAdmin): ?>
                <span class="admin-tag">Admin</span>
            <?php endif; ?>
        </div>
    </div>

    <div>
        <div class="section-title">Quick Actions</div>
        <div class="actions">
            <a class="btn btn-primary" href="request_new.php">Create New Request</a>
            <a class="btn btn-secondary" href="request_list.php">View My Requests</a>
            <?php if ($isAdmin): ?>
                <a class="btn btn-secondary" href="admin/index.php">Admin Panel</a>
            <?php endif; ?>
            <a class="btn btn-danger" href="logout.php">Logout</a>
        </div>
    </div>
</div>
</body>
</html>
