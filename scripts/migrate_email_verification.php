<?php
// Safe migration to add email verification fields and tables
require_once __DIR__ . '/../config/database.php';

function columnExists($pdo, $table, $column) {
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
    $stmt->execute([$column]);
    return (bool)$stmt->fetch();
}

function tableExists($pdo, $table) {
    $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
    $stmt->execute([$table]);
    return (bool)$stmt->fetch();
}

header('Content-Type: text/plain');

echo "Running Email Verification Migration\n====================================\n";

try {
    // Add columns to employees if missing
    $adds = [];
    if (!columnExists($pdo, 'employees', 'email_verified')) {
        $adds[] = "ADD COLUMN email_verified TINYINT(1) DEFAULT 0 AFTER email";
    }
    if (!columnExists($pdo, 'employees', 'verification_token')) {
        $adds[] = "ADD COLUMN verification_token VARCHAR(255) NULL AFTER email_verified";
    }
    if (!columnExists($pdo, 'employees', 'verification_expires')) {
        $adds[] = "ADD COLUMN verification_expires DATETIME NULL AFTER verification_token";
    }
    if (!columnExists($pdo, 'employees', 'account_status')) {
        $adds[] = "ADD COLUMN account_status ENUM('pending','active','suspended') DEFAULT 'pending' AFTER verification_expires";
    }
    if (!columnExists($pdo, 'employees', 'position')) {
        $adds[] = "ADD COLUMN position VARCHAR(255) NULL AFTER role";
    }
    if (!columnExists($pdo, 'employees', 'department')) {
        $adds[] = "ADD COLUMN department VARCHAR(255) NULL AFTER position";
    }
    if (!columnExists($pdo, 'employees', 'contact')) {
        $adds[] = "ADD COLUMN contact VARCHAR(255) NULL AFTER department";
    }

    if ($adds) {
        $sql = "ALTER TABLE employees \n" . implode(",\n", $adds);
        $pdo->exec($sql);
        echo "Employees table altered: added " . count($adds) . " column(s).\n";
    } else {
        echo "Employees table columns already up to date.\n";
    }

    // Create email_verification_logs table if missing
    if (!tableExists($pdo, 'email_verification_logs')) {
        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS email_verification_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    email VARCHAR(255) NOT NULL,
    verification_token VARCHAR(255) NOT NULL,
    sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    verified_at DATETIME NULL,
    expires_at DATETIME NOT NULL,
    status ENUM('sent','verified','expired','failed') DEFAULT 'sent',
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);
SQL);
        echo "Created table: email_verification_logs\n";
    } else {
        echo "Table exists: email_verification_logs\n";
    }

    // Create email_templates table if missing
    if (!tableExists($pdo, 'email_templates')) {
        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS email_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_name VARCHAR(100) NOT NULL UNIQUE,
    subject VARCHAR(255) NOT NULL,
    html_body TEXT NOT NULL,
    plain_text_body TEXT NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
SQL);
        echo "Created table: email_templates\n";
        $pdo->exec(<<<SQL
INSERT IGNORE INTO email_templates (template_name, subject, html_body, plain_text_body) VALUES
('verification_email','Verify Your Email - ELMS System','<h1>Email Verification Required</h1><p>Please verify your email to activate your account.</p>','Email Verification Required - Please verify your email to activate your account.'),
('welcome_email','Welcome to ELMS System - Account Verified','<h1>Welcome!</h1><p>Your account has been verified successfully.</p>','Welcome! Your account has been verified successfully.');
SQL);
        echo "Inserted default email templates.\n";
    } else {
        echo "Table exists: email_templates\n";
    }

    // Create dtr table if missing
    if (!tableExists($pdo, 'dtr')) {
        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS dtr (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    date DATE NOT NULL,
    morning_time_in DATETIME NULL,
    morning_time_out DATETIME NULL,
    afternoon_time_in DATETIME NULL,
    afternoon_time_out DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES employees(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_date (user_id, date)
);
SQL);
        echo "Created table: dtr\n";
    } else {
        echo "Table exists: dtr\n";
    }

    echo "\nMigration complete. You can now add a new user and emails should send.\n";
} catch (Throwable $t) {
    echo "ERROR: " . $t->getMessage() . "\n";
}
