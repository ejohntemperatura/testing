<?php
/**
 * Automatic Email Queue Processor
 * This script runs automatically to process emails when internet is available
 * No user intervention required - runs silently in background
 */

// Prevent direct web access
if (isset($_SERVER['HTTP_HOST'])) {
    die('This script can only be run from command line');
}

// Set time limit for long-running script
set_time_limit(60); // 1 minute max

// Include required files
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/OfflineEmailManager.php';

// Log function
function logMessage($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logFile = __DIR__ . '/logs/auto_email_processing.log';
    
    // Create logs directory if it doesn't exist
    $logDir = dirname($logFile);
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logEntry = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

try {
    // Initialize offline email manager
    $offlineManager = new OfflineEmailManager($pdo);
    
    // Check if there are pending emails
    $stats = $offlineManager->getQueueStats();
    $pendingCount = $stats['pending'] ?? 0;
    
    if ($pendingCount == 0) {
        // No pending emails, exit silently
        exit(0);
    }
    
    logMessage("Found $pendingCount pending emails");
    
    // Test internet connectivity by testing SMTP
    $smtpAvailable = $offlineManager->testSMTPConnection();
    
    if (!$smtpAvailable) {
        logMessage("No internet connection - emails remain queued");
        exit(0);
    }
    
    logMessage("Internet available - processing email queue");
    
    // Process the queue
    $result = $offlineManager->processQueue();
    
    if ($result) {
        $sent = $result['sent'] ?? 0;
        $failed = $result['failed'] ?? 0;
        logMessage("Email processing completed: $sent sent, $failed failed");
        
        // If emails were sent, also log to a success file
        if ($sent > 0) {
            $successFile = __DIR__ . '/logs/email_success.log';
            $successEntry = "[" . date('Y-m-d H:i:s') . "] Successfully sent $sent emails" . PHP_EOL;
            file_put_contents($successFile, $successEntry, FILE_APPEND | LOCK_EX);
        }
    } else {
        logMessage("Email processing failed");
    }
    
} catch (Exception $e) {
    logMessage("Error: " . $e->getMessage());
}

exit(0);
?>
