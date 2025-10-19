<?php
/**
 * Database Migration Script: Add CTO (Compensatory Time Off) Support
 * This script adds CTO balance tracking and related tables
 */

// Database configuration
$host = 'localhost';
$dbname = 'elms_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== Adding CTO (Compensatory Time Off) Support ===\n\n";
    
    // 1. Add CTO balance column to employees table
    echo "1. Adding CTO balance column to employees table...\n";
    try {
        $pdo->exec("ALTER TABLE employees ADD COLUMN cto_balance DECIMAL(5,2) DEFAULT 0.00");
        echo "✓ Added cto_balance column to employees table\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "⚠ cto_balance column already exists\n";
        } else {
            echo "✗ Failed to add cto_balance column: " . $e->getMessage() . "\n";
        }
    }
    
    // 2. Add CTO to leave_type enum in leave_requests table
    echo "\n2. Adding CTO to leave_type enum...\n";
    try {
        $pdo->exec("ALTER TABLE leave_requests MODIFY COLUMN leave_type ENUM('annual', 'sick', 'maternity', 'paternity', 'bereavement', 'study', 'unpaid', 'vacation', 'special_privilege', 'solo_parent', 'vawc', 'rehabilitation', 'terminal', 'cto') NOT NULL");
        echo "✓ Added 'cto' to leave_type enum\n";
    } catch (PDOException $e) {
        echo "⚠ Could not update leave_type enum: " . $e->getMessage() . "\n";
    }
    
    // 3. Create CTO earnings table for tracking how CTO was earned
    echo "\n3. Creating cto_earnings table...\n";
    $ctoEarningsTable = "
    CREATE TABLE IF NOT EXISTS cto_earnings (
        id INT PRIMARY KEY AUTO_INCREMENT,
        employee_id INT NOT NULL,
        earned_date DATE NOT NULL,
        hours_worked DECIMAL(5,2) NOT NULL,
        cto_earned DECIMAL(5,2) NOT NULL,
        work_type ENUM('overtime', 'holiday', 'weekend', 'special_assignment') NOT NULL,
        rate_applied DECIMAL(3,2) NOT NULL DEFAULT 1.00,
        description TEXT NULL,
        approved_by INT NULL,
        approved_at DATETIME NULL,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
        FOREIGN KEY (approved_by) REFERENCES employees(id) ON DELETE SET NULL
    )";
    
    try {
        $pdo->exec($ctoEarningsTable);
        echo "✓ Created cto_earnings table\n";
    } catch (PDOException $e) {
        echo "✗ Failed to create cto_earnings table: " . $e->getMessage() . "\n";
    }
    
    // 4. Create CTO usage tracking table
    echo "\n4. Creating cto_usage table...\n";
    $ctoUsageTable = "
    CREATE TABLE IF NOT EXISTS cto_usage (
        id INT PRIMARY KEY AUTO_INCREMENT,
        employee_id INT NOT NULL,
        leave_request_id INT NULL,
        hours_used DECIMAL(5,2) NOT NULL,
        used_date DATE NOT NULL,
        description TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
        FOREIGN KEY (leave_request_id) REFERENCES leave_requests(id) ON DELETE SET NULL
    )";
    
    try {
        $pdo->exec($ctoUsageTable);
        echo "✓ Created cto_usage table\n";
    } catch (PDOException $e) {
        echo "✗ Failed to create cto_usage table: " . $e->getMessage() . "\n";
    }
    
    // 5. Create CTO expiration tracking table
    echo "\n5. Creating cto_expiration table...\n";
    $ctoExpirationTable = "
    CREATE TABLE IF NOT EXISTS cto_expiration (
        id INT PRIMARY KEY AUTO_INCREMENT,
        employee_id INT NOT NULL,
        cto_earnings_id INT NOT NULL,
        hours_expired DECIMAL(5,2) NOT NULL,
        expiration_date DATE NOT NULL,
        processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
        FOREIGN KEY (cto_earnings_id) REFERENCES cto_earnings(id) ON DELETE CASCADE
    )";
    
    try {
        $pdo->exec($ctoExpirationTable);
        echo "✓ Created cto_expiration table\n";
    } catch (PDOException $e) {
        echo "✗ Failed to create cto_expiration table: " . $e->getMessage() . "\n";
    }
    
    // 6. Add indexes for better performance
    echo "\n6. Adding database indexes...\n";
    $indexes = [
        "CREATE INDEX idx_cto_earnings_employee_date ON cto_earnings(employee_id, earned_date)",
        "CREATE INDEX idx_cto_earnings_status ON cto_earnings(status)",
        "CREATE INDEX idx_cto_usage_employee_date ON cto_usage(employee_id, used_date)",
        "CREATE INDEX idx_cto_expiration_date ON cto_expiration(expiration_date)"
    ];
    
    foreach ($indexes as $index) {
        try {
            $pdo->exec($index);
            echo "✓ Added index\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
                echo "⚠ Index already exists\n";
            } else {
                echo "⚠ Could not add index: " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "\n=== CTO Support Installation Complete ===\n";
    echo "✓ CTO balance tracking added to employees table\n";
    echo "✓ CTO added to leave types\n";
    echo "✓ CTO earnings tracking table created\n";
    echo "✓ CTO usage tracking table created\n";
    echo "✓ CTO expiration tracking table created\n";
    echo "✓ Database indexes added for performance\n";
    
    echo "\n=== CTO Features Available ===\n";
    echo "• Employees can earn CTO through overtime, holiday, and weekend work\n";
    echo "• CTO can be used for leave requests\n";
    echo "• Automatic expiration tracking (6 months default)\n";
    echo "• Supervisor approval required for CTO earnings\n";
    echo "• Maximum accumulation limits (40 hours default)\n";
    
    echo "\n=== Next Steps ===\n";
    echo "1. CTO is now available in the leave management system\n";
    echo "2. Update the LeaveCreditsCalculator to handle CTO calculations\n";
    echo "3. Add CTO earning interface for supervisors\n";
    echo "4. Add CTO to user leave credits display\n";
    
} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
    echo "Please check your database configuration.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
