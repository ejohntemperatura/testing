<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a manager (department head)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['manager', 'admin'])) {
    header('Location: ../auth/index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'])) {
    $request_id = $_POST['request_id'];
    
    try {
        // Update leave request with department head rejection
        $stmt = $pdo->prepare("
            UPDATE leave_requests 
            SET dept_head_approval = 'rejected', 
                dept_head_approved_by = ?, 
                dept_head_approved_at = NOW(),
                status = 'rejected'
            WHERE id = ? AND dept_head_approval = 'pending'
        ");
        $result = $stmt->execute([$_SESSION['user_id'], $request_id]);
        
        if ($stmt->rowCount() > 0) {
            $_SESSION['success'] = 'Leave request rejected successfully';
        } else {
            $_SESSION['error'] = 'Leave request not found or already processed';
        }
        
    } catch (Exception $e) {
        $_SESSION['error'] = 'Error rejecting leave request: ' . $e->getMessage();
    }
}

header('Location: department_head_dashboard.php');
exit();
?>
