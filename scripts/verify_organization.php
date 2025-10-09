<?php
/**
 * Verification Script for ELMS Reorganization
 * This script verifies that all files are in their correct locations
 */

echo "🔍 ELMS Reorganization Verification\n";
echo "=====================================\n\n";

// Define expected file locations
$expectedFiles = [
    // Admin module
    'app/modules/admin/views/dashboard.php' => 'Admin Dashboard',
    'app/modules/admin/views/manage_user.php' => 'Admin User Management',
    'app/modules/admin/views/leave_management.php' => 'Admin Leave Management',
    'app/modules/admin/views/calendar.php' => 'Admin Calendar',
    'app/modules/admin/views/reports.php' => 'Admin Reports',
    'app/modules/admin/controllers/approve_leave.php' => 'Admin Approve Leave',
    'app/modules/admin/controllers/reject_leave.php' => 'Admin Reject Leave',
    'app/modules/admin/controllers/functions.php' => 'Admin Functions',
    
    // User module
    'app/modules/user/views/dashboard.php' => 'User Dashboard',
    'app/modules/user/views/profile.php' => 'User Profile',
    'app/modules/user/views/submit_leave.php' => 'User Submit Leave',
    'app/modules/user/views/leave_history.php' => 'User Leave History',
    'app/modules/user/views/calendar.php' => 'User Calendar',
    'app/modules/user/controllers/check_leave_credits.php' => 'User Check Credits',
    
    // Director module
    'app/modules/director/views/dashboard.php' => 'Director Dashboard',
    'app/modules/director/views/calendar.php' => 'Director Calendar',
    'app/modules/director/controllers/approve_leave.php' => 'Director Approve Leave',
    'app/modules/director/controllers/reject_leave.php' => 'Director Reject Leave',
    
    // Department module
    'app/modules/department/views/dashboard.php' => 'Department Dashboard',
    'app/modules/department/views/calendar.php' => 'Department Calendar',
    'app/modules/department/controllers/approve_leave.php' => 'Department Approve Leave',
    'app/modules/department/controllers/reject_leave.php' => 'Department Reject Leave',
    
    // Auth module
    'auth/views/login.php' => 'Login Page',
    'auth/views/register.php' => 'Register Page',
    'auth/views/verify_email.php' => 'Email Verification',
    
    // Core services
    'app/core/services/EmailService.php' => 'Email Service',
    'app/core/services/LeaveCreditsCalculator.php' => 'Leave Credits Calculator',
    'app/core/services/RobustEmail.php' => 'Robust Email Service',
    
    // Shared components
    'app/shared/components/calendar_component.php' => 'Calendar Component',
    'app/shared/actions/manage_leave.php' => 'Manage Leave Action',
    
    // Includes
    'includes/header.php' => 'Header Include',
    'includes/sidebar.php' => 'Sidebar Include',
    'includes/navigation.php' => 'Navigation Include',
    
    // Config
    'config/database.php' => 'Database Config',
    'config/email_config.php' => 'Email Config',
    
    // API
    'api/get_alerts.php' => 'Get Alerts API',
    'api/send_alert.php' => 'Send Alert API',
];

// Check files
$missingFiles = [];
$existingFiles = [];

foreach ($expectedFiles as $file => $description) {
    if (file_exists($file)) {
        $existingFiles[] = "✅ $description: $file";
    } else {
        $missingFiles[] = "❌ $description: $file";
    }
}

// Display results
echo "📊 Verification Results:\n";
echo "========================\n\n";

echo "✅ Found Files (" . count($existingFiles) . "):\n";
foreach ($existingFiles as $file) {
    echo "   $file\n";
}

echo "\n❌ Missing Files (" . count($missingFiles) . "):\n";
foreach ($missingFiles as $file) {
    echo "   $file\n";
}

echo "\n📈 Summary:\n";
echo "===========\n";
echo "Total Expected Files: " . count($expectedFiles) . "\n";
echo "Files Found: " . count($existingFiles) . "\n";
echo "Files Missing: " . count($missingFiles) . "\n";
echo "Success Rate: " . round((count($existingFiles) / count($expectedFiles)) * 100, 1) . "%\n";

// Check directory structure
echo "\n📁 Directory Structure Check:\n";
echo "=============================\n";

$expectedDirs = [
    'app/modules/admin/controllers',
    'app/modules/admin/views',
    'app/modules/admin/api',
    'app/modules/user/controllers',
    'app/modules/user/views',
    'app/modules/user/api',
    'app/modules/director/controllers',
    'app/modules/director/views',
    'app/modules/director/api',
    'app/modules/department/controllers',
    'app/modules/department/views',
    'app/modules/department/api',
    'app/core/services',
    'app/shared/components',
    'app/shared/actions',
    'auth/controllers',
    'auth/views',
];

$missingDirs = [];
$existingDirs = [];

foreach ($expectedDirs as $dir) {
    if (is_dir($dir)) {
        $existingDirs[] = "✅ $dir";
    } else {
        $missingDirs[] = "❌ $dir";
    }
}

echo "✅ Found Directories (" . count($existingDirs) . "):\n";
foreach ($existingDirs as $dir) {
    echo "   $dir\n";
}

echo "\n❌ Missing Directories (" . count($missingDirs) . "):\n";
foreach ($missingDirs as $dir) {
    echo "   $dir\n";
}

echo "\n🎉 Reorganization Verification Complete!\n";
echo "========================================\n";

if (count($missingFiles) === 0 && count($missingDirs) === 0) {
    echo "✅ All files and directories are in their correct locations!\n";
    echo "✅ ELMS has been successfully reorganized!\n";
} else {
    echo "⚠️  Some files or directories may need attention.\n";
    echo "⚠️  Please review the missing items above.\n";
}
?>

