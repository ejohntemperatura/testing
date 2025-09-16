<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../auth/index.php');
    exit();
}

$employee_id = $_SESSION['user_id'];
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

// Check leave balance
$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->execute([$employee_id]);
$employee = $stmt->fetch();

// Map leave type to existing balance columns
// Normalize incoming leave type (handles labels like "Annual Leave" or values like "annual")
// Resolve balance field dynamically based on leave type and available columns
$normalized_type = strtolower(trim($leave_type));
$static_map = [
    'annual leave' => 'annual_leave_balance',
    'annual' => 'annual_leave_balance',
    'vacation leave' => 'vacation_leave_balance',
    'vacation' => 'vacation_leave_balance',
    'sick leave' => 'sick_leave_balance',
    'sick' => 'sick_leave_balance',
    'emergency leave' => 'emergency_leave_balance',
    'emergency' => 'emergency_leave_balance',
    'maternity leave' => 'maternity_leave_balance',
    'maternity' => 'maternity_leave_balance',
    'paternity leave' => 'paternity_leave_balance',
    'paternity' => 'paternity_leave_balance',
    'bereavement leave' => 'bereavement_leave_balance',
    'bereavement' => 'bereavement_leave_balance',
    'study leave' => 'study_leave_balance',
    'study' => 'study_leave_balance'
];

$balance_field = $static_map[$normalized_type] ?? null;
if ($balance_field === null) {
    // Heuristic fallback: convert type to snake_case and append _leave_balance
    $slug = preg_replace('/[^a-z0-9]+/i', '_', $normalized_type);
    $slug = trim($slug, '_');
    if ($slug !== '') {
        $candidate = $slug . '_leave_balance';
        if (array_key_exists($candidate, $employee)) {
            $balance_field = $candidate;
        }
    }
}

// For types without a tracked balance (e.g., maternity, paternity, bereavement, study, unpaid),
// allow submission without balance checks/deductions
if ($balance_field !== null && array_key_exists($balance_field, $employee)) {
    $available = (int)$employee[$balance_field];
    if ($available < $days) {
        $_SESSION['error'] = "Insufficient leave balance. Required: {$days} day(s), Available: {$available}.";
        header('Location: dashboard.php');
        exit();
    }
} else {
    // If the mapped column doesn't exist in DB, treat as untracked (no balance check)
    $balance_field = null;
}

if ($employee[$balance_field] < $days) {
    $_SESSION['error'] = "Insufficient leave balance";
    header('Location: dashboard.php');
    exit();
}

try {
    // Begin transaction
    $pdo->beginTransaction();

    // Insert leave request
    $stmt = $pdo->prepare("INSERT INTO leave_requests (employee_id, leave_type, start_date, end_date, reason, status, created_at) VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
    $stmt->execute([$employee_id, $leave_type, $start_date, $end_date, $reason]);

    // Do NOT deduct balance here. Deduction will happen when an admin approves the request.

    $pdo->commit();
    $_SESSION['success'] = "Leave request submitted successfully";
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error'] = "Error submitting leave request: " . $e->getMessage();
}

header('Location: dashboard.php');
exit();
?> 