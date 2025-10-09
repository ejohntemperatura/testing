<?php
/**
 * Fix ALL Include Paths in Organized ELMS Structure
 * This script fixes paths for files that are 4 levels deep from root
 */

echo "🔧 Fixing ALL Organized File Paths...\n";
echo "=====================================\n\n";

// Define the correct path mappings
$correctPaths = [
    // For files 4 levels deep (app/modules/*/views/ and app/modules/*/controllers/)
    'app/modules/admin/views/' => [
        '../../../config/' => '../../../../config/',
        '../../../includes/' => '../../../../includes/',
        '../../../assets/' => '../../../../assets/',
        '../auth/index.php' => '../../../../auth/views/login.php'
    ],
    'app/modules/admin/controllers/' => [
        '../../../config/' => '../../../../config/',
        '../../../includes/' => '../../../../includes/',
        '../../../assets/' => '../../../../assets/',
        '../auth/index.php' => '../../../../auth/views/login.php'
    ],
    'app/modules/user/views/' => [
        '../../../config/' => '../../../../config/',
        '../../../includes/' => '../../../../includes/',
        '../../../assets/' => '../../../../assets/',
        '../auth/index.php' => '../../../../auth/views/login.php'
    ],
    'app/modules/user/controllers/' => [
        '../../../config/' => '../../../../config/',
        '../../../includes/' => '../../../../includes/',
        '../../../assets/' => '../../../../assets/',
        '../auth/index.php' => '../../../../auth/views/login.php'
    ],
    'app/modules/director/views/' => [
        '../../../config/' => '../../../../config/',
        '../../../includes/' => '../../../../includes/',
        '../../../assets/' => '../../../../assets/',
        '../auth/index.php' => '../../../../auth/views/login.php'
    ],
    'app/modules/director/controllers/' => [
        '../../../config/' => '../../../../config/',
        '../../../includes/' => '../../../../includes/',
        '../../../assets/' => '../../../../assets/',
        '../auth/index.php' => '../../../../auth/views/login.php'
    ],
    'app/modules/department/views/' => [
        '../../../config/' => '../../../../config/',
        '../../../includes/' => '../../../../includes/',
        '../../../assets/' => '../../../../assets/',
        '../auth/index.php' => '../../../../auth/views/login.php'
    ],
    'app/modules/department/controllers/' => [
        '../../../config/' => '../../../../config/',
        '../../../includes/' => '../../../../includes/',
        '../../../assets/' => '../../../../assets/',
        '../auth/index.php' => '../../../../auth/views/login.php'
    ],
    
    // For files 3 levels deep (app/core/services/, app/shared/*/)
    'app/core/services/' => [
        '../../config/' => '../../../config/',
        '../../includes/' => '../../../includes/',
        '../../assets/' => '../../../assets/'
    ],
    'app/shared/components/' => [
        '../../config/' => '../../../config/',
        '../../includes/' => '../../../includes/',
        '../../assets/' => '../../../assets/'
    ],
    'app/shared/actions/' => [
        '../../config/' => '../../../config/',
        '../../includes/' => '../../../includes/',
        '../../assets/' => '../../../assets/'
    ],
    
    // For files 2 levels deep (auth/views/, auth/controllers/)
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
    ],
    
    // For files 1 level deep (api/, cron/, includes/)
    'api/' => [
        '../config/' => '../config/',
        '../includes/' => '../includes/',
        '../assets/' => '../assets/'
    ],
    'cron/' => [
        '../config/' => '../config/',
        '../includes/' => '../includes/',
        '../assets/' => '../assets/'
    ],
    'includes/' => [
        '../config/' => '../config/',
        '../assets/' => '../assets/'
    ]
];

function fixFilePaths($filePath, $mappings) {
    if (!file_exists($filePath)) {
        return false;
    }
    
    $content = file_get_contents($filePath);
    $originalContent = $content;
    
    foreach ($mappings as $oldPath => $newPath) {
        // Fix require_once, include_once, require, include
        $patterns = [
            "require_once '$oldPath" => "require_once '$newPath",
            'require_once "' . $oldPath => 'require_once "' . $newPath,
            "include_once '$oldPath" => "include_once '$newPath",
            'include_once "' . $oldPath => 'include_once "' . $newPath,
            "require '$oldPath" => "require '$newPath",
            'require "' . $oldPath => 'require "' . $newPath,
            "include '$oldPath" => "include '$newPath",
            'include "' . $oldPath => 'include "' . $newPath,
        ];
        
        foreach ($patterns as $pattern => $replacement) {
            $content = str_replace($pattern, $replacement, $content);
        }
        
        // Fix header redirects
        $content = str_replace("header('Location: $oldPath", "header('Location: $newPath", $content);
        $content = str_replace('header("Location: ' . $oldPath, 'header("Location: ' . $newPath, $content);
        
        // Fix href links
        $content = str_replace("href=\"$oldPath", "href=\"$newPath", $content);
        $content = str_replace("href='$oldPath", "href='$newPath", $content);
        
        // Fix src links
        $content = str_replace("src=\"$oldPath", "src=\"$newPath", $content);
        $content = str_replace("src='$oldPath", "src='$newPath", $content);
    }
    
    if ($content !== $originalContent) {
        file_put_contents($filePath, $content);
        return true;
    }
    
    return false;
}

$totalUpdated = 0;
foreach ($correctPaths as $dirPath => $mappings) {
    if (is_dir($dirPath)) {
        echo "📁 Processing: $dirPath\n";
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dirPath),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        $dirUpdated = 0;
        foreach ($iterator as $file) {
            if ($file->isFile() && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                if (fixFilePaths($file->getPathname(), $mappings)) {
                    $dirUpdated++;
                    $totalUpdated++;
                    echo "   ✅ Updated: " . basename($file->getPathname()) . "\n";
                }
            }
        }
        echo "   Total updated in this directory: $dirUpdated\n\n";
    }
}

echo "🎉 Path fixing completed!\n";
echo "========================\n";
echo "Total files updated: $totalUpdated\n";
echo "All organized files should now have correct paths!\n";
?>

