<?php
// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');

$host = 'localhost';
$dbname = 'elms_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Set MySQL timezone to match PHP timezone
    $pdo->exec("SET time_zone = '+08:00'");
} catch(PDOException $e) {
    // Log error instead of echoing to prevent JSON corruption
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection failed");
}
?> 