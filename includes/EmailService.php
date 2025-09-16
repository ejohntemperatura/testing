<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

class EmailService {
    private $mailer;
    private $config;
    
    public function __construct() {
        $this->config = require __DIR__ . '/../config/email_config.php';
        $this->initializeMailer();
    }
    
    private function initializeMailer() {
        $this->mailer = new PHPMailer(true);
        
        try {
            // Server settings
            $this->mailer->isSMTP();
            $this->mailer->Host = $this->config['smtp_host'];
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $this->config['smtp_username'];
            $this->mailer->Password = $this->config['smtp_password'];
            $this->mailer->SMTPSecure = $this->config['smtp_encryption'];
            $this->mailer->Port = $this->config['smtp_port'];
            
            // Default settings
            $this->mailer->setFrom($this->config['from_email'], $this->config['from_name']);
            $this->mailer->addReplyTo($this->config['reply_to'], $this->config['from_name']);
            $this->mailer->isHTML(true);
            
        } catch (Exception $e) {
            error_log("Email initialization failed: " . $e->getMessage());
        }
    }
    
    /**
     * Send email verification to new user
     */
    public function sendVerificationEmail($userEmail, $userName, $verificationToken) {
        try {
            // Ensure clean recipient list per send
            $this->mailer->clearAllRecipients();

            $this->mailer->addAddress($userEmail, $userName);
            // Temporarily BCC sender for deliverability debugging
            if (!empty($this->config['smtp_username'])) {
                $this->mailer->addBCC($this->config['smtp_username']);
            }
            $this->mailer->Subject = 'Verify Your Email - ELMS System';
            
            // Build verification link using fixed BASE URL
            $appConfig = require __DIR__ . '/../config/app_config.php';
            $baseUrl = rtrim($appConfig['base_url'] ?? '', '/');
            if ($baseUrl === '') {
                // Fallback to dynamic detection if not configured
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
                $projectRoot = preg_replace('#/(admin|user)$#', '', $scriptDir);
                $baseUrl = $scheme . '://' . $host . $projectRoot;
            }
            $verificationLink = $baseUrl . '/auth/verify_email.php?token=' . $verificationToken;
            
            $emailBody = $this->getVerificationEmailTemplate($userName, $verificationLink);
            $this->mailer->Body = $emailBody;
            $this->mailer->AltBody = $this->getPlainTextVerificationEmail($userName, $verificationLink);
            
            $this->mailer->send();
            // Cleanup recipients after send
            $this->mailer->clearAllRecipients();
            return true;
            
        } catch (Exception $e) {
            error_log("Verification email failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send welcome email after verification
     */
    public function sendWelcomeEmail($userEmail, $userName, $temporaryPassword) {
        try {
            // Ensure clean recipient list per send
            $this->mailer->clearAllRecipients();

            $this->mailer->addAddress($userEmail, $userName);
            // No BCC in dynamic mode
            $this->mailer->Subject = 'Welcome to ELMS System - Account Verified';
            
            $emailBody = $this->getWelcomeEmailTemplate($userName, $temporaryPassword);
            $this->mailer->Body = $emailBody;
            $this->mailer->AltBody = $this->getPlainTextWelcomeEmail($userName, $temporaryPassword);
            
            $this->mailer->send();
            // Cleanup recipients after send
            $this->mailer->clearAllRecipients();
            return true;
            
        } catch (Exception $e) {
            error_log("Welcome email failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send leave status notification (approved/rejected)
     */
    public function sendLeaveStatusNotification(
        string $userEmail,
        string $userName,
        string $status,
        string $startDate,
        string $endDate,
        ?string $leaveType = null
    ): bool {
        try {
            $this->mailer->clearAllRecipients();
            $this->mailer->addAddress($userEmail, $userName);
            // No BCC in dynamic mode
            $this->mailer->Subject = 'Leave Request ' . ucfirst($status) . ' - ELMS';

            $statusColor = $status === 'approved' ? '#10b981' : ($status === 'rejected' ? '#ef4444' : '#f59e0b');
            $typeText = $leaveType ? "<p><strong>Type:</strong> {$leaveType}</p>" : '';

            $html = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <title>Leave Request Update</title>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: {$statusColor}; color: white; padding: 24px; text-align: center; border-radius: 10px 10px 0 0; }
                    .content { background: #f9f9f9; padding: 24px; border-radius: 0 0 10px 10px; }
                    .details { background: #fff; padding: 16px; border-radius: 8px; border: 1px solid #eee; margin: 16px 0; }
                    .footer { text-align: center; color: #666; font-size: 12px; margin-top: 24px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>Leave Request " . ucfirst($status) . "</h2>
                    </div>
                    <div class='content'>
                        <p>Dear {$userName},</p>
                        <p>Your leave request has been <strong style='color: {$statusColor}'>" . ucfirst($status) . "</strong>.</p>
                        <div class='details'>
                            <h3 style='margin-top:0'>Leave Details</h3>
                            {$typeText}
                            <p><strong>Start Date:</strong> {$startDate}</p>
                            <p><strong>End Date:</strong> {$endDate}</p>
                        </div>
                        <p>If you have any questions, please contact your supervisor or HR.</p>
                        <div class='footer'>
                            <p>This is an automated message from ELMS. Please do not reply.</p>
                        </div>
                    </div>
                </div>
            </body>
            </html>";

            $plain = "Leave Request " . ucfirst($status) . "\n\n"
                . "Hello {$userName},\n\n"
                . "Your leave request has been " . ucfirst($status) . ".\n"
                . ($leaveType ? "Type: {$leaveType}\n" : '')
                . "Start Date: {$startDate}\nEnd Date: {$endDate}\n\n"
                . "This is an automated message from ELMS.";

            $this->mailer->Body = $html;
            $this->mailer->AltBody = $plain;

            $this->mailer->send();
            $this->mailer->clearAllRecipients();
            // Log success
            $this->logDeliver("leave_status", $userEmail, $this->mailer->Subject, true);
            return true;
        } catch (Exception $e) {
            error_log('Leave status email failed: ' . $e->getMessage());
            $this->logDeliver("leave_status", $userEmail, 'Leave Request ' . ucfirst($status) . ' - ELMS', false, $e->getMessage());
            return false;
        }
    }

    private function logDeliver(string $type, string $to, string $subject, bool $ok, ?string $err = null): void {
        try {
            $dir = __DIR__ . '/../logs';
            if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
            $line = sprintf("[%s] [%s] [%s] to=%s subject=\"%s\"%s\n",
                date('Y-m-d H:i:s'), $type, $ok ? 'OK' : 'FAIL', $to, $subject, $err ? ' error=' . str_replace("\n", ' ', $err) : ''
            );
            @file_put_contents($dir . '/email.log', $line, FILE_APPEND);
        } catch (\Throwable $t) {
            // ignore
        }
    }
    
    /**
     * Get HTML verification email template
     */
    private function getVerificationEmailTemplate($userName, $verificationLink) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Verify Your Email</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                .btn { display: inline-block; padding: 15px 30px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { text-align: center; margin-top: 30px; color: #666; font-size: 14px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🔐 Email Verification Required</h1>
                    <p>ELMS System - Employee Leave Management</p>
                </div>
                <div class='content'>
                    <h2>Hello {$userName}!</h2>
                    <p>Welcome to the ELMS System! Your account has been created by an administrator.</p>
                    <p>To complete your account setup and start using the system, please verify your email address by clicking the button below:</p>
                    
                    <div style='text-align: center;'>
                        <a href='{$verificationLink}' class='btn'>✅ Verify Email Address</a>
                    </div>
                    
                    <p><strong>Important:</strong> This verification link will expire in 24 hours for security reasons.</p>
                    
                    <p>If the button above doesn't work, you can copy and paste this link into your browser:</p>
                    <p style='word-break: break-all; background: #f0f0f0; padding: 10px; border-radius: 5px;'>{$verificationLink}</p>
                    
                    <p>If you didn't expect this email, please contact your system administrator.</p>
                </div>
                <div class='footer'>
                    <p>This is an automated message from the ELMS System. Please do not reply to this email.</p>
                    <p>&copy; " . date('Y') . " ELMS System. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Get plain text verification email
     */
    private function getPlainTextVerificationEmail($userName, $verificationLink) {
        return "Hello {$userName}!

Welcome to the ELMS System! Your account has been created by an administrator.

To complete your account setup and start using the system, please verify your email address by visiting this link:

{$verificationLink}

Important: This verification link will expire in 24 hours for security reasons.

If you didn't expect this email, please contact your system administrator.

This is an automated message from the ELMS System. Please do not reply to this email.";
    }
    
    /**
     * Get HTML welcome email template
     */
    private function getWelcomeEmailTemplate($userName, $temporaryPassword) {
        $passwordSection = '';
        if (!empty($temporaryPassword)) {
            $passwordSection = "
                    <div class='password-box'>
                        <h3>🔑 Your Password</h3>
                        <p><strong>Password:</strong> {$temporaryPassword}</p>
                        <p><em>Please keep this password secure.</em></p>
                    </div>";
        }

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Welcome to ELMS System</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                .password-box { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0; }
                .footer { text-align: center; margin-top: 30px; color: #666; font-size: 14px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🎉 Welcome to ELMS System!</h1>
                    <p>Your Email Has Been Verified Successfully</p>
                </div>
                <div class='content'>
                    <h2>Hello {$userName}!</h2>
                    <p>Congratulations! Your email address has been verified successfully.</p>
                    <p>Your account is now active and you can start using the ELMS System.</p>
                    {$passwordSection}
                    
                    <p><strong>Next Steps:</strong></p>
                    <ol>
                        <li>Visit the ELMS System login page</li>
                        <li>Use your email address and your password</li>
                        <li>You can update your password anytime from your profile</li>
                        <li>Complete your profile information</li>
                    </ol>
                    
                    <p>If you have any questions or need assistance, please contact your system administrator.</p>
                </div>
                <div class='footer'>
                    <p>This is an automated message from the ELMS System. Please do not reply to this email.</p>
                    <p>&copy; " . date('Y') . " ELMS System. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Get plain text welcome email
     */
    private function getPlainTextWelcomeEmail($userName, $temporaryPassword) {
        $passwordText = '';
        if (!empty($temporaryPassword)) {
            $passwordText = "\nYour Password: {$temporaryPassword}\nPlease keep this password secure.";
        }

        return "Hello {$userName}!

Congratulations! Your email address has been verified successfully.

Your account is now active and you can start using the ELMS System.
{$passwordText}

Next Steps:
1. Visit the ELMS System login page
2. Use your email address and your password
3. You can update your password anytime from your profile
4. Complete your profile information

If you have any questions or need assistance, please contact your system administrator.

This is an automated message from the ELMS System. Please do not reply to this email.";
    }
    
    /**
     * Test email configuration
     */
    public function testConnection() {
        try {
            // Ensure clean recipient list per send
            $this->mailer->clearAllRecipients();
            $this->mailer->addAddress($this->config['smtp_username']);
            $this->mailer->Subject = 'Test Email - ELMS System';
            $this->mailer->Body = '<h1>Test Email</h1><p>If you receive this email, your email configuration is working correctly.</p>';
            $this->mailer->AltBody = 'Test Email - If you receive this email, your email configuration is working correctly.';
            
            $this->mailer->send();
            // Cleanup recipients after send
            $this->mailer->clearAllRecipients();
            return true;
            
        } catch (Exception $e) {
            error_log("Test email failed: " . $e->getMessage());
            return false;
        }
    }
}
?>
