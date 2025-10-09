<?php
/**
 * Migration Script: Update Leave Types to Civil Service Standards
 * 
 * This script updates the database schema to include all Civil Service leave types
 * and migrates existing data to use the new standardized leave types.
 */

require_once dirname(__DIR__) . '/config/database.php';

try {
    $pdo->beginTransaction();
    
    echo "Starting leave types migration...\n";
    
    // First, let's check current schema
    $stmt = $pdo->query("SHOW COLUMNS FROM leave_requests LIKE 'leave_type'");
    $column_info = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Current leave_type definition: " . $column_info['Type'] . "\n";
    
    // Update the ENUM to include all Civil Service leave types
    $new_enum_values = [
        'vacation',           // Vacation Leave (15 days/year)
        'sick',              // Sick Leave (15 days/year) 
        'mandatory',         // Mandatory Leave (5 days/year)
        'special_privilege', // Special Leave Privilege (3 days/year)
        'solo_parent',       // Solo Parent Leave (7 days/year)
        'vawc',             // VAWC Leave (10 days/year)
        'rehabilitation',    // Rehabilitation Privilege (6 months)
        'special_women',     // Special Leave Benefits for Women (2 months)
        'special_emergency', // Special Emergency Leave (3 days/year)
        'adoption',          // Adoption Leave (60 days)
        'maternity',         // Maternity Leave (105 days)
        'paternity',         // Paternity Leave (7 days)
        'bereavement',       // Bereavement Leave (3 days)
        'emergency',         // Emergency Leave (without pay)
        'study',             // Study Leave (without pay)
        'other'              // Other Purpose (without pay)
    ];
    
    // Create the new ENUM definition
    $enum_definition = "'" . implode("','", $new_enum_values) . "'";
    
    // Update the column definition
    $sql = "ALTER TABLE leave_requests MODIFY COLUMN leave_type ENUM($enum_definition) NOT NULL";
    $pdo->exec($sql);
    echo "Updated leave_type ENUM definition\n";
    
    // Migrate existing data to new leave types
    $migration_mapping = [
        'annual' => 'vacation',  // Map annual to vacation
        'unpaid' => 'other'      // Map unpaid to other
    ];
    
    foreach ($migration_mapping as $old_type => $new_type) {
        $stmt = $pdo->prepare("UPDATE leave_requests SET leave_type = ? WHERE leave_type = ?");
        $stmt->execute([$new_type, $old_type]);
        $affected = $stmt->rowCount();
        if ($affected > 0) {
            echo "Migrated $affected records from '$old_type' to '$new_type'\n";
        }
    }
    
    // Add new columns to employees table for Civil Service leave balances
    $new_columns = [
        'vacation_leave_balance' => 'INT DEFAULT 15',
        'mandatory_leave_balance' => 'INT DEFAULT 5',
        'special_leave_privilege_balance' => 'INT DEFAULT 3',
        'solo_parent_leave_balance' => 'INT DEFAULT 7',
        'vawc_leave_balance' => 'INT DEFAULT 10',
        'rehabilitation_leave_balance' => 'INT DEFAULT 0',
        'special_women_leave_balance' => 'INT DEFAULT 0',
        'special_emergency_leave_balance' => 'INT DEFAULT 3',
        'adoption_leave_balance' => 'INT DEFAULT 0',
        'maternity_leave_balance' => 'INT DEFAULT 0',
        'paternity_leave_balance' => 'INT DEFAULT 7',
        'bereavement_leave_balance' => 'INT DEFAULT 3'
    ];
    
    foreach ($new_columns as $column => $definition) {
        // Check if column exists
        $stmt = $pdo->query("SHOW COLUMNS FROM employees LIKE '$column'");
        if (!$stmt->fetch()) {
            $sql = "ALTER TABLE employees ADD COLUMN $column $definition";
            $pdo->exec($sql);
            echo "Added column: $column\n";
        } else {
            echo "Column $column already exists\n";
        }
    }
    
    // Add new columns to leave_requests table for additional fields
    $leave_request_columns = [
        'days_requested' => 'INT DEFAULT 1',
        'location_type' => 'VARCHAR(50) NULL',
        'location_specify' => 'VARCHAR(255) NULL',
        'medical_condition' => 'VARCHAR(255) NULL',
        'illness_specify' => 'VARCHAR(255) NULL',
        'special_women_condition' => 'VARCHAR(255) NULL',
        'study_type' => 'VARCHAR(255) NULL',
        'medical_certificate_path' => 'VARCHAR(500) NULL'
    ];
    
    foreach ($leave_request_columns as $column => $definition) {
        // Check if column exists
        $stmt = $pdo->query("SHOW COLUMNS FROM leave_requests LIKE '$column'");
        if (!$stmt->fetch()) {
            $sql = "ALTER TABLE leave_requests ADD COLUMN $column $definition";
            $pdo->exec($sql);
            echo "Added column to leave_requests: $column\n";
        } else {
            echo "Column $column already exists in leave_requests\n";
        }
    }
    
    // Update existing employees with default leave balances
    $update_sql = "UPDATE employees SET 
        vacation_leave_balance = 15,
        mandatory_leave_balance = 5,
        special_leave_privilege_balance = 3,
        solo_parent_leave_balance = 7,
        vawc_leave_balance = 10,
        special_emergency_leave_balance = 3,
        paternity_leave_balance = 7,
        bereavement_leave_balance = 3
        WHERE vacation_leave_balance IS NULL OR vacation_leave_balance = 0";
    
    $pdo->exec($update_sql);
    echo "Updated default leave balances for existing employees\n";
    
    $pdo->commit();
    echo "\nMigration completed successfully!\n";
    echo "All Civil Service leave types are now available in the system.\n";
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
