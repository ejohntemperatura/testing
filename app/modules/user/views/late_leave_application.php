<?php
session_start();
require_once '../../../../config/database.php';
require_once '../../../../app/core/services/LeaveCreditsManager.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../../auth/views/login.php');
    exit();
}

$employee_id = $_SESSION['user_id'];

// Verify employee exists in database
$stmt = $pdo->prepare("SELECT id FROM employees WHERE id = ?");
$stmt->execute([$employee_id]);
if (!$stmt->fetch()) {
    session_destroy();
    $_SESSION['error'] = "Your session has expired. Please log in again.";
    header('Location: ../../../auth/views/login.php');
    exit();
}

$leave_type = $_POST['leave_type'];
$start_date = $_POST['start_date'];
$end_date = $_POST['end_date'];
$reason = $_POST['reason'];
$late_justification = $_POST['late_justification'];

// Get conditional fields based on leave type
$location_type = $_POST['location_type'] ?? null;
$location_specify = $_POST['location_specify'] ?? null;
$medical_condition = $_POST['medical_condition'] ?? null;
$illness_specify = $_POST['illness_specify'] ?? null;
$special_women_condition = $_POST['special_women_condition'] ?? null;
$study_type = $_POST['study_type'] ?? null;

// Handle medical certificate upload for sick leave
$medical_certificate_path = null;
if ($leave_type === 'sick' && isset($_FILES['medical_certificate']) && $_FILES['medical_certificate']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = '../../../../uploads/medical_certificates/' . date('Y') . '/' . date('m') . '/';
    
    // Create directory if it doesn't exist
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $allowed_types = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
    $max_size = 10 * 1024 * 1024; // 10MB
    
    $file_extension = strtolower(pathinfo($_FILES['medical_certificate']['name'], PATHINFO_EXTENSION));
    $file_size = $_FILES['medical_certificate']['size'];
    
    if (!in_array($file_extension, $allowed_types)) {
        $_SESSION['error'] = "Invalid file type. Only PDF, JPG, JPEG, PNG, DOC, DOCX files are allowed.";
        header('Location: dashboard.php');
        exit();
    }
    
    if ($file_size > $max_size) {
        $_SESSION['error'] = "File size too large. Maximum size allowed is 10MB.";
        header('Location: dashboard.php');
        exit();
    }
    
    // Generate unique filename
    $filename = uniqid() . '_' . time() . '.' . $file_extension;
    $file_path = $upload_dir . $filename;
    
    if (move_uploaded_file($_FILES['medical_certificate']['tmp_name'], $file_path)) {
        $medical_certificate_path = $file_path;
    } else {
        $_SESSION['error'] = "Failed to upload medical certificate.";
        header('Location: dashboard.php');
        exit();
    }
}

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

// Check if the leave application is for future dates (should use regular application)
$today = new DateTime();
$today->setTime(0, 0, 0); // Reset time to start of day for accurate comparison

if ($start >= $today) {
    // This is not a late application - redirect to regular application form
    $_SESSION['regular_application_data'] = [
        'leave_type' => $leave_type,
        'start_date' => $start_date,
        'end_date' => $end_date,
        'reason' => $reason,
        'location_type' => $location_type,
        'location_specify' => $location_specify,
        'medical_condition' => $medical_condition,
        'illness_specify' => $illness_specify,
        'special_women_condition' => $special_women_condition,
        'study_type' => $study_type,
        'medical_certificate_path' => $medical_certificate_path,
        'days' => $days
    ];
    
    $_SESSION['info'] = "You are applying for leave with future dates. Please use the Regular Leave Application form for future leave requests.";
    header('Location: dashboard.php');
    exit();
}

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

    // Insert late leave request with conditional fields and late justification
    $stmt = $pdo->prepare("INSERT INTO leave_requests (employee_id, leave_type, start_date, end_date, reason, status, days_requested, location_type, location_specify, medical_condition, illness_specify, special_women_condition, study_type, medical_certificate_path, late_justification, is_late, created_at) VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())");
    $stmt->execute([$employee_id, $leave_type, $start_date, $end_date, $reason, $days, $location_type, $location_specify, $medical_condition, $illness_specify, $special_women_condition, $study_type, $medical_certificate_path, $late_justification]);

    // Deduct leave credits immediately when applying
    $creditsManager->deductLeaveCredits($employee_id, $leave_type, $start_date, $end_date);

    $pdo->commit();
    $_SESSION['success'] = "Late leave application submitted successfully. Leave credits have been deducted. Please note that late applications may require additional approval.";
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error'] = "Error submitting late leave application: " . $e->getMessage();
}

header('Location: dashboard.php');
exit();
?>
