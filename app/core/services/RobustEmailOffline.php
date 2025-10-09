<?php
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/OfflineEmailManager.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class RobustEmailOffline {
    private $pdo;
    private $offlineManager;
    private $smtp_host = 'smtp.gmail.com';
    private $smtp_port = 587;
    private $smtp_username;
    private $smtp_password;
    private $from_email;
    private $from_name = 'ELMS System';
    private $emailDir;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->emailDir = __DIR__ . '/../emails';
        $this->offlineManager = new OfflineEmailManager($pdo);
        
        // Create email directory if it doesn't exist
        if (!file_exists($this->emailDir)) {
            mkdir($this->emailDir, 0755, true);
        }
        
        $this->loadConfig();
    }
    
    private function loadConfig() {
        // Try to load from config file first
        $configPath = __DIR__ . '/../../config/email_config.php';
        if (file_exists($configPath)) {
            $config = require $configPath;
            $this->smtp_username = $config['smtp_username'] ?? 'your-email@gmail.com';
            $this->smtp_password = $config['smtp_password'] ?? 'your-app-password';
            $this->from_email = $config['from_email'] ?? 'your-email@gmail.com';
            $this->from_name = $config['from_name'] ?? 'ELMS System';
        } else {
            // Default configuration - user needs to update
            $this->smtp_username = 'your-email@gmail.com';
            $this->smtp_password = 'your-app-password';
            $this->from_email = 'your-email@gmail.com';
        }
    }
    
    /**
     * Send email using offline manager
     */
    public function sendEmail($to, $subject, $body, $isHTML = true, $priority = 'normal', $metadata = null) {
        try {
            // Test SMTP connection first
            $smtpAvailable = $this->offlineManager->testSMTPConnection();
            
            // Use offline manager to send or queue email
            $result = $this->offlineManager->sendEmail($to, $subject, $body, $isHTML, null, $priority, $metadata);
            
            if ($result) {
                $status = $smtpAvailable ? 'sent' : 'queued';
                error_log("Email $status successfully: To: $to, Subject: $subject");
                return true;
            } else {
                error_log("Failed to send/queue email: To: $to, Subject: $subject");
                return false;
            }
            
        } catch (Exception $e) {
            error_log("Email sending error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send email verification to new user
     */
    public function sendVerificationEmail($userEmail, $userName, $verificationToken, $priority = 'high') {
        try {
            // Build verification link
            $baseUrl = $this->getBaseUrl();
            $verificationLink = $baseUrl . '/auth/views/verify_email.php?token=' . $verificationToken;
            
            $subject = 'Verify Your Email - ELMS System';
            $body = $this->getVerificationEmailTemplate($userName, $verificationLink);
            
            $metadata = [
                'type' => 'verification',
                'user_name' => $userName,
                'token' => $verificationToken
            ];
            
            return $this->sendEmail($userEmail, $subject, $body, true, $priority, $metadata);
            
        } catch (Exception $e) {
            error_log("Verification email error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send welcome email after verification
     */
    public function sendWelcomeEmail($userEmail, $userName, $temporaryPassword, $priority = 'normal') {
        try {
            $subject = 'Welcome to ELMS System - Account Verified';
            $body = $this->getWelcomeEmailTemplate($userName, $temporaryPassword);
            
            $metadata = [
                'type' => 'welcome',
                'user_name' => $userName
            ];
            
            return $this->sendEmail($userEmail, $subject, $body, true, $priority, $metadata);
            
        } catch (Exception $e) {
            error_log("Welcome email error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send leave status notification
     */
    public function sendLeaveStatusNotification($leaveRequestId, $action, $approverName, $approverRole, $message = '', $priority = 'normal') {
        try {
            // Get leave request details
            $stmt = $this->pdo->prepare("
                SELECT 
                    lr.*,
                    e.name as employee_name,
                    e.email as employee_email,
                    e.position,
                    e.department
                FROM leave_requests lr 
                JOIN employees e ON lr.employee_id = e.id 
                WHERE lr.id = ?
            ");
            $stmt->execute([$leaveRequestId]);
            $leaveRequest = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$leaveRequest) {
                return false;
            }
            
            $subject = $this->getEmailSubject($action, $approverRole);
            $body = $this->generateEmailBody($leaveRequest, $action, $approverName, $approverRole, $message);
            
            $metadata = [
                'type' => 'leave_status',
                'leave_request_id' => $leaveRequestId,
                'action' => $action,
                'approver_name' => $approverName,
                'approver_role' => $approverRole
            ];
            
            return $this->sendEmail($leaveRequest['employee_email'], $subject, $body, true, $priority, $metadata);
            
        } catch (Exception $e) {
            error_log("Leave status notification error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send approver notification
     */
    public function sendApproverNotification($leaveRequestId, $nextApproverRole, $priority = 'high') {
        try {
            // Get leave request details
            $stmt = $this->pdo->prepare("
                SELECT 
                    lr.*,
                    e.name as employee_name,
                    e.position,
                    e.department
                FROM leave_requests lr 
                JOIN employees e ON lr.employee_id = e.id 
                WHERE lr.id = ?
            ");
            $stmt->execute([$leaveRequestId]);
            $leaveRequest = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$leaveRequest) {
                return false;
            }
            
            // For testing, send to a default email
            $approverEmail = 'approver@elms.local';
            
            $subject = "New Leave Request Pending Approval - ELMS";
            $body = $this->generateApproverEmailBody($leaveRequest, $nextApproverRole);
            
            $metadata = [
                'type' => 'approver_notification',
                'leave_request_id' => $leaveRequestId,
                'approver_role' => $nextApproverRole
            ];
            
            return $this->sendEmail($approverEmail, $subject, $body, true, $priority, $metadata);
            
        } catch (Exception $e) {
            error_log("Approver notification error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Process email queue
     */
    public function processQueue($batchSize = null) {
        return $this->offlineManager->processQueue($batchSize);
    }
    
    /**
     * Get queue statistics
     */
    public function getQueueStats() {
        return $this->offlineManager->getQueueStats();
    }
    
    /**
     * Test SMTP connection
     */
    public function testSMTPConnection() {
        return $this->offlineManager->testSMTPConnection();
    }
    
    /**
     * Get base URL for the application
     */
    private function getBaseUrl() {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
        $projectRoot = preg_replace('#/(admin|user|department|director)$#', '', $scriptDir);
        return $scheme . '://' . $host . $projectRoot;
    }
    
    /**
     * Get email subject
     */
    private function getEmailSubject($action, $approverRole) {
        $role = ucfirst(str_replace('_', ' ', $approverRole));
        
        switch ($action) {
            case 'approve':
                return "Leave Request Approved by {$role} - ELMS";
            case 'reject':
                return "Leave Request Rejected by {$role} - ELMS";
            case 'approve_with_pay':
                return "Leave Request Approved with Pay by {$role} - ELMS";
            case 'approve_without_pay':
                return "Leave Request Approved without Pay by {$role} - ELMS";
            case 'others':
                return "Leave Request Processed by {$role} - ELMS";
            default:
                return "Leave Request Status Update - ELMS";
        }
    }
    
    /**
     * Generate email body
     */
    private function generateEmailBody($leaveRequest, $action, $approverName, $approverRole, $message) {
        $employeeName = $leaveRequest['employee_name'];
        $leaveType = ucfirst(str_replace('_', ' ', $leaveRequest['leave_type']));
        $startDate = date('F j, Y', strtotime($leaveRequest['start_date']));
        $endDate = date('F j, Y', strtotime($leaveRequest['end_date']));
        
        // Calculate days if not set or is 0
        $days = $leaveRequest['days_requested'];
        if (!$days || $days == 0) {
            $start = new DateTime($leaveRequest['start_date']);
            $end = new DateTime($leaveRequest['end_date']);
            $days = $start->diff($end)->days + 1;
        }
        
        $reason = $leaveRequest['reason'];
        $approverRoleFormatted = ucfirst(str_replace('_', ' ', $approverRole));
        
        $statusMessage = $this->getStatusMessage($action, $approverRoleFormatted);
        $statusColor = $this->getStatusColor($action);
        
        return "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <div style='background: #1e40af; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0;'>
                <h1>Employee Leave Management System</h1>
                <p>Leave Request Status Update</p>
            </div>
            
            <div style='background: #f8fafc; padding: 30px; border: 1px solid #e2e8f0;'>
                <h2>Dear {$employeeName},</h2>
                
                <div style='background: {$statusColor}; color: white; padding: 15px; border-radius: 6px; margin: 20px 0; text-align: center; font-weight: bold;'>
                    {$statusMessage}
                </div>
                
                <p>Your leave request has been processed by <strong>{$approverName}</strong> ({$approverRoleFormatted}).</p>
                
                {$this->getNextApprovalMessage($action, $approverRole)}
                
                <h3>Leave Request Details:</h3>
                <table style='width: 100%; border-collapse: collapse; margin: 20px 0;'>
                    <tr><td style='padding: 10px; border-bottom: 1px solid #e2e8f0; font-weight: bold; width: 30%;'>Leave Type:</td><td style='padding: 10px; border-bottom: 1px solid #e2e8f0;'>{$leaveType}</td></tr>
                    <tr><td style='padding: 10px; border-bottom: 1px solid #e2e8f0; font-weight: bold;'>Start Date:</td><td style='padding: 10px; border-bottom: 1px solid #e2e8f0;'>{$startDate}</td></tr>
                    <tr><td style='padding: 10px; border-bottom: 1px solid #e2e8f0; font-weight: bold;'>End Date:</td><td style='padding: 10px; border-bottom: 1px solid #e2e8f0;'>{$endDate}</td></tr>
                    <tr><td style='padding: 10px; border-bottom: 1px solid #e2e8f0; font-weight: bold;'>Total Days:</td><td style='padding: 10px; border-bottom: 1px solid #e2e8f0;'>{$days} day(s)</td></tr>
                    <tr><td style='padding: 10px; border-bottom: 1px solid #e2e8f0; font-weight: bold;'>Reason:</td><td style='padding: 10px; border-bottom: 1px solid #e2e8f0;'>{$reason}</td></tr>
                    <tr><td style='padding: 10px; border-bottom: 1px solid #e2e8f0; font-weight: bold;'>Processed By:</td><td style='padding: 10px; border-bottom: 1px solid #e2e8f0;'>{$approverName} ({$approverRoleFormatted})</td></tr>
                    <tr><td style='padding: 10px; border-bottom: 1px solid #e2e8f0; font-weight: bold;'>Processed On:</td><td style='padding: 10px; border-bottom: 1px solid #e2e8f0;'>" . date('F j, Y \a\t g:i A') . "</td></tr>
                </table>
                
                <p>You can view your leave history and current status by logging into the ELMS system.</p>
                <p>If you have any questions or concerns, please contact your supervisor or the HR department.</p>
                
                <p>Best regards,<br><strong>ELMS Team</strong></p>
            </div>
            
            <div style='background: #64748b; color: white; padding: 15px; text-align: center; border-radius: 0 0 8px 8px; font-size: 14px;'>
                <p>This is an automated message from the Employee Leave Management System.</p>
                <p>Please do not reply to this email.</p>
            </div>
        </div>
        ";
    }
    
    /**
     * Generate approver email body
     */
    private function generateApproverEmailBody($leaveRequest, $approverRole) {
        $employeeName = $leaveRequest['employee_name'];
        $leaveType = ucfirst(str_replace('_', ' ', $leaveRequest['leave_type']));
        $startDate = date('F j, Y', strtotime($leaveRequest['start_date']));
        $endDate = date('F j, Y', strtotime($leaveRequest['end_date']));
        
        // Calculate days if not set or is 0
        $days = $leaveRequest['days_requested'];
        if (!$days || $days == 0) {
            $start = new DateTime($leaveRequest['start_date']);
            $end = new DateTime($leaveRequest['end_date']);
            $days = $start->diff($end)->days + 1;
        }
        
        $reason = $leaveRequest['reason'];
        $approverRoleFormatted = ucfirst(str_replace('_', ' ', $approverRole));
        
        return "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <div style='background: #1e40af; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0;'>
                <h1>Employee Leave Management System</h1>
                <p>Leave Request Pending Approval</p>
            </div>
            
            <div style='background: #f8fafc; padding: 30px; border: 1px solid #e2e8f0;'>
                <div style='background: #fef3c7; border: 1px solid #f59e0b; padding: 15px; border-radius: 6px; margin: 20px 0;'>
                    <h3>⚠️ Action Required</h3>
                    <p>You have a new leave request pending your approval as <strong>{$approverRoleFormatted}</strong>.</p>
                </div>
                
                <h3>Leave Request Details:</h3>
                <table style='width: 100%; border-collapse: collapse; margin: 20px 0;'>
                    <tr><td style='padding: 10px; border-bottom: 1px solid #e2e8f0; font-weight: bold; width: 30%;'>Employee:</td><td style='padding: 10px; border-bottom: 1px solid #e2e8f0;'>{$employeeName}</td></tr>
                    <tr><td style='padding: 10px; border-bottom: 1px solid #e2e8f0; font-weight: bold;'>Position:</td><td style='padding: 10px; border-bottom: 1px solid #e2e8f0;'>{$leaveRequest['position']}</td></tr>
                    <tr><td style='padding: 10px; border-bottom: 1px solid #e2e8f0; font-weight: bold;'>Department:</td><td style='padding: 10px; border-bottom: 1px solid #e2e8f0;'>{$leaveRequest['department']}</td></tr>
                    <tr><td style='padding: 10px; border-bottom: 1px solid #e2e8f0; font-weight: bold;'>Leave Type:</td><td style='padding: 10px; border-bottom: 1px solid #e2e8f0;'>{$leaveType}</td></tr>
                    <tr><td style='padding: 10px; border-bottom: 1px solid #e2e8f0; font-weight: bold;'>Start Date:</td><td style='padding: 10px; border-bottom: 1px solid #e2e8f0;'>{$startDate}</td></tr>
                    <tr><td style='padding: 10px; border-bottom: 1px solid #e2e8f0; font-weight: bold;'>End Date:</td><td style='padding: 10px; border-bottom: 1px solid #e2e8f0;'>{$endDate}</td></tr>
                    <tr><td style='padding: 10px; border-bottom: 1px solid #e2e8f0; font-weight: bold;'>Total Days:</td><td style='padding: 10px; border-bottom: 1px solid #e2e8f0;'>{$days} day(s)</td></tr>
                    <tr><td style='padding: 10px; border-bottom: 1px solid #e2e8f0; font-weight: bold;'>Reason:</td><td style='padding: 10px; border-bottom: 1px solid #e2e8f0;'>{$reason}</td></tr>
                    <tr><td style='padding: 10px; border-bottom: 1px solid #e2e8f0; font-weight: bold;'>Submitted On:</td><td style='padding: 10px; border-bottom: 1px solid #e2e8f0;'>" . date('F j, Y \a\t g:i A', strtotime($leaveRequest['created_at'])) . "</td></tr>
                </table>
                
                <p>Please log into the ELMS system to review and process this leave request.</p>
                <p>Best regards,<br><strong>ELMS Team</strong></p>
            </div>
            
            <div style='background: #64748b; color: white; padding: 15px; text-align: center; border-radius: 0 0 8px 8px; font-size: 14px;'>
                <p>This is an automated message from the Employee Leave Management System.</p>
                <p>Please do not reply to this email.</p>
            </div>
        </div>
        ";
    }
    
    /**
     * Get HTML verification email template
     */
    private function getVerificationEmailTemplate($userName, $verificationLink) {
        return "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
                <h1>🔐 Email Verification Required</h1>
                <p>ELMS System - Employee Leave Management</p>
            </div>
            
            <div style='background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px;'>
                <h2>Hello {$userName}!</h2>
                <p>Welcome to the ELMS System! Your account has been created by an administrator.</p>
                <p>To complete your account setup and start using the system, please verify your email address by clicking the button below:</p>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$verificationLink}' style='display: inline-block; padding: 15px 30px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;'>✅ Verify Email Address</a>
                </div>
                
                <p><strong>Important:</strong> This verification link will expire in 24 hours for security reasons.</p>
                
                <p>If the button above doesn't work, you can copy and paste this link into your browser:</p>
                <p style='word-break: break-all; background: #f0f0f0; padding: 10px; border-radius: 5px; font-family: monospace;'>{$verificationLink}</p>
                
                <p>If you didn't expect this email, please contact your system administrator.</p>
            </div>
            
            <div style='text-align: center; margin-top: 30px; color: #666; font-size: 14px;'>
                <p>This is an automated message from the ELMS System. Please do not reply to this email.</p>
                <p>&copy; " . date('Y') . " ELMS System. All rights reserved.</p>
            </div>
        </div>
        ";
    }
    
    /**
     * Get HTML welcome email template
     */
    private function getWelcomeEmailTemplate($userName, $temporaryPassword) {
        return "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <div style='background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
                <h1>🎉 Welcome to ELMS System!</h1>
                <p>Your Account is Now Active</p>
            </div>
            
            <div style='background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px;'>
                <h2>Hello {$userName}!</h2>
                <p>Congratulations! Your email address has been verified and your account is now active.</p>
                <p>You can now start using the ELMS System with your chosen password.</p>
                
                <div style='background: #e3f2fd; border: 1px solid #2196f3; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                    <h3>🔐 Account Security</h3>
                    <p>Your account is secured with the password you created during verification.</p>
                    <p><em>Keep your password secure and don't share it with anyone.</em></p>
                </div>
                
                <p><strong>Next Steps:</strong></p>
                <ol style='text-align: left;'>
                    <li>Visit the ELMS System login page</li>
                    <li>Use your email address and the password you created</li>
                    <li>Complete your profile information</li>
                    <li>Start using the leave management system</li>
                </ol>
                
                <p>If you have any questions or need assistance, please contact your system administrator.</p>
            </div>
            
            <div style='text-align: center; margin-top: 30px; color: #666; font-size: 14px;'>
                <p>This is an automated message from the ELMS System. Please do not reply to this email.</p>
                <p>&copy; " . date('Y') . " ELMS System. All rights reserved.</p>
            </div>
        </div>
        ";
    }
    
    /**
     * Get status message
     */
    private function getStatusMessage($action, $approverRole) {
        switch ($action) {
            case 'approve':
                return "✅ APPROVED by {$approverRole}";
            case 'reject':
                return "❌ REJECTED by {$approverRole}";
            case 'approve_with_pay':
                return "✅ APPROVED WITH PAY by {$approverRole}";
            case 'approve_without_pay':
                return "✅ APPROVED WITHOUT PAY by {$approverRole}";
            case 'others':
                return "✅ PROCESSED by {$approverRole}";
            default:
                return "📋 PROCESSED by {$approverRole}";
        }
    }
    
    /**
     * Get status color
     */
    private function getStatusColor($action) {
        switch ($action) {
            case 'approve':
            case 'approve_with_pay':
            case 'approve_without_pay':
            case 'others':
                return '#10b981'; // Green
            case 'reject':
                return '#ef4444'; // Red
            default:
                return '#6b7280'; // Gray
        }
    }
    
    /**
     * Get next approval step message
     */
    private function getNextApprovalMessage($action, $approverRole) {
        // Only show next step message for department head approvals
        if ($approverRole === 'department_head' && $action === 'approve') {
            return "
                <div style='background: #e0f2fe; border: 1px solid #0ea5e9; padding: 15px; border-radius: 6px; margin: 20px 0;'>
                    <h4 style='color: #0369a1; margin: 0 0 10px 0; font-size: 16px;'>
                        <i class='fas fa-arrow-right' style='margin-right: 8px;'></i>Next Step: Director Head Approval
                    </h4>
                    <p style='color: #0c4a6e; margin: 0; font-size: 14px;'>
                        Your leave request has been approved by the Department Head and is now pending approval from the Director Head. 
                        You will receive another notification once the Director Head processes your request.
                    </p>
                </div>
            ";
        }
        
        // For director approvals, show final status
        if ($approverRole === 'director' && in_array($action, ['approve', 'approve_with_pay', 'approve_without_pay'])) {
            return "
                <div style='background: #f0fdf4; border: 1px solid #10b981; padding: 15px; border-radius: 6px; margin: 20px 0;'>
                    <h4 style='color: #166534; margin: 0 0 10px 0; font-size: 16px;'>
                        <i class='fas fa-check-circle' style='margin-right: 8px;'></i>Final Approval Complete
                    </h4>
                    <p style='color: #14532d; margin: 0; font-size: 14px;'>
                        Your leave request has been fully approved and is now ready for use. 
                        Please ensure you follow your organization's leave policies and procedures.
                    </p>
                </div>
            ";
        }
        
        // For rejections, show appropriate message
        if ($action === 'reject') {
            return "
                <div style='background: #fef2f2; border: 1px solid #ef4444; padding: 15px; border-radius: 6px; margin: 20px 0;'>
                    <h4 style='color: #dc2626; margin: 0 0 10px 0; font-size: 16px;'>
                        <i class='fas fa-times-circle' style='margin-right: 8px;'></i>Leave Request Rejected
                    </h4>
                    <p style='color: #991b1b; margin: 0; font-size: 14px;'>
                        Your leave request has been rejected. If you have any questions about this decision, 
                        please contact your supervisor or the HR department for clarification.
                    </p>
                </div>
            ";
        }
        
        return '';
    }
}
?>
