<?php
/**
 * Notification Helper for Leave Management System
 * Handles finding appropriate approvers and sending notifications
 */

class NotificationHelper {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Get department head email for a given employee
     */
    public function getDepartmentHeadEmail($employeeId) {
        try {
            // Get employee's department
            $stmt = $this->pdo->prepare("SELECT department FROM employees WHERE id = ?");
            $stmt->execute([$employeeId]);
            $employee = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$employee || !$employee['department']) {
                return null;
            }
            
            // Find department head (manager role) for the same department
            $stmt = $this->pdo->prepare("
                SELECT email, name 
                FROM employees 
                WHERE role = 'manager' 
                AND department = ? 
                AND account_status = 'active'
                LIMIT 1
            ");
            $stmt->execute([$employee['department']]);
            $deptHead = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $deptHead ? $deptHead : null;
            
        } catch (Exception $e) {
            error_log("Error getting department head email: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get director email
     */
    public function getDirectorEmail() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT email, name 
                FROM employees 
                WHERE role = 'director' 
                AND account_status = 'active'
                LIMIT 1
            ");
            $stmt->execute();
            $director = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $director ? $director : null;
            
        } catch (Exception $e) {
            error_log("Error getting director email: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Send notification to department head when employee applies for leave
     */
    public function notifyDepartmentHeadNewLeave($leaveRequestId) {
        try {
            // Get leave request details with employee info
            $stmt = $this->pdo->prepare("
                SELECT 
                    lr.*,
                    e.name as employee_name,
                    e.email as employee_email,
                    e.department,
                    e.position
                FROM leave_requests lr 
                JOIN employees e ON lr.employee_id = e.id 
                WHERE lr.id = ?
            ");
            $stmt->execute([$leaveRequestId]);
            $leaveRequest = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$leaveRequest) {
                return false;
            }
            
            // Get department head info
            $deptHead = $this->getDepartmentHeadEmail($leaveRequest['employee_id']);
            if (!$deptHead) {
                error_log("No department head found for employee department: " . $leaveRequest['department']);
                return false;
            }
            
            // Send email notification
            require_once __DIR__ . '/EmailService.php';
            $emailService = new EmailService();
            
            $subject = "New Leave Request - Action Required - ELMS";
            $startDate = date('M d, Y', strtotime($leaveRequest['start_date']));
            $endDate = date('M d, Y', strtotime($leaveRequest['end_date']));
            
            $html = $this->generateDepartmentHeadNotificationHTML($leaveRequest, $deptHead);
            $plain = $this->generateDepartmentHeadNotificationPlain($leaveRequest, $deptHead);
            
            return $emailService->sendCustomEmail(
                $deptHead['email'],
                $deptHead['name'],
                $subject,
                $html,
                $plain
            );
            
        } catch (Exception $e) {
            error_log("Error notifying department head: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send notification to director when department head takes action
     */
    public function notifyDirectorDepartmentAction($leaveRequestId, $action) {
        try {
            // Get leave request details with employee and department head info
            $stmt = $this->pdo->prepare("
                SELECT 
                    lr.*,
                    e.name as employee_name,
                    e.email as employee_email,
                    e.department,
                    e.position,
                    dept_head.name as dept_head_name
                FROM leave_requests lr 
                JOIN employees e ON lr.employee_id = e.id 
                LEFT JOIN employees dept_head ON lr.dept_head_approved_by = dept_head.id
                WHERE lr.id = ?
            ");
            $stmt->execute([$leaveRequestId]);
            $leaveRequest = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$leaveRequest) {
                return false;
            }
            
            // Only notify director if department head approved (not rejected)
            if ($action !== 'approved') {
                return true; // No need to notify director for rejections
            }
            
            // Get director info
            $director = $this->getDirectorEmail();
            if (!$director) {
                error_log("No director found in system");
                return false;
            }
            
            // Send email notification
            require_once __DIR__ . '/EmailService.php';
            $emailService = new EmailService();
            
            $subject = "Leave Request Approved by Department Head - Action Required - ELMS";
            $startDate = date('M d, Y', strtotime($leaveRequest['start_date']));
            $endDate = date('M d, Y', strtotime($leaveRequest['end_date']));
            
            $html = $this->generateDirectorNotificationHTML($leaveRequest, $director);
            $plain = $this->generateDirectorNotificationPlain($leaveRequest, $director);
            
            return $emailService->sendCustomEmail(
                $director['email'],
                $director['name'],
                $subject,
                $html,
                $plain
            );
            
        } catch (Exception $e) {
            error_log("Error notifying director: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate HTML email for department head notification
     */
    private function generateDepartmentHeadNotificationHTML($leaveRequest, $deptHead) {
        $startDate = date('M d, Y', strtotime($leaveRequest['start_date']));
        $endDate = date('M d, Y', strtotime($leaveRequest['end_date']));
        $days = (new DateTime($leaveRequest['start_date']))->diff(new DateTime($leaveRequest['end_date']))->days + 1;
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #2563eb; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f8fafc; }
                .details { background: white; padding: 15px; border-radius: 5px; margin: 15px 0; }
                .action-btn { 
                    display: inline-block; 
                    background: #2563eb; 
                    color: white !important; 
                    padding: 12px 24px; 
                    text-decoration: none; 
                    border-radius: 8px; 
                    margin: 10px 5px;
                    font-weight: bold;
                    font-size: 16px;
                    border: none;
                    cursor: pointer;
                }
                .action-btn:hover {
                    background: #1d4ed8;
                    color: white !important;
                }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>New Leave Request - Action Required</h1>
                    <p>ELMS - Employee Leave Management System</p>
                </div>
                
                <div class='content'>
                    <p>Dear {$deptHead['name']},</p>
                    
                    <p>A new leave request has been submitted and requires your approval:</p>
                    
                    <div class='details'>
                        <h3>Leave Request Details:</h3>
                        <p><strong>Employee:</strong> {$leaveRequest['employee_name']}</p>
                        <p><strong>Position:</strong> {$leaveRequest['position']}</p>
                        <p><strong>Department:</strong> {$leaveRequest['department']}</p>
                        <p><strong>Leave Type:</strong> " . $this->getLeaveTypeDisplayName($leaveRequest['leave_type'], $leaveRequest['original_leave_type'] ?? null) . "</p>
                        <p><strong>Start Date:</strong> {$startDate}</p>
                        <p><strong>End Date:</strong> {$endDate}</p>
                        <p><strong>Duration:</strong> {$days} day(s)</p>
                        <p><strong>Reason:</strong> {$leaveRequest['reason']}</p>
                    </div>
                    
                    <p><strong>Action Required:</strong> Please review and approve or reject this leave request.</p>
                    
                    <div style='text-align: center; margin: 20px 0;'>
                        <a href='" . $this->getBaseUrl() . "/department/dashboard.php' class='action-btn' style='color: white !important; text-decoration: none;'>
                            📋 Review Leave Request
                        </a>
                    </div>
                    
                    <p>Please log in to the ELMS system to take action on this request.</p>
                </div>
                
                <div class='footer'>
                    <p>This is an automated notification from the ELMS system.</p>
                    <p>Please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Generate plain text email for department head notification
     */
    private function generateDepartmentHeadNotificationPlain($leaveRequest, $deptHead) {
        $startDate = date('M d, Y', strtotime($leaveRequest['start_date']));
        $endDate = date('M d, Y', strtotime($leaveRequest['end_date']));
        $days = (new DateTime($leaveRequest['start_date']))->diff(new DateTime($leaveRequest['end_date']))->days + 1;
        
        return "
NEW LEAVE REQUEST - ACTION REQUIRED
ELMS - Employee Leave Management System

Dear {$deptHead['name']},

A new leave request has been submitted and requires your approval:

LEAVE REQUEST DETAILS:
Employee: {$leaveRequest['employee_name']}
Position: {$leaveRequest['position']}
Department: {$leaveRequest['department']}
Leave Type: " . $this->getLeaveTypeDisplayName($leaveRequest['leave_type'], $leaveRequest['original_leave_type'] ?? null) . "
Start Date: {$startDate}
End Date: {$endDate}
Duration: {$days} day(s)
Reason: {$leaveRequest['reason']}

ACTION REQUIRED: Please review and approve or reject this leave request.

To take action, please log in to the ELMS system at: " . $this->getBaseUrl() . "/department/dashboard.php

This is an automated notification from the ELMS system.
Please do not reply to this email.
        ";
    }
    
    /**
     * Generate HTML email for director notification
     */
    private function generateDirectorNotificationHTML($leaveRequest, $director) {
        $startDate = date('M d, Y', strtotime($leaveRequest['start_date']));
        $endDate = date('M d, Y', strtotime($leaveRequest['end_date']));
        $days = (new DateTime($leaveRequest['start_date']))->diff(new DateTime($leaveRequest['end_date']))->days + 1;
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #059669; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f8fafc; }
                .details { background: white; padding: 15px; border-radius: 5px; margin: 15px 0; }
                .action-btn { 
                    display: inline-block; 
                    background: #059669; 
                    color: white; 
                    padding: 10px 20px; 
                    text-decoration: none; 
                    border-radius: 5px; 
                    margin: 10px 5px;
                }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
                .approved { color: #059669; font-weight: bold; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Leave Request Approved by Department Head</h1>
                    <p>ELMS - Employee Leave Management System</p>
                </div>
                
                <div class='content'>
                    <p>Dear {$director['name']},</p>
                    
                    <p>A leave request has been <span class='approved'>approved by the Department Head</span> and now requires your final approval:</p>
                    
                    <div class='details'>
                        <h3>Leave Request Details:</h3>
                        <p><strong>Employee:</strong> {$leaveRequest['employee_name']}</p>
                        <p><strong>Position:</strong> {$leaveRequest['position']}</p>
                        <p><strong>Department:</strong> {$leaveRequest['department']}</p>
                        <p><strong>Department Head:</strong> {$leaveRequest['dept_head_name']}</p>
                        <p><strong>Leave Type:</strong> " . $this->getLeaveTypeDisplayName($leaveRequest['leave_type'], $leaveRequest['original_leave_type'] ?? null) . "</p>
                        <p><strong>Start Date:</strong> {$startDate}</p>
                        <p><strong>End Date:</strong> {$endDate}</p>
                        <p><strong>Duration:</strong> {$days} day(s)</p>
                        <p><strong>Reason:</strong> {$leaveRequest['reason']}</p>
                    </div>
                    
                    <p><strong>Action Required:</strong> Please review and provide final approval for this leave request.</p>
                    
                    <div style='text-align: center; margin: 20px 0;'>
                        <a href='" . $this->getBaseUrl() . "/director/dashboard.php' class='action-btn' style='color: white !important; text-decoration: none;'>
                            📋 Review Leave Request
                        </a>
                    </div>
                    
                    <p>Please log in to the ELMS system to take final action on this request.</p>
                </div>
                
                <div class='footer'>
                    <p>This is an automated notification from the ELMS system.</p>
                    <p>Please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Generate plain text email for director notification
     */
    private function generateDirectorNotificationPlain($leaveRequest, $director) {
        $startDate = date('M d, Y', strtotime($leaveRequest['start_date']));
        $endDate = date('M d, Y', strtotime($leaveRequest['end_date']));
        $days = (new DateTime($leaveRequest['start_date']))->diff(new DateTime($leaveRequest['end_date']))->days + 1;
        
        return "
LEAVE REQUEST APPROVED BY DEPARTMENT HEAD - ACTION REQUIRED
ELMS - Employee Leave Management System

Dear {$director['name']},

A leave request has been APPROVED by the Department Head and now requires your final approval:

LEAVE REQUEST DETAILS:
Employee: {$leaveRequest['employee_name']}
Position: {$leaveRequest['position']}
Department: {$leaveRequest['department']}
Department Head: {$leaveRequest['dept_head_name']}
Leave Type: " . $this->getLeaveTypeDisplayName($leaveRequest['leave_type'], $leaveRequest['original_leave_type'] ?? null) . "
Start Date: {$startDate}
End Date: {$endDate}
Duration: {$days} day(s)
Reason: {$leaveRequest['reason']}

ACTION REQUIRED: Please review and provide final approval for this leave request.

To take action, please log in to the ELMS system at: " . $this->getBaseUrl() . "/director/dashboard.php

This is an automated notification from the ELMS system.
Please do not reply to this email.
        ";
    }
    
    /**
     * Get leave type display name using the proper formatting
     */
    private function getLeaveTypeDisplayName($leave_type, $original_leave_type = null) {
        require_once __DIR__ . '/../../../config/leave_types.php';
        return getLeaveTypeDisplayName($leave_type, $original_leave_type);
    }
    
    /**
     * Get base URL for the application
     */
    private function getBaseUrl() {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $path = dirname(dirname(dirname(dirname($script))));
        return $scheme . '://' . $host . $path;
    }
}
?>
