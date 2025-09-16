<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

$employee_id = $_SESSION['user_id'];
$leave_type = $_POST['leave_type'];
$start_date = $_POST['start_date'];
$end_date = $_POST['end_date'];
$reason = $_POST['reason'];

// Handle medical certificate upload for sick leave
$medical_certificate_path = null;
if ($leave_type === 'sick' && isset($_FILES['medical_certificate']) && $_FILES['medical_certificate']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = 'uploads/medical_certificates/';
    
    // Create directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file = $_FILES['medical_certificate'];
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];
    
    // Validate file extension
    if (!in_array($file_extension, $allowed_extensions)) {
        $_SESSION['error'] = "Invalid file type. Please upload JPG, PNG, or PDF files only.";
        header('Location: dashboard.php');
        exit();
    }
    
    // Validate file size (5MB max)
    if ($file['size'] > 5 * 1024 * 1024) {
        $_SESSION['error'] = "File size too large. Maximum size is 5MB.";
        header('Location: dashboard.php');
        exit();
    }
    
    // Generate unique filename
    $filename = 'medical_cert_' . $employee_id . '_' . time() . '.' . $file_extension;
    $medical_certificate_path = $upload_dir . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $medical_certificate_path)) {
        $_SESSION['error'] = "Failed to upload medical certificate.";
        header('Location: dashboard.php');
        exit();
    }
} elseif ($leave_type === 'sick') {
    $_SESSION['error'] = "Medical certificate is required for sick leave applications.";
    header('Location: dashboard.php');
    exit();
}

// Calculate number of days
$start = new DateTime($start_date);
$end = new DateTime($end_date);
$interval = $start->diff($end);
$days = $interval->days + 1; // Include both start and end dates

// Check leave balance (but don't deduct yet - only when approved)
$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->execute([$employee_id]);
$employee = $stmt->fetch();

$balance_field = $leave_type . '_leave_balance';
if ($employee[$balance_field] < $days) {
    $_SESSION['error'] = "Insufficient leave balance. Available: " . $employee[$balance_field] . " days, Required: " . $days . " days";
    header('Location: dashboard.php');
    exit();
}

try {
    // Begin transaction
    $pdo->beginTransaction();

    // Insert leave request (balance will be deducted when approved)
    $stmt = $pdo->prepare("INSERT INTO leave_requests (employee_id, leave_type, start_date, end_date, reason, medical_certificate_path, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())");
    $stmt->execute([$employee_id, $leave_type, $start_date, $end_date, $reason, $medical_certificate_path]);

    $pdo->commit();
    $_SESSION['success'] = "Leave request submitted successfully";
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error'] = "Error submitting leave request: " . $e->getMessage();
}

header('Location: dashboard.php');
exit();
?> 