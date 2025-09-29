<?php
$host = 'localhost';
$dbname = 'elms_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    // Log error instead of echoing to prevent JSON corruption
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection failed");
}
?> 