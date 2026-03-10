<?php
session_start();
require __DIR__ . '/includes/db.php';

// Must be logged in to access
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
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

// Fetch user's requests from database
$stmt = $pdo->prepare(
    'SELECT id, subject, description, contact_preference, photo_path, status, created_at, updated_at 
     FROM requests 
     WHERE user_id = ? 
     ORDER BY created_at DESC'
);
$stmt->execute([$_SESSION['user_id']]);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Description truncate length
define('DESC_TRUNCATE_LENGTH', 150);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Requests - Lovejoy App</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: linear-gradient(135deg,rgb(32, 70, 240) 0%,rgba(3, 28, 252, 0.71) 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
        }

        .request-card {
            background: #fff;
            border-radius: 10px;
            padding: 40px;
            box-shadow: 0 15px 35px rgba(0,0,0,.2);
        }

        h1 {
            font-size: 26px;
            color: #333;
            margin-bottom: 25px;
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

        .request-item {
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            padding: 16px;
            margin-bottom: 12px;
            background: #fafafa;
        }

        .request-item:last-child {
            margin-bottom: 0;
        }

        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 8px;
        }

        .request-subject {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
        }

        .request-status {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
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

        .request-description {
            color: #666;
            font-size: 14px;
            margin-bottom: 8px;
            line-height: 1.5;
        }

        .request-meta {
            font-size: 12px;
            color: #888;
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
            margin-top: 20px;
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
            padding: 40px 20px;
            color: #888;
        }

        .empty-state p {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="request-card">
            <h1>My Requests</h1>

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

            <?php if (empty($requests)): ?>
                <div class="empty-state">
                    <p>You haven't created any requests yet.</p>
                    <a href="request_new.php" class="btn btn-primary">Create New Request</a>
                </div>
            <?php else: ?>
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
                    ?>
                    <div class="request-item">
                        <div class="request-header">
                            <div>
                                <div class="request-subject"><?php echo htmlspecialchars($request['subject']); ?></div>
                                <?php if (!empty($request['contact_preference'])): ?>
                                    <div style="font-size: 12px; color: #888; margin-top: 4px;">
                                        Contact: <span style="text-transform: capitalize;"><?php echo htmlspecialchars($request['contact_preference']); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <span class="request-status status-<?php echo htmlspecialchars($request['status']); ?>">
                                <?php echo htmlspecialchars($statusLabel); ?>
                            </span>
                        </div>
                        <div class="request-description">
                            <?php echo nl2br(htmlspecialchars($description)); ?>
                            <?php if ($isTruncated): ?>
                                <a href="request_detail.php?id=<?php echo (int)$request['id']; ?>" class="view-details-link">View details</a>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($request['photo_path']) && strpos($request['photo_path'], 'uploads/') === 0 && file_exists(__DIR__ . '/' . $request['photo_path'])): ?>
                            <div style="margin-top: 10px;">
                                <img src="<?php echo htmlspecialchars($request['photo_path']); ?>" 
                                     alt="Photo" 
                                     style="max-width: 200px; max-height: 150px; border-radius: 6px; border: 1px solid #e0e0e0;">
                            </div>
                        <?php endif; ?>
                        <div class="request-meta">
                            Created: <?php echo date('M d, Y H:i', strtotime($request['created_at'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <div class="actions">
                <a href="request_new.php" class="btn btn-primary">Create New Request</a>
                <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
            </div>
        </div>
    </div>
</body>
</html>

