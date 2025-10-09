<?php
/**
 * Fix Include Paths Script for ELMS Reorganization
 * This script fixes all include/require paths in the organized files
 */

echo "🔧 Fixing Include Paths in Organized Files...\n";
echo "============================================\n\n";

// Define the path mappings for each module
$pathMappings = [
    // Admin module paths (from app/modules/admin/views/)
    'app/modules/admin/views/' => [
        '../../config/' => '../../../config/',
        '../../includes/' => '../../../includes/',
        '../../assets/' => '../../../assets/',
        '../auth/index.php' => '../../../auth/views/login.php',
        'admin_dashboard.php' => 'dashboard.php',
        'manage_user.php' => 'manage_user.php',
        'leave_management.php' => 'leave_management.php',
        'view_chart.php' => 'calendar.php',
        'reports.php' => 'reports.php'
    ],
    
    // Admin module paths (from app/modules/admin/controllers/)
    'app/modules/admin/controllers/' => [
        '../../config/' => '../../../config/',
        '../../includes/' => '../../../includes/',
        '../../assets/' => '../../../assets/',
        '../auth/index.php' => '../../../auth/views/login.php'
    ],
    
    // User module paths (from app/modules/user/views/)
    'app/modules/user/views/' => [
        '../../config/' => '../../../config/',
        '../../includes/' => '../../../includes/',
        '../../assets/' => '../../../assets/',
        '../auth/index.php' => '../../../auth/views/login.php',
        'dashboard.php' => 'dashboard.php',
        'submit_leave.php' => 'submit_leave.php',
        'profile.php' => 'profile.php'
    ],
    
    // User module paths (from app/modules/user/controllers/)
    'app/modules/user/controllers/' => [
        '../../config/' => '../../../config/',
        '../../includes/' => '../../../includes/',
        '../../assets/' => '../../../assets/',
        '../auth/index.php' => '../../../auth/views/login.php'
    ],
    
    // Director module paths
    'app/modules/director/views/' => [
        '../../config/' => '../../../config/',
        '../../includes/' => '../../../includes/',
        '../../assets/' => '../../../assets/',
        '../auth/index.php' => '../../../auth/views/login.php'
    ],
    
    'app/modules/director/controllers/' => [
        '../../config/' => '../../../config/',
        '../../includes/' => '../../../includes/',
        '../../assets/' => '../../../assets/',
        '../auth/index.php' => '../../../auth/views/login.php'
    ],
    
    // Department module paths
    'app/modules/department/views/' => [
        '../../config/' => '../../../config/',
        '../../includes/' => '../../../includes/',
        '../../assets/' => '../../../assets/',
        '../auth/index.php' => '../../../auth/views/login.php'
    ],
    
    'app/modules/department/controllers/' => [
        '../../config/' => '../../../config/',
        '../../includes/' => '../../../includes/',
        '../../assets/' => '../../../assets/',
        '../auth/index.php' => '../../../auth/views/login.php'
    ],
    
    // Auth module paths
    'auth/views/' => [
        '../../config/' => '../../config/',
        '../../includes/' => '../../includes/',
        '../../assets/' => '../../assets/',
        '../index.php' => 'login.php'
    ],
    
    'auth/controllers/' => [
        '../config/' => '../../config/',
        '../includes/' => '../../includes/',
        '../assets/' => '../../assets/'
    ]
];

// Function to update file paths
function updateFilePaths($filePath, $mappings) {
    if (!file_exists($filePath)) {
        echo "File not found: $filePath\n";
        return false;
    }
    
    $content = file_get_contents($filePath);
    $originalContent = $content;
    
    foreach ($mappings as $oldPath => $newPath) {
        $content = str_replace($oldPath, $newPath, $content);
    }
    
    if ($content !== $originalContent) {
        file_put_contents($filePath, $content);
        echo "✅ Updated paths in: $filePath\n";
        return true;
    }
    
    return false;
}

// Process each module
foreach ($pathMappings as $modulePath => $mappings) {
    if (is_dir($modulePath)) {
        echo "📁 Processing module: $modulePath\n";
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($modulePath),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                updateFilePaths($file->getPathname(), $mappings);
            }
        }
        echo "\n";
    }
}

echo "🎉 Include path fixing completed!\n";
?>

