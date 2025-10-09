<?php
// Shared Leave Actions Component
// This file handles approve/reject leave functionality for all roles
// Used by: admin, director, department heads

session_start();
require_once '../../../../config/database.php';

// Check if user is logged in and has permission
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'manager', 'director'])) {
    header('Location: ../auth/index.php');
    exit();
}

$action = $_GET['action'] ?? '';
$request_id = $_GET['request_id'] ?? null;

if (!$request_id) {
    header('Location: ' . $_SERVER['HTTP_REFERER'] ?? '../index.php');
    exit();
}

// Get leave request details
$stmt = $pdo->prepare("
    SELECT lr.*, e.name as employee_name, e.email as employee_email 
    FROM leave_requests lr 
    JOIN employees e ON lr.employee_id = e.id 
    WHERE lr.id = ?
");
$stmt->execute([$request_id]);
$leave_request = $stmt->fetch();

if (!$leave_request) {
    header('Location: ' . $_SERVER['HTTP_REFERER'] ?? '../index.php');
    exit();
}

// Handle actions
if ($action === 'approve') {
    $stmt = $pdo->prepare("UPDATE leave_requests SET status = 'approved' WHERE id = ?");
    $stmt->execute([$request_id]);
    
    // Send notification email
    require_once '../../../includes/EmailService.php';
    $emailService = new EmailService();
    $emailService->sendLeaveStatusNotification(
        $leave_request['employee_email'],
        $leave_request['employee_name'],
        'approved',
        $leave_request['start_date'],
        $leave_request['end_date'],
        $leave_request['leave_type']
    );
    
    $message = 'Leave request approved successfully';
    
} elseif ($action === 'reject') {
    $stmt = $pdo->prepare("UPDATE leave_requests SET status = 'rejected' WHERE id = ?");
    $stmt->execute([$request_id]);
    
    // Send notification email
    require_once '../../../includes/EmailService.php';
    $emailService = new EmailService();
    $emailService->sendLeaveStatusNotification(
        $leave_request['employee_email'],
        $leave_request['employee_name'],
        'rejected',
        $leave_request['start_date'],
        $leave_request['end_date'],
        $leave_request['leave_type']
    );
    
    $message = 'Leave request rejected';
}

// Redirect back with message
$redirect_url = $_SERVER['HTTP_REFERER'] ?? '../index.php';
$redirect_url .= (strpos($redirect_url, '?') !== false ? '&' : '?') . 'message=' . urlencode($message);
header('Location: ' . $redirect_url);
exit();
?>
