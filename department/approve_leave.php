<?php
session_start();
require_once '../config/database.php';
require_once '../includes/RobustEmail.php';

// Check if user is logged in and is a manager (department head)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['manager', 'admin'])) {
    header('Location: ../auth/index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'])) {
    $request_id = $_POST['request_id'];
    $action = $_POST['action']; // 'approve' or 'reject'
    $reason = $_POST['reason'] ?? '';
    
    try {
        $pdo->beginTransaction();
        
        // Get the leave request details
        $stmt = $pdo->prepare("
            SELECT lr.*, e.name as employee_name, e.email as employee_email
            FROM leave_requests lr 
            JOIN employees e ON lr.employee_id = e.id 
            WHERE lr.id = ? AND lr.dept_head_approval = 'pending'
        ");
        $stmt->execute([$request_id]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$request) {
            throw new Exception('Leave request not found or already processed');
        }
        
        if ($action === 'approve') {
            // Update leave request with department head approval
            $stmt = $pdo->prepare("
                UPDATE leave_requests 
                SET dept_head_approval = 'approved', 
                    dept_head_approved_by = ?, 
                    dept_head_approved_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $request_id]);
            
            $_SESSION['success'] = 'Leave request marked for approval successfully';
            
        } elseif ($action === 'reject') {
            if (empty(trim($reason))) {
                throw new Exception('Disapproval reason is required');
            }
            
            // Update leave request with department head disapproval
            $stmt = $pdo->prepare("
                UPDATE leave_requests 
                SET dept_head_approval = 'rejected', 
                    dept_head_approved_by = ?, 
                    dept_head_approved_at = NOW(),
                    dept_head_rejection_reason = ?,
                    status = 'rejected'
                WHERE id = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $reason, $request_id]);
            
            $_SESSION['success'] = 'Leave request marked for disapproval successfully';
        } else {
            throw new Exception('Invalid action');
        }
        
        $pdo->commit();
        
        // Send email notification to employee
        try {
            $emailNotification = new RobustEmail($pdo);
            $approverName = $_SESSION['name'] ?? 'Department Head';
            
            if ($action === 'approve') {
                $emailResult1 = $emailNotification->sendLeaveStatusNotification($request_id, 'approve', $approverName, 'department_head');
                // Also notify director that request is ready for their approval
                $emailResult2 = $emailNotification->sendApproverNotification($request_id, 'director');
                
                // Log email results for debugging
                error_log("Department Head Approval Emails - Employee: " . ($emailResult1 ? 'Sent' : 'Failed') . ", Director: " . ($emailResult2 ? 'Sent' : 'Failed'));
            } elseif ($action === 'reject') {
                $emailResult = $emailNotification->sendLeaveStatusNotification($request_id, 'reject', $approverName, 'department_head');
                error_log("Department Head Rejection Email - Employee: " . ($emailResult ? 'Sent' : 'Failed'));
            }
        } catch (Exception $e) {
            error_log("Email notification error: " . $e->getMessage());
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = 'Error processing leave request: ' . $e->getMessage();
    }
}

header('Location: department_head_dashboard.php');
exit();
?>
