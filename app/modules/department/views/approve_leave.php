<?php
session_start();
require_once '../../../../config/database.php';

// Check if user is logged in and is a department head
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    header('Location: ../../../../auth/views/login.php');
    exit();
}

// Get request ID from GET or POST
$request_id = $_GET['id'] ?? $_POST['request_id'] ?? '';
$action = $_POST['action'] ?? 'approve';

if (empty($request_id)) {
    $_SESSION['error'] = 'Invalid request ID. Please try again.';
    header('Location: dashboard.php');
    exit();
}

try {
    $pdo->beginTransaction();
    
    // Get leave request details
    $stmt = $pdo->prepare("SELECT lr.*, e.id as emp_id, e.name, e.email FROM leave_requests lr JOIN employees e ON lr.employee_id = e.id WHERE lr.id = ?");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$request) {
        throw new Exception('Leave request not found');
    }
    
    // Handle approval or rejection based on action
    if ($action === 'approve') {
        // Update department head approval status
        $stmt = $pdo->prepare("UPDATE leave_requests SET dept_head_approval = 'approved', dept_head_approved_by = ?, dept_head_approved_at = NOW() WHERE id = ?");
        $stmt->execute([$_SESSION['user_id'], $request_id]);
    
        // Deduct leave credits
        $start = new DateTime($request['start_date']);
        $end = new DateTime($request['end_date']);
        $days = $start->diff($end)->days + 1;
        $leave_type = strtolower(trim($request['leave_type']));
        $balance_field = $leave_type . '_leave_balance';
        
        // Don't deduct leave credits yet - wait for final approval from director
        $_SESSION['success'] = 'Leave request approved by Department Head! Now waiting for Director approval.';
        
        // Send email notification for department head approval
        try {
            require_once '../../../../app/core/services/EmailService.php';
            $emailService = new EmailService();
            
            // Get department head name for email
            $stmt = $pdo->prepare("SELECT name FROM employees WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $dept_head = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $emailService->sendLeaveStatusNotification(
                $request['email'],
                $request['name'],
                'dept_approved',
                $request['start_date'],
                $request['end_date'],
                $request['leave_type'],
                $dept_head['name'] ?? 'Department Head',
                'manager'
            );
        } catch (Exception $e) {
            error_log("Email notification failed: " . $e->getMessage());
        }
        
    } else {
        // Handle rejection
        $reason = $_POST['reason'] ?? 'No reason provided';
        
        // Update department head approval status to rejected
        $stmt = $pdo->prepare("UPDATE leave_requests SET dept_head_approval = 'rejected', dept_head_approved_by = ?, dept_head_approved_at = NOW(), dept_head_rejection_reason = ?, status = 'rejected' WHERE id = ?");
        $stmt->execute([$_SESSION['user_id'], $reason, $request_id]);
        
        $_SESSION['success'] = 'Leave request rejected successfully!';
        
        // Send email notification for rejection
        try {
            require_once '../../../../app/core/services/EmailService.php';
            $emailService = new EmailService();
            
            // Get department head name for email
            $stmt = $pdo->prepare("SELECT name FROM employees WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $dept_head = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $emailService->sendLeaveStatusNotification(
                $request['email'],
                $request['name'],
                'rejected',
                $request['start_date'],
                $request['end_date'],
                $request['leave_type'],
                $dept_head['name'] ?? 'Department Head',
                'manager'
            );
        } catch (Exception $e) {
            error_log("Email notification failed: " . $e->getMessage());
        }
    }
    
    $pdo->commit();
    
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error'] = 'Error processing leave request: ' . $e->getMessage();
}

header('Location: dashboard.php');
exit();
?>
