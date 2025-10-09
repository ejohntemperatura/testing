<?php
/**
 * Database Migration Script: Update employees table for CSC compliance
 * This script adds the necessary columns for CSC leave types
 */

// Database configuration
$host = 'localhost';
$dbname = 'elms_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== Updating Employees Table for CSC Compliance ===\n\n";
    
    // Check if columns already exist and add them if they don't
    $columnsToAdd = [
        'vacation_leave_balance' => 'INT DEFAULT 15',
        'sick_leave_balance' => 'INT DEFAULT 15', 
        'special_leave_privilege_balance' => 'INT DEFAULT 3',
        'maternity_leave_balance' => 'INT DEFAULT 105',
        'paternity_leave_balance' => 'INT DEFAULT 7',
        'solo_parent_leave_balance' => 'INT DEFAULT 7',
        'vawc_leave_balance' => 'INT DEFAULT 10',
        'rehabilitation_leave_balance' => 'INT DEFAULT 0',
        'terminal_leave_balance' => 'INT DEFAULT 0',
        'gender' => 'ENUM("male", "female") DEFAULT "male"',
        'is_solo_parent' => 'TINYINT(1) DEFAULT 0',
        'service_start_date' => 'DATE DEFAULT CURDATE()',
        'last_leave_credit_update' => 'DATE NULL'
    ];
    
    // Get existing columns
    $stmt = $pdo->query("DESCRIBE employees");
    $existingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $addedColumns = [];
    $skippedColumns = [];
    
    foreach ($columnsToAdd as $columnName => $columnDefinition) {
        if (!in_array($columnName, $existingColumns)) {
            try {
                $sql = "ALTER TABLE employees ADD COLUMN {$columnName} {$columnDefinition}";
                $pdo->exec($sql);
                $addedColumns[] = $columnName;
                echo "✓ Added column: {$columnName}\n";
            } catch (PDOException $e) {
                echo "✗ Failed to add column {$columnName}: " . $e->getMessage() . "\n";
            }
        } else {
            $skippedColumns[] = $columnName;
            echo "⚠ Column already exists: {$columnName}\n";
        }
    }
    
    // Migrate existing data from old column names to new ones
    if (in_array('annual_leave_balance', $existingColumns) && in_array('vacation_leave_balance', $addedColumns)) {
        try {
            $pdo->exec("UPDATE employees SET vacation_leave_balance = annual_leave_balance WHERE vacation_leave_balance = 15");
            echo "✓ Migrated data from annual_leave_balance to vacation_leave_balance\n";
        } catch (PDOException $e) {
            echo "⚠ Could not migrate annual_leave_balance data: " . $e->getMessage() . "\n";
        }
    }
    
    if (in_array('sick_leave_balance', $existingColumns)) {
        try {
            $pdo->exec("UPDATE employees SET sick_leave_balance = sick_leave_balance WHERE sick_leave_balance = 10");
            echo "✓ Updated sick_leave_balance to CSC standard (15 days)\n";
        } catch (PDOException $e) {
            echo "⚠ Could not update sick_leave_balance: " . $e->getMessage() . "\n";
        }
    }
    
    // Update leave_requests table to support new leave types
    echo "\n=== Updating Leave Requests Table ===\n";
    
    // Check current leave_type enum values
    $stmt = $pdo->query("SHOW COLUMNS FROM leave_requests LIKE 'leave_type'");
    $columnInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($columnInfo) {
        $currentEnum = $columnInfo['Type'];
        echo "Current leave_type enum: {$currentEnum}\n";
        
        // Add new CSC leave types to enum
        $newLeaveTypes = [
            'vacation', 'sick', 'special_privilege', 'maternity', 'paternity',
            'solo_parent', 'vawc', 'rehabilitation', 'study', 'terminal'
        ];
        
        $currentTypes = ['annual', 'sick', 'maternity', 'paternity', 'bereavement', 'study', 'unpaid'];
        $allTypes = array_unique(array_merge($currentTypes, $newLeaveTypes));
        
        $enumValues = "'" . implode("','", $allTypes) . "'";
        
        try {
            $sql = "ALTER TABLE leave_requests MODIFY COLUMN leave_type ENUM({$enumValues}) NOT NULL";
            $pdo->exec($sql);
            echo "✓ Updated leave_type enum to include CSC leave types\n";
        } catch (PDOException $e) {
            echo "⚠ Could not update leave_type enum: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n=== Migration Summary ===\n";
    echo "Added columns: " . count($addedColumns) . "\n";
    echo "Skipped existing columns: " . count($skippedColumns) . "\n";
    
    if (count($addedColumns) > 0) {
        echo "\n✓ Database successfully updated for CSC compliance!\n";
        echo "The following columns were added:\n";
        foreach ($addedColumns as $column) {
            echo "  - {$column}\n";
        }
    } else {
        echo "\n✓ Database was already up to date!\n";
    }
    
    echo "\n=== Next Steps ===\n";
    echo "1. The database schema is now CSC compliant\n";
    echo "2. Run the test script to verify everything works\n";
    echo "3. Update any existing leave requests to use new leave types if needed\n";
    
} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
    echo "Please check your database configuration.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
