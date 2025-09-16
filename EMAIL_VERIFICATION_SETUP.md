# Email Verification System Setup Guide

## Overview
This system implements a comprehensive email verification process when administrators add new users. New users receive a verification email and must verify their email address before accessing the system.

## Features
- ✅ **Automatic Email Verification**: Sends verification emails when admins create users
- ✅ **Secure Token System**: 24-hour expiration for verification links
- ✅ **Professional Email Templates**: Beautiful HTML and plain text emails
- ✅ **Temporary Password System**: Users receive secure temporary passwords after verification
- ✅ **Comprehensive Logging**: Tracks all verification attempts and outcomes
- ✅ **Gmail SMTP Support**: Configured for Gmail SMTP with security

## Prerequisites
- PHP 7.4 or higher
- MySQL/MariaDB database
- Composer (for PHPMailer dependency)
- Gmail account with App Password

## Installation Steps

### 1. Install Dependencies
```bash
composer install
```

### 2. Database Setup
Run the SQL commands from `database_updates.sql` in your database:
```sql
-- Add email verification fields to employees table
ALTER TABLE employees 
ADD COLUMN email_verified TINYINT(1) DEFAULT 0 AFTER email,
ADD COLUMN verification_token VARCHAR(255) NULL AFTER email_verified,
ADD COLUMN verification_expires DATETIME NULL AFTER verification_token,
ADD COLUMN account_status ENUM('pending', 'active', 'suspended') DEFAULT 'pending' AFTER verification_expires;

-- Create email_verification_logs table
CREATE TABLE email_verification_logs (
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

-- Create indexes for performance
CREATE INDEX idx_verification_token ON employees(verification_token);
CREATE INDEX idx_email_verified ON employees(email_verified);
CREATE INDEX idx_account_status ON employees(account_status);
```

### 3. Gmail Configuration

#### Step 1: Enable 2-Factor Authentication
1. Go to your Google Account settings
2. Navigate to Security
3. Enable 2-Step Verification

#### Step 2: Generate App Password
1. Go to Google Account settings → Security
2. Under "2-Step Verification", click "App passwords"
3. Generate a new app password for "Mail"
4. Copy the 16-character password

#### Step 3: Update Email Configuration
Edit `config/email_config.php`:
```php
<?php
return [
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_username' => 'your-email@gmail.com', // Your Gmail address
    'smtp_password' => 'your-16-char-app-password', // Your Gmail App Password
    'smtp_encryption' => 'tls',
    'from_email' => 'your-email@gmail.com', // Your Gmail address
    'from_name' => 'ELMS System',
    'reply_to' => 'noreply@elms.com'
];
?>
```

### 4. Test Email Configuration
Create a test file `test_email.php`:
```php
<?php
require_once 'includes/EmailService.php';

$emailService = new EmailService();
if ($emailService->testConnection()) {
    echo "✅ Email configuration is working correctly!";
} else {
    echo "❌ Email configuration failed. Check your settings.";
}
?>
```

## How It Works

### 1. Admin Creates User
- Admin fills out user form (name, email, position, etc.)
- System generates secure verification token
- User account is created with 'pending' status
- Verification email is sent automatically

### 2. User Receives Email
- Professional HTML email with verification button
- 24-hour expiration for security
- Clear instructions and branding

### 3. User Verifies Email
- Clicks verification link
- System validates token and expiration
- Account is activated
- Temporary password is generated and sent

### 4. User Logs In
- Uses email and temporary password
- Can change password after first login
- Full access to system features

## Email Templates

### Verification Email
- **Subject**: "Verify Your Email - ELMS System"
- **Content**: Professional HTML with verification button
- **Features**: Responsive design, clear call-to-action

### Welcome Email
- **Subject**: "Welcome to ELMS System - Account Verified"
- **Content**: Temporary password and next steps
- **Features**: Security instructions, login guidance

## Security Features

- **Secure Tokens**: 32-character random tokens
- **Time Expiration**: 24-hour link expiration
- **IP Logging**: Tracks verification attempts
- **User Agent Logging**: Monitors verification sources
- **Temporary Passwords**: Secure random passwords
- **HTTPS Support**: Secure verification links

## Troubleshooting

### Common Issues

#### 1. Email Not Sending
- Check Gmail App Password is correct
- Verify SMTP settings in `email_config.php`
- Check server firewall allows SMTP (port 587)
- Review error logs for specific error messages

#### 2. Verification Link Not Working
- Ensure database tables are created correctly
- Check verification token is being generated
- Verify token expiration logic
- Check URL rewriting if using custom URLs

#### 3. Database Errors
- Run database updates SQL commands
- Check table structure matches expected schema
- Verify foreign key constraints
- Check database permissions

### Debug Mode
Enable debug logging in `EmailService.php`:
```php
$this->mailer->SMTPDebug = SMTP::DEBUG_SERVER;
```

## Customization

### Email Templates
Edit email templates in `EmailService.php`:
- HTML templates in `getVerificationEmailTemplate()`
- Plain text templates in `getPlainTextVerificationEmail()`
- Welcome email templates in `getWelcomeEmailTemplate()`

### Email Settings
Modify email configuration in `config/email_config.php`:
- SMTP server settings
- From email and name
- Reply-to address
- Email branding

### Verification Process
Customize verification logic in `auth/verify_email.php`:
- Token validation
- Account activation
- Password generation
- Success/error handling

## Maintenance

### Regular Tasks
- Monitor email verification logs
- Clean expired verification tokens
- Review failed verification attempts
- Update email templates as needed

### Log Analysis
Query verification logs:
```sql
-- Check verification success rate
SELECT status, COUNT(*) as count 
FROM email_verification_logs 
GROUP BY status;

-- Find expired verifications
SELECT * FROM email_verification_logs 
WHERE status = 'sent' AND expires_at < NOW();

-- Monitor verification attempts by IP
SELECT ip_address, COUNT(*) as attempts 
FROM email_verification_logs 
GROUP BY ip_address;
```

## Support

For technical support or questions:
1. Check error logs in your server's error log
2. Verify all configuration files are correct
3. Test email configuration with test script
4. Review database structure and permissions

## Version History

- **v1.0**: Initial email verification system
- **v1.1**: Added comprehensive logging
- **v1.2**: Enhanced security features
- **v1.3**: Professional email templates

---

**Note**: This system requires proper server configuration and Gmail account setup. Ensure your hosting environment supports SMTP connections and has the necessary PHP extensions enabled.
