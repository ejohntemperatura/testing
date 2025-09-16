<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>PHP Error Check</h1>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Error reporting enabled</p>";

// Test database connection
try {
    require_once '../config/database.php';
    echo "<p>Database connection: OK</p>";
} catch (Exception $e) {
    echo "<p>Database connection error: " . $e->getMessage() . "</p>";
}

// Test session
session_start();
echo "<p>Session started: " . (session_status() === PHP_SESSION_ACTIVE ? 'Yes' : 'No') . "</p>";

// Test if we can access the dashboard
echo "<p><a href='dashboard.php'>Try Dashboard</a></p>";
echo "<p><a href='test_dashboard.php'>Try Test Dashboard</a></p>";
?>
