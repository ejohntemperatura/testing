<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a director or admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['director', 'admin'])) {
    header('Location: ../auth/index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'])) {
    $request_id = $_POST['request_id'];
    
    try {
        // Update leave request with director rejection
        $stmt = $pdo->prepare("
            UPDATE leave_requests 
            SET director_approval = 'rejected', 
                director_approved_by = ?, 
                director_approved_at = NOW(),
                status = 'rejected'
            WHERE id = ? AND director_approval = 'pending'
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

header('Location: director_head_dashboard.php');
exit();
?>
