<?php
/**
 * Automatic Email Processor - Include File
 * Include this file in your pages to automatically process emails when internet is available
 * 
 * Usage: require_once 'includes/auto_email_processor.php';
 */

// Only process if not already processed in this request
if (!defined('EMAIL_PROCESSED')) {
    define('EMAIL_PROCESSED', true);
    
    // Check if we should process emails (only once per minute)
    $lastProcessed = $_SESSION['last_email_processed'] ?? 0;
    $currentTime = time();
    
    if ($currentTime - $lastProcessed >= 60) { // Process every minute
        try {
            require_once __DIR__ . '/OfflineEmailManager.php';
            
            $offlineManager = new OfflineEmailManager($pdo);
            
            // Check if SMTP is available
            if ($offlineManager->isSMTPAvailable()) {
                // Get pending emails count
                $stats = $offlineManager->getQueueStats();
                $pendingCount = $stats['pending'] ?? 0;
                
                if ($pendingCount > 0) {
                    // Process the queue
                    $result = $offlineManager->processQueue();
                    
                    if ($result && $result['sent'] > 0) {
                        // Log the processing
                        error_log("Auto email processing: {$result['sent']} sent, {$result['failed']} failed");
                    }
                }
            }
            
            // Update last processed time
            $_SESSION['last_email_processed'] = $currentTime;
            
        } catch (Exception $e) {
            error_log("Auto email processing error: " . $e->getMessage());
        }
    }
}
?>
