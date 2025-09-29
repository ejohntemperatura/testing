<?php
session_start();
require_once '../config/database.php';
require_once '../includes/LeaveCreditsManager.php';


if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../auth/index.php');
    exit();
}

$employee_id = $_SESSION['user_id'];

// Verify employee exists in database
$stmt = $pdo->prepare("SELECT id FROM employees WHERE id = ?");
$stmt->execute([$employee_id]);
if (!$stmt->fetch()) {
    session_destroy();
    $_SESSION['error'] = "Your session has expired. Please log in again.";
    header('Location: ../auth/index.php');
    exit();
}

$leave_type = $_POST['leave_type'];
$start_date = $_POST['start_date'];
$end_date = $_POST['end_date'];
$reason = $_POST['reason'];

// Calculate number of days (inclusive)
$start = new DateTime($start_date);
$end = new DateTime($end_date);
if ($end < $start) {
    $_SESSION['error'] = "End date cannot be before start date.";
    header('Location: dashboard.php');
    exit();
}
$interval = $start->diff($end);
$days = $interval->days + 1; // Include both start and end dates

// Check leave credits using the LeaveCreditsManager
$creditsManager = new LeaveCreditsManager($pdo);
$creditCheck = $creditsManager->checkLeaveCredits($employee_id, $leave_type, $start_date, $end_date);

if (!$creditCheck['sufficient']) {
    $_SESSION['error'] = $creditCheck['message'];
    header('Location: dashboard.php');
    exit();
}

try {
    // Begin transaction
    $pdo->beginTransaction();

    // Insert leave request
    $stmt = $pdo->prepare("INSERT INTO leave_requests (employee_id, leave_type, start_date, end_date, reason, status, days_requested, created_at) VALUES (?, ?, ?, ?, ?, 'pending', ?, NOW())");
    $stmt->execute([$employee_id, $leave_type, $start_date, $end_date, $reason, $days]);

    // Deduct leave credits immediately when applying
    $creditsManager->deductLeaveCredits($employee_id, $leave_type, $start_date, $end_date);

    $pdo->commit();
    $_SESSION['success'] = "Leave request submitted successfully. Leave credits have been deducted.";
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error'] = "Error submitting leave request: " . $e->getMessage();
}

header('Location: dashboard.php');
exit();
?> 