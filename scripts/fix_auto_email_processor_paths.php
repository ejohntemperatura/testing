<?php
/**
 * Fix Auto Email Processor Paths
 * This script fixes all references to auto_email_processor.php
 */

echo "🔧 Fixing Auto Email Processor Paths...\n";
echo "======================================\n\n";

// Find all files that reference auto_email_processor.php
$directories = [
    'app/modules/admin/views/',
    'app/modules/admin/controllers/',
    'app/modules/user/views/',
    'app/modules/user/controllers/',
    'app/modules/director/views/',
    'app/modules/director/controllers/',
    'app/modules/department/views/',
    'app/modules/department/controllers/',
    'app/core/services/',
    'app/shared/components/',
    'app/shared/actions/',
    'auth/views/',
    'auth/controllers/',
    'api/',
    'cron/',
    'includes/'
];

$totalUpdated = 0;

foreach ($directories as $dir) {
    if (is_dir($dir)) {
        echo "📁 Checking: $dir\n";
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                $content = file_get_contents($file->getPathname());
                $originalContent = $content;
                
                // Calculate the correct path based on directory depth
                $depth = substr_count($dir, '/') + 1;
                $backPath = str_repeat('../', $depth);
                
                // Fix the path to auto_email_processor.php
                $oldPattern = $backPath . 'includes/auto_email_processor.php';
                $newPattern = $backPath . 'app/core/services/auto_email_processor.php';
                
                $content = str_replace($oldPattern, $newPattern, $content);
                
                // Also fix with different quote styles
                $content = str_replace("'$oldPattern'", "'$newPattern'", $content);
                $content = str_replace('"' . $oldPattern . '"', '"' . $newPattern . '"', $content);
                
                if ($content !== $originalContent) {
                    file_put_contents($file->getPathname(), $content);
                    echo "   ✅ Updated: " . basename($file->getPathname()) . "\n";
                    $totalUpdated++;
                }
            }
        }
        echo "\n";
    }
}

echo "🎉 Auto Email Processor Path Fix Complete!\n";
echo "==========================================\n";
echo "Total files updated: $totalUpdated\n";
?>

