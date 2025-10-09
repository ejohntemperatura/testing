<?php
/**
 * Email Queue Processor - Cron Job
 * 
 * This script should be run every 5-10 minutes to process the email queue
 * Add this to your system's crontab or Windows Task Scheduler
 * 
 * Example cron entry (every 5 minutes):
 * 0,5,10,15,20,25,30,35,40,45,50,55 * * * * /usr/bin/php /path/to/ELMS/cron/process_email_queue.php
 * 
 * Windows Task Scheduler:
 * - Create a new task
 * - Set trigger to repeat every 5 minutes
 * - Set action to run: php.exe C:\xampp\htdocs\ELMS\cron\process_email_queue.php
 */

// Set time limit for long-running script
set_time_limit(300); // 5 minutes

// Include required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/OfflineEmailManager.php';

// Log function
function logMessage($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logFile = __DIR__ . '/../logs/email_queue.log';
    
    // Create logs directory if it doesn't exist
    $logDir = dirname($logFile);
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logEntry = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    
    // Also output to console if running from command line
    if (php_sapi_name() === 'cli') {
        echo $logEntry;
    }
}

try {
    logMessage("Starting email queue processing...");
    
    // Initialize offline email manager
    $offlineManager = new OfflineEmailManager($pdo);
    
    // Test SMTP connection first
    $smtpAvailable = $offlineManager->testSMTPConnection();
    logMessage("SMTP connection test: " . ($smtpAvailable ? 'SUCCESS' : 'FAILED'));
    
    if (!$smtpAvailable) {
        logMessage("SMTP not available, skipping queue processing");
        exit(0);
    }
    
    // Get queue statistics before processing
    $statsBefore = $offlineManager->getQueueStats();
    logMessage("Queue stats before processing: " . json_encode($statsBefore));
    
    // Process the queue
    $result = $offlineManager->processQueue();
    
    if ($result) {
        logMessage("Queue processing completed: {$result['sent']} sent, {$result['failed']} failed");
        
        // Get queue statistics after processing
        $statsAfter = $offlineManager->getQueueStats();
        logMessage("Queue stats after processing: " . json_encode($statsAfter));
        
        // Clean up old emails if there are many sent emails
        if (($statsAfter['sent'] ?? 0) > 100) {
            $deleted = $offlineManager->cleanupOldEmails();
            if ($deleted > 0) {
                logMessage("Cleaned up $deleted old sent emails");
            }
        }
        
    } else {
        logMessage("Queue processing failed");
    }
    
    logMessage("Email queue processing completed successfully");
    
} catch (Exception $e) {
    logMessage("Error in email queue processing: " . $e->getMessage());
    logMessage("Stack trace: " . $e->getTraceAsString());
    exit(1);
}

exit(0);
?>
