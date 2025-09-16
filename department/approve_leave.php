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
        $pdo->beginTransaction();
        
        // Get the leave request details
        $stmt = $pdo->prepare("
            SELECT lr.*, e.annual_leave_balance, e.sick_leave_balance 
            FROM leave_requests lr 
            JOIN employees e ON lr.employee_id = e.id 
            WHERE lr.id = ? AND lr.status = 'pending'
        ");
        $stmt->execute([$request_id]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$request) {
            throw new Exception('Leave request not found or already processed');
        }
        
        // Calculate days
        $start = new DateTime($request['start_date']);
        $end = new DateTime($request['end_date']);
        $days = $start->diff($end)->days + 1;
        
        // Update leave request with department head approval
        $stmt = $pdo->prepare("
            UPDATE leave_requests 
            SET dept_head_approval = 'approved', 
                dept_head_approved_by = ?, 
                dept_head_approved_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $request_id]);
        
        // Deduct from appropriate leave balance
        $leave_type = strtolower(trim($request['leave_type']));
        $balance_field = $leave_type . '_leave_balance';
        
        // Check if balance field exists and has sufficient balance
        $stmt = $pdo->prepare("SELECT {$balance_field} FROM employees WHERE id = ?");
        $stmt->execute([$request['employee_id']]);
        $current_balance = $stmt->fetchColumn();
        
        if ($current_balance < $days) {
            throw new Exception("Insufficient leave balance. Available: {$current_balance} days, Required: {$days} days");
        }
        
        // Deduct leave balance
        $stmt = $pdo->prepare("UPDATE employees SET {$balance_field} = {$balance_field} - ? WHERE id = ?");
        $stmt->execute([$days, $request['employee_id']]);
        
        $pdo->commit();
        $_SESSION['success'] = 'Leave request approved successfully';
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = 'Error approving leave request: ' . $e->getMessage();
    }
}

header('Location: department_head_dashboard.php');
exit();
?>
