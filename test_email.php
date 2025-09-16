<?php
/**
 * Email Configuration Test Script
 * Use this script to test if your email configuration is working correctly
 */

// Check if EmailService exists
if (!file_exists('includes/EmailService.php')) {
    die("❌ EmailService.php not found. Please ensure the file exists in the includes directory.\n");
}

// Check if email config exists
if (!file_exists('config/email_config.php')) {
    die("❌ email_config.php not found. Please create the email configuration file first.\n");
}

try {
    require_once 'includes/EmailService.php';
    
    echo "🔧 Testing Email Configuration...\n";
    echo "================================\n\n";
    
    // Test 1: Check if EmailService can be instantiated
    echo "1. Testing EmailService instantiation... ";
    $emailService = new EmailService();
    echo "✅ PASSED\n";
    
    // Test 2: Test email connection
    echo "2. Testing SMTP connection... ";
    if ($emailService->testConnection()) {
        echo "✅ PASSED - Test email sent successfully!\n";
        echo "   Check your email inbox for the test message.\n";
    } else {
        echo "❌ FAILED - Could not send test email.\n";
        echo "   Check your email configuration and server settings.\n";
    }
    
    echo "\n📧 Email Configuration Test Complete!\n";
    echo "====================================\n";
    
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        echo "✅ PHPMailer library is available\n";
    } else {
        echo "❌ PHPMailer library is missing. Run 'composer install' first.\n";
    }
    
    // Display configuration info (without sensitive data)
    $config = require 'config/email_config.php';
    echo "\n📋 Configuration Summary:\n";
    echo "   SMTP Host: " . $config['smtp_host'] . "\n";
    echo "   SMTP Port: " . $config['smtp_port'] . "\n";
    echo "   SMTP Encryption: " . $config['smtp_encryption'] . "\n";
    echo "   From Email: " . $config['from_email'] . "\n";
    echo "   From Name: " . $config['from_name'] . "\n";
    
    echo "\n💡 Next Steps:\n";
    echo "   1. If all tests passed, your email system is ready!\n";
    echo "   2. Try adding a new user through the admin panel\n";
    echo "   3. Check the user's email for verification message\n";
    echo "   4. Test the verification process\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "\n🔍 Troubleshooting Tips:\n";
    echo "   1. Check if Composer dependencies are installed\n";
    echo "   2. Verify email_config.php has correct Gmail settings\n";
    echo "   3. Ensure Gmail App Password is correct\n";
    echo "   4. Check server firewall allows SMTP connections\n";
    echo "   5. Verify PHP has required extensions enabled\n";
}

echo "\n📚 For more help, see EMAIL_VERIFICATION_SETUP.md\n";
?>
