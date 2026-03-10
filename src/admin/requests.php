<?php

session_start();
require __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/csrf.php';

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

// Status label mapping
$statusLabels = [
    'pending'      => 'Pending',
    'open'         => 'Open',
    'closed'       => 'Closed',
    'in_progress'  => 'In Progress',
    'resolved'     => 'Resolved',
];

// Get filter status from query parameter
$filterStatus = isset($_GET['status']) ? trim($_GET['status']) : '';

// Build query based on filter
if ($filterStatus && in_array($filterStatus, array_keys($statusLabels))) {
    $stmt = $pdo->prepare(
        'SELECT r.id, r.subject, r.description, r.contact_preference, r.photo_path, r.status, r.created_at, r.updated_at,
                u.id as user_id, u.full_name as user_name, u.email as user_email
         FROM requests r
         LEFT JOIN users u ON r.user_id = u.id
         WHERE r.status = ?
         ORDER BY r.created_at DESC'
    );
    $stmt->execute([$filterStatus]);
} else {
    $stmt = $pdo->query(
        'SELECT r.id, r.subject, r.description, r.contact_preference, r.photo_path, r.status, r.created_at, r.updated_at,
                u.id as user_id, u.full_name as user_name, u.email as user_email
         FROM requests r
         LEFT JOIN users u ON r.user_id = u.id
         ORDER BY r.created_at DESC'
    );
}

$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Description truncate length
define('DESC_TRUNCATE_LENGTH', 150);

$fullName = $_SESSION['user_fullname'] ?? 'Admin';
$email    = $_SESSION['user_email']    ?? '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Requests - Admin - Lovejoy App</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: linear-gradient(135deg,rgb(32, 70, 240) 0%,rgba(3, 28, 252, 0.71) 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
        }

        .request-card {
            background: #fff;
            border-radius: 10px;
            padding: 40px;
            box-shadow: 0 15px 35px rgba(0,0,0,.2);
        }

        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }

        .admin-header h1 {
            font-size: 26px;
            color: #333;
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

        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .filter-tab {
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: all .2s;
            border: 2px solid transparent;
        }

        .filter-tab.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
        }

        .filter-tab:not(.active) {
            background: #f3f4ff;
            color: #444;
            border-color: #d6dafc;
        }

        .filter-tab:hover:not(.active) {
            background: #e8eaff;
            transform: translateY(-1px);
        }

        .request-item {
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            padding: 18px;
            margin-bottom: 14px;
            background: #fafafa;
            transition: box-shadow .2s;
        }

        .request-item:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,.1);
        }

        .request-item:last-child {
            margin-bottom: 0;
        }

        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 10px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .request-subject {
            font-size: 17px;
            font-weight: 600;
            color: #333;
            margin-bottom: 6px;
        }

        .request-status {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-open {
            background: #cfe2ff;
            color: #084298;
        }

        .status-closed {
            background: #d1e7dd;
            color: #0f5132;
        }

        .status-in_progress {
            background: #cfe2ff;
            color: #084298;
        }

        .status-resolved {
            background: #d1e7dd;
            color: #0f5132;
        }

        .request-user {
            font-size: 13px;
            color: #666;
            margin-bottom: 8px;
        }

        .request-user strong {
            color: #333;
        }

        .request-description {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
            line-height: 1.6;
        }

        .request-meta {
            font-size: 12px;
            color: #888;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }

        .view-details-link {
            display: inline-block;
            margin-top: 8px;
            color: #667eea;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
        }

        .view-details-link:hover {
            text-decoration: underline;
        }

        .actions {
            margin-top: 25px;
            text-align: center;
        }

        .btn {
            display: inline-block;
            padding: 11px 20px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: transform .2s, box-shadow .2s;
            margin: 0 5px;
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

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 20px rgba(0,0,0,.12);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #888;
        }

        .empty-state p {
            margin-bottom: 20px;
            font-size: 16px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .stat-box {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            border: 1px solid #e0e0e0;
        }

        .stat-box .number {
            font-size: 24px;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 5px;
        }

        .stat-box .label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .flash-message {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .flash-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .flash-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .status-update-form {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #e0e0e0;
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .status-update-form label {
            font-size: 13px;
            font-weight: 600;
            color: #555;
        }

        .status-select {
            padding: 6px 10px;
            border-radius: 6px;
            border: 2px solid #e0e0e0;
            font-size: 13px;
            font-weight: 600;
            background: #fff;
            color: #333;
            cursor: pointer;
            transition: border-color .2s;
        }

        .status-select:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn-update {
            padding: 6px 14px;
            border-radius: 6px;
            border: none;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: transform .15s, box-shadow .15s;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
        }

        .btn-update:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(102, 126, 234, .3);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="request-card">
            <div class="admin-header">
                <div>
                    <h1>Manage Requests</h1>
                </div>
                <div class="admin-info">
                    <strong><?php echo htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'); ?></strong>
                    <span><?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?></span>
                    <div class="admin-tag">Administrator</div>
                </div>
            </div>

            <?php if (!empty($_SESSION['flash_success'])): ?>
                <div class="flash-message flash-success">
                    <?php echo htmlspecialchars($_SESSION['flash_success']); ?>
                </div>
                <?php unset($_SESSION['flash_success']); ?>
            <?php endif; ?>

            <?php if (!empty($_SESSION['flash_error'])): ?>
                <div class="flash-message flash-error">
                    <?php echo htmlspecialchars($_SESSION['flash_error']); ?>
                </div>
                <?php unset($_SESSION['flash_error']); ?>
            <?php endif; ?>

            <div class="filter-tabs">
                <a href="requests.php" class="filter-tab <?php echo $filterStatus === '' ? 'active' : ''; ?>">
                    All Requests
                </a>
                <a href="requests.php?status=pending" class="filter-tab <?php echo $filterStatus === 'pending' ? 'active' : ''; ?>">
                    Pending
                </a>
                <a href="requests.php?status=open" class="filter-tab <?php echo $filterStatus === 'open' ? 'active' : ''; ?>">
                    Open
                </a>
                <a href="requests.php?status=in_progress" class="filter-tab <?php echo $filterStatus === 'in_progress' ? 'active' : ''; ?>">
                    In Progress
                </a>
                <a href="requests.php?status=resolved" class="filter-tab <?php echo $filterStatus === 'resolved' ? 'active' : ''; ?>">
                    Resolved
                </a>
                <a href="requests.php?status=closed" class="filter-tab <?php echo $filterStatus === 'closed' ? 'active' : ''; ?>">
                    Closed
                </a>
            </div>

            <?php if (empty($requests)): ?>
                <div class="empty-state">
                    <p>No requests found<?php echo $filterStatus ? ' with status "' . htmlspecialchars($statusLabels[$filterStatus] ?? $filterStatus) . '"' : ''; ?>.</p>
                    <a href="index.php" class="btn btn-secondary">Back to Admin Dashboard</a>
                </div>
            <?php else: ?>
                <div style="margin-bottom: 15px; font-size: 14px; color: #666;">
                    Showing <?php echo count($requests); ?> request(s)<?php echo $filterStatus ? ' with status "' . htmlspecialchars($statusLabels[$filterStatus] ?? $filterStatus) . '"' : ''; ?>
                </div>

                <?php foreach ($requests as $request): ?>
                    <?php
                    // Get status label from mapping
                    $statusLabel = $statusLabels[$request['status']] ?? 'Unknown';
                    
                    // Truncate description if needed
                    $description = $request['description'];
                    $isTruncated = strlen($description) > DESC_TRUNCATE_LENGTH;
                    if ($isTruncated) {
                        $description = substr($description, 0, DESC_TRUNCATE_LENGTH) . '...';
                    }

                    // Get user info
                    $userName = $request['user_name'] ?? 'Unknown User';
                    $userEmail = $request['user_email'] ?? 'N/A';
                    ?>
                    <div class="request-item">
                        <div class="request-header">
                            <div style="flex: 1;">
                                <div class="request-subject"><?php echo htmlspecialchars($request['subject']); ?></div>
                                <div class="request-user">
                                    <strong>User:</strong> <?php echo htmlspecialchars($userName); ?> 
                                    (<?php echo htmlspecialchars($userEmail); ?>)
                                </div>
                            </div>
                            <span class="request-status status-<?php echo htmlspecialchars($request['status']); ?>">
                                <?php echo htmlspecialchars($statusLabel); ?>
                            </span>
                        </div>
                        <div class="request-description">
                            <?php echo nl2br(htmlspecialchars($description)); ?>
                            <?php if ($isTruncated): ?>
                                <a href="../request_detail.php?id=<?php echo (int)$request['id']; ?>" class="view-details-link">View details</a>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($request['photo_path']) && strpos($request['photo_path'], 'uploads/') === 0 && file_exists(__DIR__ . '/../' . $request['photo_path'])): ?>
                            <div style="margin-top: 10px;">
                                <img src="../<?php echo htmlspecialchars($request['photo_path']); ?>" 
                                     alt="Request photo" 
                                     style="max-width: 200px; max-height: 150px; border-radius: 6px; border: 1px solid #e0e0e0; cursor: pointer;"
                                     onclick="window.open('../<?php echo htmlspecialchars($request['photo_path']); ?>', '_blank')"
                                     title="Click to view full size">
                            </div>
                        <?php endif; ?>
                        <div class="request-meta">
                            <span>Created: <?php echo date('M d, Y H:i', strtotime($request['created_at'])); ?></span>
                            <?php if ($request['updated_at'] !== $request['created_at']): ?>
                                <span>Updated: <?php echo date('M d, Y H:i', strtotime($request['updated_at'])); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="status-update-form">
                            <form method="POST" action="update_status.php" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                                <label for="status_<?php echo (int)$request['id']; ?>">Update Status:</label>
                                <select name="status" id="status_<?php echo (int)$request['id']; ?>" class="status-select" required>
                                    <?php foreach ($statusLabels as $statusValue => $statusLabel): ?>
                                        <option value="<?php echo htmlspecialchars($statusValue); ?>" 
                                                <?php echo $request['status'] === $statusValue ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($statusLabel); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="hidden" name="request_id" value="<?php echo (int)$request['id']; ?>">
                                <?php if ($filterStatus): ?>
                                    <input type="hidden" name="return_filter" value="<?php echo htmlspecialchars($filterStatus); ?>">
                                <?php endif; ?>
                                <?php require_once __DIR__ . '/../includes/csrf.php'; echo csrfField(); ?>
                                <button type="submit" class="btn-update">Update</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <div class="actions">
                <a href="index.php" class="btn btn-secondary">Back to Admin Dashboard</a>
            </div>
        </div>
    </div>
</body>
</html>

