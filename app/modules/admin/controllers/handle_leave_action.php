<?php
session_start();
require_once '../../../../config/database.php';
require_once '../../../../app/core/services/LeaveCreditsManager.php';

// Check if user is logged in and has admin/manager/director role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin','manager','director'])) {
    header('Location: ../../../auth/views/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $leave_request_id = $_POST['leave_request_id'] ?? '';
    $reason = $_POST['reason'] ?? '';
    
    if (empty($action) || empty($leave_request_id)) {
        $_SESSION['error'] = "Invalid request parameters.";
        header('Location: leave_management.php');
        exit();
    }
    
    try {
        $creditsManager = new LeaveCreditsManager($pdo);
        
        // Handle the leave status change
        $result = $creditsManager->handleLeaveStatusChange($leave_request_id, $action);
        
        if ($result) {
            $status_message = ucfirst($action);
            if ($action === 'rejected') {
                $_SESSION['success'] = "Leave request {$status_message} successfully. Leave credits have been restored.";
            } else {
                $_SESSION['success'] = "Leave request {$status_message} successfully.";
            }
        } else {
            $_SESSION['error'] = "Failed to update leave request status.";
        }
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Error processing leave request: " . $e->getMessage();
    }
    
    // Redirect back to leave management
    header('Location: leave_management.php');
    exit();
} else {
    header('Location: leave_management.php');
    exit();
}
?>
