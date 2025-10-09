<?php
/**
 * Path Update Script for ELMS Reorganization
 * This script updates all include/require paths after file reorganization
 */

// Define the path mappings
$pathMappings = [
    // Admin module paths
    '../includes/' => '../../includes/',
    '../config/' => '../../config/',
    '../assets/' => '../../assets/',
    '../admin/' => '../',
    
    // User module paths
    '../includes/' => '../../includes/',
    '../config/' => '../../config/',
    '../assets/' => '../../assets/',
    '../user/' => '../',
    
    // Director module paths
    '../includes/' => '../../includes/',
    '../config/' => '../../config/',
    '../assets/' => '../../assets/',
    '../director/' => '../',
    
    // Department module paths
    '../includes/' => '../../includes/',
    '../config/' => '../../config/',
    '../assets/' => '../../assets/',
    '../department/' => '../',
    
    // Auth module paths
    '../includes/' => '../../includes/',
    '../config/' => '../../config/',
    '../assets/' => '../../assets/',
    
    // Core service paths
    'includes/' => '../../includes/',
    'config/' => '../../config/',
    'assets/' => '../../assets/',
    
    // Shared component paths
    'shared/' => 'app/shared/',
    'includes/' => 'includes/',
    'config/' => 'config/',
    'assets/' => 'assets/',
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
        echo "Updated paths in: $filePath\n";
        return true;
    }
    
    return false;
}

// Directories to process
$directories = [
    'app/modules/admin/',
    'app/modules/user/',
    'app/modules/director/',
    'app/modules/department/',
    'app/core/',
    'app/shared/',
    'auth/',
    'includes/',
    'api/',
];

echo "Starting path update process...\n";

foreach ($directories as $dir) {
    if (is_dir($dir)) {
        echo "Processing directory: $dir\n";
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                updateFilePaths($file->getPathname(), $pathMappings);
            }
        }
    }
}

echo "Path update process completed!\n";
?>

