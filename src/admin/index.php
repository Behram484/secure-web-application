<?php

session_start();

// 1) Must be logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// 2) Must be an administrator
if (empty($_SESSION['user_is_admin']) || $_SESSION['user_is_admin'] != 1) {
    header('Location: ../dashboard.php');
    exit;
}

$fullName = $_SESSION['user_fullname'] ?? 'Admin';
$email    = $_SESSION['user_email']    ?? '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Lovejoy App</title>
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
        .admin-card {
            width: 100%;
            max-width: 720px;
            background: #fff;
            border-radius: 12px;
            padding: 32px 36px;
            box-shadow: 0 15px 35px rgba(0,0,0,.2);
        }
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        .admin-header h1 {
            font-size: 24px;
            margin-bottom: 4px;
            color: #333;
        }
        .admin-header p {
            font-size: 14px;
            color: #777;
        }
        .admin-info {
            text-align: right;
            font-size: 13px;
            color: #666;
        }
        .admin-info strong {
            display: block;
            font-size: 14px;
            color: #333;
        }
        .admin-tag {
            display: inline-block;
            margin-top: 4px;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 11px;
            background: #ffe7b3;
            color: #9b5b00;
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
    </style>
</head>
<body>
<div class="admin-card">
    <div class="admin-header">
        <div>
            <h1>Admin Dashboard</h1>
            <p>Manage all support requests</p>
        </div>
        <div class="admin-info">
            <strong><?php echo htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'); ?></strong>
            <span><?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?></span>
            <div class="admin-tag">Administrator</div>
        </div>
    </div>
    <div>
        <div class="section-title">Requests Management</div>
        <div class="actions">
            <a class="btn btn-primary" href="requests.php">View All Requests</a>
            <a class="btn btn-secondary" href="requests.php?status=pending">Pending Requests</a>
            <a class="btn btn-secondary" href="requests.php?status=closed">Closed Requests</a>
            <a class="btn btn-danger" href="../logout.php">Logout</a>
            <a class="btn btn-secondary" href="../dashboard.php">Back to User Dashboard</a>
        </div>
    </div>
</div>
</body>
</html>

