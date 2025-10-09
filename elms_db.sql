 database
CREATE DATABASE IF NOT EXISTS elms_db;
USE elms_db;

-- Create employees table with email verification fields
CREATE TABLE IF NOT EXISTS employees (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    email_verified TINYINT(1) DEFAULT 0,
    verification_token VARCHAR(255) NULL,
    verification_expires DATETIME NULL,
    account_status ENUM('pending', 'active', 'suspended') DEFAULT 'pending',
    role ENUM('employee', 'manager', 'admin') DEFAULT 'employee',
    position VARCHAR(255) NULL,
    department VARCHAR(255) NULL,
    contact VARCHAR(255) NULL,
    annual_leave_balance INT DEFAULT 20,
    sick_leave_balance INT DEFAULT 10,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create leave_requests table
CREATE TABLE IF NOT EXISTS leave_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    leave_type ENUM('annual', 'sick', 'maternity', 'paternity', 'bereavement', 'study', 'unpaid') NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'under_appeal') DEFAULT 'pending',
    days_requested INT DEFAULT 1,
    is_late TINYINT(1) DEFAULT 0,
    late_justification TEXT NULL,
    location_type ENUM('office', 'remote', 'hybrid') NULL,
    location_specify VARCHAR(255) NULL,
    medical_condition ENUM('minor', 'serious', 'chronic') NULL,
    illness_specify VARCHAR(255) NULL,
    special_women_condition ENUM('pregnancy', 'menstruation', 'miscarriage', 'other') NULL,
    study_type ENUM('conference', 'training', 'seminar', 'course', 'exam') NULL,
    medical_certificate_path VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id)
);

-- Create email_verification_logs table for tracking verification attempts
CREATE TABLE IF NOT EXISTS email_verification_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    email VARCHAR(255) NOT NULL,
    verification_token VARCHAR(255) NOT NULL,
    sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    verified_at DATETIME NULL,
    expires_at DATETIME NOT NULL,
    status ENUM('sent', 'verified', 'expired', 'failed') DEFAULT 'sent',
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

-- Create email_templates table for customizable emails
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

-- Create DTR (Daily Time Record) table
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

-- Insert sample employees (password: password123) - All verified and active
INSERT INTO employees (email, password, name, email_verified, account_status, role, position, department, contact) VALUES
('employee@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John Doe', 1, 'active', 'employee', 'Software Developer', 'IT Department', '+1234567890'),
('manager@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Jane Smith', 1, 'active', 'manager', 'Project Manager', 'Management', '+1234567891'),
('admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin User', 1, 'active', 'admin', 'System Administrator', 'IT Department', '+1234567892');

-- Insert sample leave requests with different types
INSERT INTO leave_requests (employee_id, leave_type, start_date, end_date, reason, status) VALUES
(1, 'annual', '2024-01-15', '2024-01-17', 'Family vacation', 'approved'),
(2, 'sick', '2024-01-20', '2024-01-20', 'Medical appointment', 'approved'),
(1, 'maternity', '2024-02-01', '2024-05-01', 'Maternity leave', 'approved'),
(2, 'paternity', '2024-02-15', '2024-02-22', 'Paternity leave', 'pending'),
(1, 'bereavement', '2024-01-25', '2024-01-26', 'Family bereavement', 'approved'),
(2, 'study', '2024-03-01', '2024-03-05', 'Professional development course', 'approved'),
(1, 'unpaid', '2024-04-01', '2024-04-03', 'Personal emergency', 'pending');

-- Insert default email templates
INSERT INTO email_templates (template_name, subject, html_body, plain_text_body) VALUES
('verification_email', 'Verify Your Email - ELMS System', 
'<h1>Email Verification Required</h1><p>Please verify your email to activate your account.</p>',
'Email Verification Required - Please verify your email to activate your account.'),
('welcome_email', 'Welcome to ELMS System - Account Verified',
'<h1>Welcome!</h1><p>Your account has been verified successfully.</p>',
'Welcome! Your account has been verified successfully.');

-- Create indexes for better performance
CREATE INDEX idx_verification_token ON employees(verification_token);
CREATE INDEX idx_email_verified ON employees(email_verified);
CREATE INDEX idx_account_status ON employees(account_status);
CREATE INDEX idx_employee_email ON employees(email);
CREATE INDEX idx_leave_employee_date ON leave_requests(employee_id, start_date);
CREATE INDEX idx_dtr_user_date ON dtr(user_id, date); 