<?php
/**
 * DTR to CTO Processing Cron Job
 * This script automatically processes DTR data to generate CTO earnings
 * Should be run daily (e.g., at end of day or early morning)
 */

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/app/core/services/DTRToCTOProcessor.php';

try {
    $processor = new DTRToCTOProcessor($pdo);
    
    echo "[" . date('Y-m-d H:i:s') . "] Starting DTR to CTO processing...\n";
    
    // Process yesterday's DTR data (to allow for late time-outs)
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    echo "[" . date('Y-m-d H:i:s') . "] Processing DTR data for: {$yesterday}\n";
    
    $result = $processor->processYesterdayDTR();
    
    if ($result['success']) {
        echo "[" . date('Y-m-d H:i:s') . "] Successfully processed {$result['processed']} DTR records\n";
        
        // Show summary
        $summary = $processor->getProcessingSummary($yesterday, $yesterday);
        if (!empty($summary)) {
            echo "[" . date('Y-m-d H:i:s') . "] CTO Earnings Summary for {$yesterday}:\n";
            foreach ($summary as $item) {
                echo "  - {$item['work_type']}: {$item['count']} records, {$item['total_hours_worked']} hours worked, {$item['total_cto_earned']} hours CTO earned\n";
            }
        }
        
        // Log any errors
        if (!empty($result['errors'])) {
            echo "[" . date('Y-m-d H:i:s') . "] Warnings/Errors:\n";
            foreach ($result['errors'] as $error) {
                echo "  - {$error}\n";
            }
        }
        
        // Log success
        $logMessage = "DTR to CTO Processing: {$result['processed']} records processed on " . date('Y-m-d H:i:s');
        error_log($logMessage);
        
    } else {
        echo "[" . date('Y-m-d H:i:s') . "] ERROR: " . $result['error'] . "\n";
        error_log("DTR to CTO Processing Error: " . $result['error']);
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] DTR to CTO processing completed\n";
    
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] FATAL ERROR: " . $e->getMessage() . "\n";
    error_log("DTR to CTO Processing Fatal Error: " . $e->getMessage());
}
?>
