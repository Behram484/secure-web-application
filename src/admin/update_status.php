<?php

session_start();
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/csrf.php';

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

// 3) Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: requests.php');
    exit;
}

// 4) CSRF Protection
if (!verifyCSRFToken()) {
    $_SESSION['flash_error'] = 'Invalid security token. Please try again.';
    header('Location: requests.php');
    exit;
}

// Status label mapping (must match requests.php)
$allowedStatuses = [
    'pending',
    'open',
    'closed',
    'in_progress',
    'resolved',
];

$statusLabels = [
    'pending'      => 'Pending',
    'open'         => 'Open',
    'closed'       => 'Closed',
    'in_progress'  => 'In Progress',
    'resolved'     => 'Resolved',
];

// Get and validate input
$requestId = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
$newStatus = isset($_POST['status']) ? trim($_POST['status']) : '';

// Validate request ID
if ($requestId <= 0) {
    $_SESSION['flash_error'] = 'Invalid request ID.';
    header('Location: requests.php');
    exit;
}

// Validate status
if (!in_array($newStatus, $allowedStatuses)) {
    $_SESSION['flash_error'] = 'Invalid status value.';
    header('Location: requests.php');
    exit;
}

// Check if request exists
$stmt = $pdo->prepare('SELECT id, status FROM requests WHERE id = ?');
$stmt->execute([$requestId]);
$request = $stmt->fetch();

if (!$request) {
    $_SESSION['flash_error'] = 'Request not found.';
    header('Location: requests.php');
    exit;
}

// Update status
try {
    $stmt = $pdo->prepare(
        'UPDATE requests SET status = ?, updated_at = NOW() WHERE id = ?'
    );
    $stmt->execute([$newStatus, $requestId]);
    
    // Get friendly status labels
    $oldStatusLabel = $statusLabels[$request['status']] ?? $request['status'];
    $newStatusLabel = $statusLabels[$newStatus] ?? $newStatus;
    
    $_SESSION['flash_success'] = "Status updated: {$oldStatusLabel} → {$newStatusLabel}";
} catch (PDOException $e) {
    $_SESSION['flash_error'] = 'Failed to update status: ' . $e->getMessage();
}

// Decide where to redirect after update

// 1) If from request_detail.php and want to stay on detail page
if (isset($_POST['redirect_to']) && $_POST['redirect_to'] === 'detail') {
    $redirectUrl = '../request_detail.php?id=' . $requestId;
} else {
    // 2) Default: back to admin/requests.php, preserve filter if exists
    $redirectUrl = 'requests.php';

    if (isset($_POST['return_filter']) && !empty($_POST['return_filter'])) {
        $filter = trim($_POST['return_filter']);
        if (in_array($filter, $allowedStatuses)) {
            $redirectUrl .= '?status=' . urlencode($filter);
        }
    }
}

header('Location: ' . $redirectUrl);
exit;

