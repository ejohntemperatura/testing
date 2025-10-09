<?php
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/database.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class OfflineEmailManager {
    private $pdo;
    private $emailDir;
    private $settings;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->emailDir = __DIR__ . '/../emails';
        
        // Create email directory if it doesn't exist
        if (!file_exists($this->emailDir)) {
            mkdir($this->emailDir, 0755, true);
        }
        
        $this->loadSettings();
    }
    
    /**
     * Load settings from database
     */
    private function loadSettings() {
        try {
            $stmt = $this->pdo->query("SELECT setting_key, setting_value FROM offline_email_settings");
            $this->settings = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $this->settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            error_log("Error loading offline email settings: " . $e->getMessage());
            $this->settings = [
                'offline_mode' => '1',
                'queue_processing' => '1',
                'max_attempts' => '3',
                'retry_delay_minutes' => '30',
                'batch_size' => '10',
                'cleanup_days' => '30',
                'smtp_available' => '0'
            ];
        }
    }
    
    /**
     * Check if offline mode is enabled
     */
    public function isOfflineMode() {
        return $this->settings['offline_mode'] === '1';
    }
    
    /**
     * Check if SMTP is available
     */
    public function isSMTPAvailable() {
        return $this->settings['smtp_available'] === '1';
    }
    
    /**
     * Update SMTP availability status
     */
    public function updateSMTPStatus($available) {
        try {
            $stmt = $this->pdo->prepare("UPDATE offline_email_settings SET setting_value = ? WHERE setting_key = 'smtp_available'");
            $stmt->execute([$available ? '1' : '0']);
            $this->settings['smtp_available'] = $available ? '1' : '0';
        } catch (Exception $e) {
            error_log("Error updating SMTP status: " . $e->getMessage());
        }
    }
    
    /**
     * Queue an email for sending
     */
    public function queueEmail($to, $subject, $body, $isHTML = true, $toName = null, $priority = 'normal', $metadata = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO email_queue (to_email, to_name, subject, body, is_html, priority, metadata, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
            ");
            
            $metadataJson = $metadata ? json_encode($metadata) : null;
            $result = $stmt->execute([$to, $toName, $subject, $body, $isHTML, $priority, $metadataJson]);
            
            if ($result) {
                $emailId = $this->pdo->lastInsertId();
                
                // Log the queued email
                $this->logEmail($emailId, $to, $subject, 'queued');
                
                // Save to file as backup
                $this->saveEmailToFile($to, $subject, $body, false, $emailId);
                
                error_log("Email queued successfully: ID $emailId, To: $to, Subject: $subject");
                return $emailId;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("Error queuing email: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send email immediately or queue if offline
     */
    public function sendEmail($to, $subject, $body, $isHTML = true, $toName = null, $priority = 'normal', $metadata = null) {
        // If offline mode is enabled, always queue
        if ($this->isOfflineMode()) {
            return $this->queueEmail($to, $subject, $body, $isHTML, $toName, $priority, $metadata);
        }
        
        // Try to send immediately if SMTP is available
        if ($this->isSMTPAvailable()) {
            $sent = $this->sendEmailImmediately($to, $subject, $body, $isHTML, $toName);
            if ($sent) {
                $this->logEmail(null, $to, $subject, 'sent');
                return true;
            }
        }
        
        // If immediate sending fails, queue the email
        return $this->queueEmail($to, $subject, $body, $isHTML, $toName, $priority, $metadata);
    }
    
    /**
     * Send email immediately using SMTP
     */
    private function sendEmailImmediately($to, $subject, $body, $isHTML = true, $toName = null) {
        try {
            $mail = new PHPMailer(true);
            
            // Load email config
            $configPath = __DIR__ . '/../../config/email_config.php';
            if (file_exists($configPath)) {
                $config = require $configPath;
                
                $mail->isSMTP();
                $mail->Host = $config['smtp_host'];
                $mail->SMTPAuth = true;
                $mail->Username = $config['smtp_username'];
                $mail->Password = $config['smtp_password'];
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = $config['smtp_port'];
                $mail->CharSet = 'UTF-8';
                
                $mail->setFrom($config['from_email'], $config['from_name']);
                $mail->addAddress($to, $toName);
                $mail->isHTML($isHTML);
                $mail->Subject = $subject;
                $mail->Body = $body;
                
                $result = $mail->send();
                
                if ($result) {
                    $this->updateSMTPStatus(true);
                    return true;
                } else {
                    $this->updateSMTPStatus(false);
                    return false;
                }
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("Error sending email immediately: " . $e->getMessage());
            $this->updateSMTPStatus(false);
            return false;
        }
    }
    
    /**
     * Process email queue
     */
    public function processQueue($batchSize = null) {
        if (!$this->isSMTPAvailable()) {
            error_log("SMTP not available, skipping queue processing");
            return false;
        }
        
        $batchSize = $batchSize ?: (int)$this->settings['batch_size'];
        
        try {
            // Get pending emails ordered by priority and creation time
            $stmt = $this->pdo->prepare("
                SELECT * FROM email_queue 
                WHERE status = 'pending' 
                ORDER BY 
                    CASE priority 
                        WHEN 'urgent' THEN 1 
                        WHEN 'high' THEN 2 
                        WHEN 'normal' THEN 3 
                        WHEN 'low' THEN 4 
                    END,
                    created_at ASC
                LIMIT " . (int)$batchSize
            );
            $stmt->execute();
            $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $processed = 0;
            $failed = 0;
            
            foreach ($emails as $email) {
                // Update status to sending
                $this->updateEmailStatus($email['id'], 'sending');
                
                // Try to send the email
                $sent = $this->sendEmailImmediately(
                    $email['to_email'], 
                    $email['subject'], 
                    $email['body'], 
                    $email['is_html'], 
                    $email['to_name']
                );
                
                if ($sent) {
                    $this->updateEmailStatus($email['id'], 'sent');
                    $this->logEmail($email['id'], $email['to_email'], $email['subject'], 'sent');
                    $processed++;
                } else {
                    $attempts = $email['attempts'] + 1;
                    $maxAttempts = $this->settings['max_attempts'];
                    
                    if ($attempts >= $maxAttempts) {
                        $this->updateEmailStatus($email['id'], 'failed', 'Max attempts reached');
                        $this->logEmail($email['id'], $email['to_email'], $email['subject'], 'failed', 'Max attempts reached');
                        $failed++;
                    } else {
                        $this->updateEmailStatus($email['id'], 'pending', null, $attempts);
                        $failed++;
                    }
                }
            }
            
            // Update last processed timestamp
            $this->updateSetting('last_processed', time());
            
            error_log("Queue processing completed: $processed sent, $failed failed");
            return ['sent' => $processed, 'failed' => $failed];
            
        } catch (Exception $e) {
            error_log("Error processing email queue: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update email status
     */
    private function updateEmailStatus($emailId, $status, $errorMessage = null, $attempts = null) {
        try {
            $sql = "UPDATE email_queue SET status = ?, last_attempt_at = NOW()";
            $params = [$status];
            
            if ($errorMessage !== null) {
                $sql .= ", error_message = ?";
                $params[] = $errorMessage;
            }
            
            if ($attempts !== null) {
                $sql .= ", attempts = ?";
                $params[] = $attempts;
            }
            
            if ($status === 'sent') {
                $sql .= ", sent_at = NOW()";
            }
            
            $sql .= " WHERE id = ?";
            $params[] = $emailId;
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
        } catch (Exception $e) {
            error_log("Error updating email status: " . $e->getMessage());
        }
    }
    
    /**
     * Log email activity
     */
    private function logEmail($emailQueueId, $to, $subject, $status, $errorMessage = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO email_logs (email_queue_id, to_email, subject, status, error_message, sent_at) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $sentAt = $status === 'sent' ? date('Y-m-d H:i:s') : null;
            $stmt->execute([$emailQueueId, $to, $subject, $status, $errorMessage, $sentAt]);
            
        } catch (Exception $e) {
            error_log("Error logging email: " . $e->getMessage());
        }
    }
    
    /**
     * Save email to file as backup
     */
    private function saveEmailToFile($to, $subject, $body, $sent = false, $emailId = null) {
        try {
            $filename = 'email_' . date('Y-m-d_H-i-s') . '_' . uniqid() . '.html';
            $filepath = $this->emailDir . '/' . $filename;
            
            $status = $sent ? '✅ Sent Successfully' : '📁 Queued for Later Sending';
            $queueInfo = $emailId ? " (Queue ID: $emailId)" : '';
            
            $emailContent = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <title>Email: $subject</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 20px; }
                    .email-header { background: #f0f0f0; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
                    .email-content { border: 1px solid #ddd; padding: 20px; border-radius: 5px; }
                    .email-meta { background: #e9ecef; padding: 10px; border-radius: 3px; margin-bottom: 15px; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class='email-header'>
                    <h2>📧 ELMS Email Notification</h2>
                    <div class='email-meta'>
                        <strong>To:</strong> $to<br>
                        <strong>Subject:</strong> $subject<br>
                        <strong>Processed:</strong> " . date('Y-m-d H:i:s') . "<br>
                        <strong>Status:</strong> <span style='color: " . ($sent ? 'green' : 'orange') . ";'>$status$queueInfo</span>
                    </div>
                </div>
                <div class='email-content'>
                    $body
                </div>
                <div style='margin-top: 20px; padding: 10px; background: #f8f9fa; border-radius: 3px; font-size: 12px; color: #666;'>
                    <strong>Note:</strong> This email was processed by the ELMS Offline Email System.
                    <br><a href='../emails/'>View All Emails</a>
                </div>
            </body>
            </html>
            ";
            
            file_put_contents($filepath, $emailContent);
            
        } catch (Exception $e) {
            error_log("Error saving email to file: " . $e->getMessage());
        }
    }
    
    /**
     * Get queue statistics
     */
    public function getQueueStats() {
        try {
            $stmt = $this->pdo->query("
                SELECT 
                    status,
                    COUNT(*) as count,
                    priority,
                    COUNT(CASE WHEN priority = 'urgent' THEN 1 END) as urgent_count,
                    COUNT(CASE WHEN priority = 'high' THEN 1 END) as high_count,
                    COUNT(CASE WHEN priority = 'normal' THEN 1 END) as normal_count,
                    COUNT(CASE WHEN priority = 'low' THEN 1 END) as low_count
                FROM email_queue 
                GROUP BY status, priority
            ");
            
            $stats = [
                'pending' => 0,
                'sending' => 0,
                'sent' => 0,
                'failed' => 0,
                'cancelled' => 0,
                'urgent' => 0,
                'high' => 0,
                'normal' => 0,
                'low' => 0
            ];
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $stats[$row['status']] = $row['count'];
                $stats[$row['priority']] = $row['count'];
            }
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("Error getting queue stats: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Clean up old sent emails
     */
    public function cleanupOldEmails() {
        try {
            $cleanupDays = $this->settings['cleanup_days'];
            $stmt = $this->pdo->prepare("
                DELETE FROM email_queue 
                WHERE status = 'sent' 
                AND sent_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$cleanupDays]);
            
            $deleted = $stmt->rowCount();
            error_log("Cleaned up $deleted old sent emails");
            return $deleted;
            
        } catch (Exception $e) {
            error_log("Error cleaning up old emails: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Update setting
     */
    private function updateSetting($key, $value) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE offline_email_settings 
                SET setting_value = ?, updated_at = NOW() 
                WHERE setting_key = ?
            ");
            $stmt->execute([$value, $key]);
            $this->settings[$key] = $value;
        } catch (Exception $e) {
            error_log("Error updating setting $key: " . $e->getMessage());
        }
    }
    
    /**
     * Test SMTP connection
     */
    public function testSMTPConnection() {
        try {
            $configPath = __DIR__ . '/../../config/email_config.php';
            if (!file_exists($configPath)) {
                return false;
            }
            
            $config = require $configPath;
            
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $config['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $config['smtp_username'];
            $mail->Password = $config['smtp_password'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $config['smtp_port'];
            $mail->SMTPDebug = 0; // Disable debug output
            $mail->Timeout = 10; // 10 second timeout
            
            // Test connection
            $connected = $mail->smtpConnect();
            $mail->smtpClose();
            
            $this->updateSMTPStatus($connected);
            return $connected;
            
        } catch (Exception $e) {
            error_log("SMTP connection test failed: " . $e->getMessage());
            $this->updateSMTPStatus(false);
            return false;
        }
    }
}
?>
