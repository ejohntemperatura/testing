<?php
/**
 * Update leave_alerts table to support enhanced alert features
 */

require_once dirname(__DIR__) . '/config/database.php';

try {
    // Add priority column if it doesn't exist
    $pdo->exec("
        ALTER TABLE leave_alerts 
        ADD COLUMN IF NOT EXISTS priority ENUM('low', 'moderate', 'critical', 'urgent') DEFAULT 'moderate' AFTER alert_type
    ");
    
    // Add is_read column if it doesn't exist
    $pdo->exec("
        ALTER TABLE leave_alerts 
        ADD COLUMN IF NOT EXISTS is_read TINYINT(1) DEFAULT 0 AFTER sent_by
    ");
    
    // Add read_at timestamp
    $pdo->exec("
        ALTER TABLE leave_alerts 
        ADD COLUMN IF NOT EXISTS read_at TIMESTAMP NULL AFTER is_read
    ");
    
    // Add alert_category for better organization
    $pdo->exec("
        ALTER TABLE leave_alerts 
        ADD COLUMN IF NOT EXISTS alert_category ENUM('utilization', 'year_end', 'csc_compliance', 'wellness', 'custom') DEFAULT 'utilization' AFTER priority
    ");
    
    // Update existing alert_category column to remove sil_compliance if it exists
    try {
        $pdo->exec("
            ALTER TABLE leave_alerts 
            MODIFY COLUMN alert_category ENUM('utilization', 'year_end', 'csc_compliance', 'wellness', 'custom') DEFAULT 'utilization'
        ");
        echo "Updated alert_category column to remove SIL references.\n";
    } catch (Exception $e) {
        // Column might not exist yet, that's okay
    }
    
    // Add metadata column for additional alert data
    $pdo->exec("
        ALTER TABLE leave_alerts 
        ADD COLUMN IF NOT EXISTS metadata JSON NULL AFTER alert_category
    ");
    
    echo "✅ Leave alerts table updated successfully!\n";
    echo "Added columns: priority, is_read, read_at, alert_category, metadata\n";
    
} catch (Exception $e) {
    echo "❌ Error updating leave_alerts table: " . $e->getMessage() . "\n";
}
?>
