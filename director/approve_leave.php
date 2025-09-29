<?php
session_start();
require_once '../config/database.php';
require_once '../includes/RobustEmail.php';

// Check if user is logged in and is a director or admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['director', 'admin'])) {
    header('Location: ../auth/index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'])) {
        $request_id = $_POST['request_id'];
        $action = $_POST['action']; // 'approve_with_pay' or 'approve_without_pay' or 'reject' or 'others'
        $reason = $_POST['reason'] ?? $_POST['rejection_reason'] ?? '';
    
    try {
        $pdo->beginTransaction();
        
        // Get the leave request details
        $stmt = $pdo->prepare("
            SELECT lr.*, e.name as employee_name, e.email as employee_email
            FROM leave_requests lr 
            JOIN employees e ON lr.employee_id = e.id 
            WHERE lr.id = ? AND lr.director_approval = 'pending' AND lr.dept_head_approval = 'approved'
        ");
        $stmt->execute([$request_id]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$request) {
            throw new Exception('Leave request not found, already processed, or department head approval required');
        }
        
        // Calculate days
        $start = new DateTime($request['start_date']);
        $end = new DateTime($request['end_date']);
        $days = $start->diff($end)->days + 1;
        
        if ($action === 'approve_with_pay') {
            // Get days input from form
            $days_input = $_POST['days_input'] ?? $days;
            if (!is_numeric($days_input) || $days_input < 1) {
                throw new Exception('Invalid number of days for approval with pay');
            }
            
            // Update leave request with director approval with pay
            $stmt = $pdo->prepare("
                UPDATE leave_requests 
                SET director_approval = 'approved', 
                    director_approved_by = ?, 
                    director_approved_at = NOW(),
                    approved_days_with_pay = ?,
                    status = 'approved',
                    final_approval_status = 'approved',
                    approved_by = ?,
                    approved_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $days_input, $_SESSION['user_id'], $request_id]);
            
            $_SESSION['success'] = 'Leave request approved with pay successfully';
            
        } elseif ($action === 'approve_without_pay') {
            // Get days input from form
            $days_input = $_POST['days_input'] ?? $days;
            if (!is_numeric($days_input) || $days_input < 1) {
                throw new Exception('Invalid number of days for approval without pay');
            }
            
            // Update leave request with director approval without pay
            $stmt = $pdo->prepare("
                UPDATE leave_requests 
                SET director_approval = 'approved', 
                    director_approved_by = ?, 
                    director_approved_at = NOW(),
                    approved_days_without_pay = ?,
                    status = 'approved',
                    final_approval_status = 'approved',
                    approved_by = ?,
                    approved_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $days_input, $_SESSION['user_id'], $request_id]);
            
            $_SESSION['success'] = 'Leave request approved without pay successfully';
            
        } elseif ($action === 'reject') {
            if (empty(trim($reason))) {
                throw new Exception('Rejection reason is required');
            }
            
            // Update leave request with director rejection
            $stmt = $pdo->prepare("
                UPDATE leave_requests 
                SET director_approval = 'rejected', 
                    director_approved_by = ?, 
                    director_approved_at = NOW(),
                    director_rejection_reason = ?,
                    status = 'rejected'
                WHERE id = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $reason, $request_id]);
            
            $_SESSION['success'] = 'Leave request rejected successfully';
            
        } elseif ($action === 'others') {
            $others_specify = $_POST['others_specify'] ?? '';
            if (empty(trim($others_specify))) {
                throw new Exception('Please specify the approval decision');
            }
            
            // Update leave request with director others approval
            $stmt = $pdo->prepare("
                UPDATE leave_requests 
                SET director_approval = 'approved', 
                    director_approved_by = ?, 
                    director_approved_at = NOW(),
                    director_approval_notes = ?,
                    status = 'approved',
                    final_approval_status = 'approved',
                    approved_by = ?,
                    approved_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $others_specify, $_SESSION['user_id'], $request_id]);
            
            $_SESSION['success'] = 'Leave request approved with custom decision: ' . $others_specify;
        } else {
            throw new Exception('Invalid action');
        }
        
        $pdo->commit();
        
        // Send email notification to employee
        try {
            $emailNotification = new RobustEmail($pdo);
            $approverName = $_SESSION['name'] ?? 'Director';
            
            $emailResult = false;
            if ($action === 'approve_with_pay') {
                $emailResult = $emailNotification->sendLeaveStatusNotification($request_id, 'approve_with_pay', $approverName, 'director');
            } elseif ($action === 'approve_without_pay') {
                $emailResult = $emailNotification->sendLeaveStatusNotification($request_id, 'approve_without_pay', $approverName, 'director');
            } elseif ($action === 'reject') {
                $emailResult = $emailNotification->sendLeaveStatusNotification($request_id, 'reject', $approverName, 'director');
            } elseif ($action === 'others') {
                $emailResult = $emailNotification->sendLeaveStatusNotification($request_id, 'others', $approverName, 'director');
            }
            
            // Log email result for debugging
            error_log("Director Approval Email - Action: $action, Result: " . ($emailResult ? 'Sent' : 'Failed'));
        } catch (Exception $e) {
            error_log("Director email notification error: " . $e->getMessage());
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = 'Error processing leave request: ' . $e->getMessage();
    }
}

header('Location: director_head_dashboard.php');
exit();
?>
