<?php
/**
 * Admin Functions for ELMS
 * Provides utility functions for admin operations
 */

require_once '../../../../config/database.php';

/**
 * Log audit events
 */
function logAuditEvent($pdo, $user_id, $action, $details, $ip_address = null) {
    if ($ip_address === null) {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (user_id, action, details, ip_address, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$user_id, $action, $details, $ip_address]);
        return true;
    } catch (PDOException $e) {
        error_log("Error logging audit event: " . $e->getMessage());
        return false;
    }
}

/**
 * Send email notification for leave status updates
 */
function sendLeaveStatusEmail($employee_email, $employee_name, $status, $start_date, $end_date) {
    if (!filter_var($employee_email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $to = $employee_email;
    $subject = "Leave Request Update - ELMS";
    
    $message = "
    <html>
    <head>
    <!-- OFFLINE Tailwind CSS - No internet required! -->
    <link rel="stylesheet" href="../../../../assets/css/tailwind.css">
    <link rel="stylesheet" href="../../../../assets/css/font-awesome-local.css">

        <title>Leave Request Update</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #4CAF50; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background-color: #f9f9f9; }
            .details { background-color: #fff; padding: 15px; border-radius: 5px; margin: 20px 0; border: 1px solid #ddd; }
            .status { font-weight: bold; color: #4CAF50; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 0.9em; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Leave Request Update</h2>
            </div>
            <div class='content'>
                <p>Dear {$employee_name},</p>
                <p>Your leave request has been <span class='status'>{$status}</span>.</p>
                <div class='details'>
                    <h3>Leave Details:</h3>
                    <p><strong>Start Date:</strong> {$start_date}</p>
                    <p><strong>End Date:</strong> {$end_date}</p>
                </div>
                <p>If you have any questions, please contact your supervisor or the HR department.</p>
            </div>
            <div class='footer'>
                <p>This is an automated message from the E-Learning Management System (ELMS).</p>
                <p>Please do not reply to this email.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: ELMS <noreply@elms.com>" . "\r\n";
    
    return mail($to, $subject, $message, $headers);
}

/**
 * Get dashboard statistics
 */
function getDashboardStats($pdo) {
    $stats = [];
    
    try {
        // Total employees
        $stmt = $pdo->query("SELECT COUNT(*) FROM employees");
        $stats['total_employees'] = $stmt->fetchColumn();
        
        // Leave request statistics
        $stmt = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'pending'");
        $stats['pending_requests'] = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'approved'");
        $stats['approved_requests'] = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'rejected'");
        $stats['rejected_requests'] = $stmt->fetchColumn();
        
        // Department statistics
        $stmt = $pdo->query("
            SELECT department, COUNT(*) as count 
            FROM employees 
            GROUP BY department 
            ORDER BY count DESC
        ");
        $stats['department_stats'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Recent activities
        $stmt = $pdo->query("
            SELECT al.*, e.name as user_name 
            FROM audit_logs al 
            LEFT JOIN employees e ON al.user_id = e.id 
            ORDER BY al.created_at DESC 
            LIMIT 10
        ");
        $stats['recent_activities'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error getting dashboard stats: " . $e->getMessage());
    }
    
    return $stats;
}

/**
 * Get leave requests with filters
 */
function getLeaveRequests($pdo, $filters = []) {
    $where_conditions = [];
    $params = [];
    
    if (isset($filters['status']) && $filters['status'] !== '') {
        $where_conditions[] = "lr.status = ?";
        $params[] = $filters['status'];
    }
    
    if (isset($filters['employee']) && $filters['employee'] !== '') {
        $where_conditions[] = "e.name LIKE ?";
        $params[] = '%' . $filters['employee'] . '%';
    }
    
    if (isset($filters['leave_type']) && $filters['leave_type'] !== '') {
        $where_conditions[] = "lr.leave_type = ?";
        $params[] = $filters['leave_type'];
    }
    
    if (isset($filters['date_from']) && $filters['date_from'] !== '') {
        $where_conditions[] = "lr.start_date >= ?";
        $params[] = $filters['date_from'];
    }
    
    if (isset($filters['date_to']) && $filters['date_to'] !== '') {
        $where_conditions[] = "lr.start_date <= ?";
        $params[] = $filters['date_to'];
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    try {
        $query = "
            SELECT lr.*, e.name as employee_name, e.email as employee_email, e.department
            FROM leave_requests lr 
            JOIN employees e ON lr.employee_id = e.id 
            $where_clause
            ORDER BY lr.created_at DESC
        ";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting leave requests: " . $e->getMessage());
        return [];
    }
}

/**
 * Update leave request status
 */
function updateLeaveRequestStatus($pdo, $leave_id, $status, $admin_id) {
    try {
        $pdo->beginTransaction();
        
        // Get leave request details
        $stmt = $pdo->prepare("
            SELECT lr.*, e.name as employee_name, e.email as employee_email 
            FROM leave_requests lr 
            JOIN employees e ON lr.employee_id = e.id 
            WHERE lr.id = ?
        ");
        $stmt->execute([$leave_id]);
        $leave_request = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$leave_request) {
            throw new Exception("Leave request not found");
        }
        
        // Update status
        $stmt = $pdo->prepare("UPDATE leave_requests SET status = ? WHERE id = ?");
        $stmt->execute([$status, $leave_id]);
        
        // Send email notification
        $email_sent = sendLeaveStatusEmail(
            $leave_request['employee_email'],
            $leave_request['employee_name'],
            $status,
            $leave_request['start_date'],
            $leave_request['end_date']
        );
        
        // Log the action
        logAuditEvent($pdo, $admin_id, 'UPDATE_LEAVE_STATUS', 
            "Updated leave request #{$leave_id} to {$status} for {$leave_request['employee_name']}");
        
        $pdo->commit();
        
        return [
            'success' => true,
            'email_sent' => $email_sent,
            'message' => "Leave request status updated successfully!"
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error updating leave request status: " . $e->getMessage());
        return [
            'success' => false,
            'message' => "Error updating leave request: " . $e->getMessage()
        ];
    }
}

/**
 * Get user statistics
 */
function getUserStats($pdo) {
    try {
        $stats = [];
        
        // Total users by role
        $stmt = $pdo->query("
            SELECT role, COUNT(*) as count 
            FROM employees 
            GROUP BY role
        ");
        $stats['role_stats'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Users by department
        $stmt = $pdo->query("
            SELECT department, COUNT(*) as count 
            FROM employees 
            GROUP BY department 
            ORDER BY count DESC
        ");
        $stats['department_stats'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Recent user activities
        $stmt = $pdo->query("
            SELECT al.*, e.name as user_name 
            FROM audit_logs al 
            LEFT JOIN employees e ON al.user_id = e.id 
            WHERE al.action LIKE '%USER%' 
            ORDER BY al.created_at DESC 
            LIMIT 10
        ");
        $stats['recent_user_activities'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $stats;
    } catch (PDOException $e) {
        error_log("Error getting user stats: " . $e->getMessage());
        return [];
    }
}

/**
 * Export data to CSV
 */
function exportToCSV($data, $filename) {
    if (empty($data)) {
        return false;
    }
    
    $headers = array_keys($data[0]);
    $csv = fopen('php://temp', 'r+');
    
    // Add headers
    fputcsv($csv, $headers);
    
    // Add data rows
    foreach ($data as $row) {
        fputcsv($csv, $row);
    }
    
    rewind($csv);
    $csv_content = stream_get_contents($csv);
    fclose($csv);
    
    // Set headers for download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($csv_content));
    
    echo $csv_content;
    return true;
}

/**
 * Validate admin access
 */
function validateAdminAccess() {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        header('Location: index.php');
        exit();
    }
}

/**
 * Get pagination info
 */
function getPaginationInfo($total_records, $per_page, $current_page) {
    $total_pages = ceil($total_records / $per_page);
    $offset = ($current_page - 1) * $per_page;
    
    return [
        'total_pages' => $total_pages,
        'offset' => $offset,
        'current_page' => $current_page,
        'per_page' => $per_page,
        'total_records' => $total_records
    ];
}

/**
 * Format date for display
 */
function formatDate($date, $format = 'M d, Y') {
    return date($format, strtotime($date));
}

/**
 * Get status badge class
 */
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'approved':
            return 'success';
        case 'pending':
            return 'warning';
        case 'rejected':
            return 'danger';
        default:
            return 'secondary';
    }
}

/**
 * Sanitize output
 */
function sanitizeOutput($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}
?> 