<?php
/**
 * Comprehensive Path Fix Script for ELMS
 * This script fixes ALL include/require paths throughout the entire system
 */

echo "🔧 Fixing ALL Include Paths in ELMS System...\n";
echo "==============================================\n\n";

// Define comprehensive path mappings for each directory level
$pathMappings = [
    // From app/modules/admin/views/ (3 levels deep)
    'app/modules/admin/views/' => [
        '../../../config/' => '../../../config/',
        '../../../includes/' => '../../../includes/',
        '../../../assets/' => '../../../assets/',
        '../auth/index.php' => '../../../auth/views/login.php',
        '../admin_dashboard.php' => 'dashboard.php',
        '../manage_user.php' => 'manage_user.php',
        '../leave_management.php' => 'leave_management.php',
        '../view_chart.php' => 'calendar.php',
        '../reports.php' => 'reports.php'
    ],
    
    // From app/modules/admin/controllers/ (3 levels deep)
    'app/modules/admin/controllers/' => [
        '../../../config/' => '../../../config/',
        '../../../includes/' => '../../../includes/',
        '../../../assets/' => '../../../assets/',
        '../auth/index.php' => '../../../auth/views/login.php'
    ],
    
    // From app/modules/user/views/ (3 levels deep)
    'app/modules/user/views/' => [
        '../../../config/' => '../../../config/',
        '../../../includes/' => '../../../includes/',
        '../../../assets/' => '../../../assets/',
        '../auth/index.php' => '../../../auth/views/login.php'
    ],
    
    // From app/modules/user/controllers/ (3 levels deep)
    'app/modules/user/controllers/' => [
        '../../../config/' => '../../../config/',
        '../../../includes/' => '../../../includes/',
        '../../../assets/' => '../../../assets/',
        '../auth/index.php' => '../../../auth/views/login.php'
    ],
    
    // From app/modules/director/views/ (3 levels deep)
    'app/modules/director/views/' => [
        '../../../config/' => '../../../config/',
        '../../../includes/' => '../../../includes/',
        '../../../assets/' => '../../../assets/',
        '../auth/index.php' => '../../../auth/views/login.php'
    ],
    
    // From app/modules/director/controllers/ (3 levels deep)
    'app/modules/director/controllers/' => [
        '../../../config/' => '../../../config/',
        '../../../includes/' => '../../../includes/',
        '../../../assets/' => '../../../assets/',
        '../auth/index.php' => '../../../auth/views/login.php'
    ],
    
    // From app/modules/department/views/ (3 levels deep)
    'app/modules/department/views/' => [
        '../../../config/' => '../../../config/',
        '../../../includes/' => '../../../includes/',
        '../../../assets/' => '../../../assets/',
        '../auth/index.php' => '../../../auth/views/login.php'
    ],
    
    // From app/modules/department/controllers/ (3 levels deep)
    'app/modules/department/controllers/' => [
        '../../../config/' => '../../../config/',
        '../../../includes/' => '../../../includes/',
        '../../../assets/' => '../../../assets/',
        '../auth/index.php' => '../../../auth/views/login.php'
    ],
    
    // From app/core/services/ (2 levels deep)
    'app/core/services/' => [
        '../../config/' => '../../config/',
        '../../includes/' => '../../includes/',
        '../../assets/' => '../../assets/'
    ],
    
    // From app/shared/components/ (2 levels deep)
    'app/shared/components/' => [
        '../../config/' => '../../config/',
        '../../includes/' => '../../includes/',
        '../../assets/' => '../../assets/'
    ],
    
    // From app/shared/actions/ (2 levels deep)
    'app/shared/actions/' => [
        '../../config/' => '../../config/',
        '../../includes/' => '../../includes/',
        '../../assets/' => '../../assets/'
    ],
    
    // From auth/views/ (2 levels deep)
    'auth/views/' => [
        '../../config/' => '../../config/',
        '../../includes/' => '../../includes/',
        '../../assets/' => '../../assets/',
        '../index.php' => 'login.php'
    ],
    
    // From auth/controllers/ (2 levels deep)
    'auth/controllers/' => [
        '../config/' => '../../config/',
        '../includes/' => '../../includes/',
        '../assets/' => '../../assets/'
    ],
    
    // From api/ (1 level deep)
    'api/' => [
        '../config/' => '../config/',
        '../includes/' => '../includes/',
        '../assets/' => '../assets/'
    ],
    
    // From cron/ (1 level deep)
    'cron/' => [
        '../config/' => '../config/',
        '../includes/' => '../includes/',
        '../assets/' => '../assets/'
    ],
    
    // From includes/ (1 level deep)
    'includes/' => [
        '../config/' => '../config/',
        '../assets/' => '../assets/'
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
        // Fix require_once and include_once paths
        $content = str_replace("require_once '$oldPath", "require_once '$newPath", $content);
        $content = str_replace('require_once "' . $oldPath, 'require_once "' . $newPath, $content);
        $content = str_replace("include_once '$oldPath", "include_once '$newPath", $content);
        $content = str_replace('include_once "' . $oldPath, 'include_once "' . $newPath, $content);
        $content = str_replace("require '$oldPath", "require '$newPath", $content);
        $content = str_replace('require "' . $oldPath, 'require "' . $newPath, $content);
        $content = str_replace("include '$oldPath", "include '$newPath", $content);
        $content = str_replace('include "' . $oldPath, 'include "' . $newPath, $content);
        
        // Fix header redirects
        $content = str_replace("header('Location: $oldPath", "header('Location: $newPath", $content);
        $content = str_replace('header("Location: ' . $oldPath, 'header("Location: ' . $newPath, $content);
    }
    
    if ($content !== $originalContent) {
        file_put_contents($filePath, $content);
        echo "✅ Updated paths in: $filePath\n";
        return true;
    }
    
    return false;
}

// Process each directory
$totalUpdated = 0;
foreach ($pathMappings as $dirPath => $mappings) {
    if (is_dir($dirPath)) {
        echo "📁 Processing directory: $dirPath\n";
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dirPath),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        $dirUpdated = 0;
        foreach ($iterator as $file) {
            if ($file->isFile() && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                if (updateFilePaths($file->getPathname(), $mappings)) {
                    $dirUpdated++;
                    $totalUpdated++;
                }
            }
        }
        echo "   Updated $dirUpdated files\n\n";
    }
}

echo "🎉 Complete path fixing finished!\n";
echo "================================\n";
echo "Total files updated: $totalUpdated\n";
echo "All include paths should now be working correctly!\n";
?>

