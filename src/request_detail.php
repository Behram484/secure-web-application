<?php

session_start();
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/csrf.php';

// Must be logged in to access
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get request ID
$requestId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($requestId <= 0) {
    $_SESSION['flash_error'] = 'Invalid request ID.';
    header('Location: request_list.php');
    exit;
}

// Check if user is admin
$isAdmin = !empty($_SESSION['user_is_admin']) && $_SESSION['user_is_admin'] == 1;

// Status label mapping
$statusLabels = [
    'pending'      => 'Pending',
    'open'         => 'Open',
    'closed'       => 'Closed',
    'in_progress'  => 'In Progress',
    'resolved'     => 'Resolved',
];

// Fetch request with user information
if ($isAdmin) {
    // Admin can view all requests
    $stmt = $pdo->prepare(
        'SELECT r.id, r.subject, r.description, r.contact_preference, r.photo_path, r.status, r.created_at, r.updated_at,
                u.id as user_id, u.full_name as user_name, u.email as user_email, u.phone as user_phone
         FROM requests r
         LEFT JOIN users u ON r.user_id = u.id
         WHERE r.id = ?'
    );
    $stmt->execute([$requestId]);
} else {
    // Regular user can only view their own requests
    $stmt = $pdo->prepare(
        'SELECT r.id, r.subject, r.description, r.contact_preference, r.photo_path, r.status, r.created_at, r.updated_at,
                u.id as user_id, u.full_name as user_name, u.email as user_email, u.phone as user_phone
         FROM requests r
         LEFT JOIN users u ON r.user_id = u.id
         WHERE r.id = ? AND r.user_id = ?'
    );
    $stmt->execute([$requestId, $_SESSION['user_id']]);
}

$request = $stmt->fetch();

if (!$request) {
    $_SESSION['flash_error'] = 'Request not found or you do not have permission to view it.';
    header('Location: request_list.php');
    exit;
}

// Get status label
$statusLabel = $statusLabels[$request['status']] ?? 'Unknown';

$fullName = $_SESSION['user_fullname'] ?? 'User';
$email    = $_SESSION['user_email']    ?? '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Details - Lovejoy App</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: linear-gradient(135deg,rgb(32, 70, 240) 0%,rgba(3, 28, 252, 0.71) 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
        }

        .detail-card {
            background: #fff;
            border-radius: 10px;
            padding: 40px;
            box-shadow: 0 15px 35px rgba(0,0,0,.2);
        }

        .detail-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
            flex-wrap: wrap;
            gap: 15px;
        }

        .detail-header h1 {
            font-size: 26px;
            color: #333;
            flex: 1;
        }

        .request-status {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 12px;
            font-size: 13px;
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

        .detail-section {
            margin-bottom: 25px;
        }

        .detail-section:last-child {
            margin-bottom: 0;
        }

        .section-label {
            font-size: 12px;
            font-weight: 600;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .section-content {
            font-size: 15px;
            color: #333;
            line-height: 1.7;
        }

        .section-content.subject {
            font-size: 20px;
            font-weight: 600;
            color: #222;
            margin-bottom: 4px;
        }

        .section-content.description {
            white-space: pre-wrap;
            word-wrap: break-word;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 6px;
            border-left: 4px solid #667eea;
        }

        .user-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 6px;
        }

        .user-info-item {
            font-size: 14px;
        }

        .user-info-item strong {
            display: block;
            color: #666;
            font-size: 12px;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .user-info-item span {
            color: #333;
            font-weight: 500;
        }

        .meta-info {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 6px;
            font-size: 13px;
            color: #666;
        }

        .meta-info-item strong {
            color: #555;
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

        .admin-section {
            margin-top: 30px;
            padding-top: 25px;
            border-top: 2px solid #e0e0e0;
        }

        .admin-section-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
        }

        .status-update-form {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 6px;
        }

        .status-update-form label {
            font-size: 14px;
            font-weight: 600;
            color: #555;
        }

        .status-select {
            padding: 8px 12px;
            border-radius: 6px;
            border: 2px solid #e0e0e0;
            font-size: 14px;
            font-weight: 600;
            background: #fff;
            color: #333;
            cursor: pointer;
            transition: border-color .2s;
            min-width: 150px;
        }

        .status-select:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn-update {
            padding: 8px 18px;
            border-radius: 6px;
            border: none;
            font-size: 14px;
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

        .actions {
            margin-top: 30px;
            text-align: center;
            padding-top: 25px;
            border-top: 2px solid #f0f0f0;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="detail-card">
            <div class="detail-header">
                <h1>Request Details</h1>
                <span class="request-status status-<?php echo htmlspecialchars($request['status']); ?>">
                    <?php echo htmlspecialchars($statusLabel); ?>
                </span>
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

            <div class="detail-section">
                <div class="section-label">Subject</div>
                <div class="section-content subject">
                    <?php echo htmlspecialchars($request['subject']); ?>
                </div>
            </div>

            <div class="detail-section">
                <div class="section-label">Description</div>
                <div class="section-content description">
                    <?php echo htmlspecialchars($request['description']); ?>
                </div>
            </div>

            <?php if (!empty($request['photo_path']) && strpos($request['photo_path'], 'uploads/') === 0 && file_exists(__DIR__ . '/' . $request['photo_path'])): ?>
                <div class="detail-section">
                    <div class="section-label">Photo</div>
                    <div class="photo-container">
                        <img src="<?php echo htmlspecialchars($request['photo_path']); ?>" 
                             alt="Object photo" 
                             class="request-photo"
                             style="max-width: 100%; max-height: 500px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,.15);">
                    </div>
                </div>
            <?php endif; ?>

            <div class="detail-section">
                <div class="section-label">Contact Preference</div>
                <div class="section-content">
                    <span style="text-transform: capitalize; font-weight: 600;">
                        <?php echo htmlspecialchars($request['contact_preference'] ?? 'email'); ?>
                    </span>
                </div>
            </div>

            <?php if ($isAdmin): ?>
                <div class="detail-section">
                    <div class="section-label">User Information</div>
                    <div class="user-info">
                        <div class="user-info-item">
                            <strong>Name</strong>
                            <span><?php echo htmlspecialchars($request['user_name'] ?? 'Unknown'); ?></span>
                        </div>
                        <div class="user-info-item">
                            <strong>Email</strong>
                            <span><?php echo htmlspecialchars($request['user_email'] ?? 'N/A'); ?></span>
                        </div>
                        <?php if (!empty($request['user_phone'])): ?>
                            <div class="user-info-item">
                                <strong>Phone</strong>
                                <span><?php echo htmlspecialchars($request['user_phone']); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="detail-section">
                <div class="section-label">Request Information</div>
                <div class="meta-info">
                    <div class="meta-info-item">
                        <strong>Request ID:</strong> #<?php echo (int)$request['id']; ?>
                    </div>
                    <div class="meta-info-item">
                        <strong>Created:</strong> <?php echo date('M d, Y H:i', strtotime($request['created_at'])); ?>
                    </div>
                    <?php if ($request['updated_at'] !== $request['created_at']): ?>
                        <div class="meta-info-item">
                            <strong>Last Updated:</strong> <?php echo date('M d, Y H:i', strtotime($request['updated_at'])); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($isAdmin): ?>
                <div class="admin-section">
                    <div class="admin-section-title">Admin Actions</div>
                    <div class="status-update-form">
                        <form method="POST" action="admin/update_status.php" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                            <label for="status_update">Update Status:</label>
                            <select name="status" id="status_update" class="status-select" required>
                                <?php foreach ($statusLabels as $statusValue => $statusLabel): ?>
                                    <option value="<?php echo htmlspecialchars($statusValue); ?>" 
                                            <?php echo $request['status'] === $statusValue ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($statusLabel); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="request_id" value="<?php echo (int)$request['id']; ?>">
                            <input type="hidden" name="redirect_to" value="detail">
                            <?php echo csrfField(); ?>
                            <button type="submit" class="btn-update">Update Status</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <div class="actions">
                <?php if ($isAdmin): ?>
                    <a href="admin/requests.php" class="btn btn-secondary">Back to Admin Requests</a>
                    <a href="admin/index.php" class="btn btn-secondary">Admin Dashboard</a>
                <?php else: ?>
                    <a href="request_list.php" class="btn btn-secondary">Back to My Requests</a>
                <?php endif; ?>
                <a href="dashboard.php" class="btn btn-primary">Dashboard</a>
            </div>
        </div>
    </div>
</body>
</html>

