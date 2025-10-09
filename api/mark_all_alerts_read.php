<?php
// Suppress notices and warnings to prevent JSON corruption
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/config/database.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not authorized']);
    exit();
}

try {
    // Mark all alerts as read for this user
    $stmt = $pdo->prepare("
        UPDATE leave_alerts 
        SET is_read = 1, read_at = NOW() 
        WHERE employee_id = ? AND is_read = 0
    ");
    $stmt->execute([$_SESSION['user_id']]);
    
    $updated_count = $stmt->rowCount();
    
    echo json_encode([
        'success' => true,
        'message' => "Marked {$updated_count} alerts as read",
        'updated_count' => $updated_count
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error marking alerts as read: ' . $e->getMessage()
    ]);
}
?>
