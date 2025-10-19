<?php
/**
 * CTO Expiration Processor
 * This script processes CTO expiration based on the 6-month rule
 * Should be run daily via cron job
 */

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/app/core/services/LeaveCreditsCalculator.php';

try {
    $calculator = new LeaveCreditsCalculator($pdo);
    
    echo "[" . date('Y-m-d H:i:s') . "] Starting CTO expiration processing...\n";
    
    $expiredCount = $calculator->processCTOExpiration();
    
    if ($expiredCount > 0) {
        echo "[" . date('Y-m-d H:i:s') . "] Processed {$expiredCount} expired CTO earnings\n";
        
        // Log the expiration
        $logMessage = "CTO Expiration Process: {$expiredCount} earnings expired on " . date('Y-m-d H:i:s');
        error_log($logMessage);
        
        // Send notification to admin (optional)
        // You can add email notification here if needed
        
    } else {
        echo "[" . date('Y-m-d H:i:s') . "] No CTO earnings expired today\n";
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] CTO expiration processing completed successfully\n";
    
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n";
    error_log("CTO Expiration Error: " . $e->getMessage());
}
?>
