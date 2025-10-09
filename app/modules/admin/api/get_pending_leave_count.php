<?php
// Suppress notices and warnings to prevent JSON corruption
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/../../config/database.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['error' => 'Not authorized']);
    exit();
}

try {
    // Get count of pending leave requests
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as pending_count
        FROM leave_requests 
        WHERE status = 'pending'
    ");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $pending_count = (int)$result['pending_count'];
    
    echo json_encode([
        'success' => true,
        'count' => $pending_count,
        'message' => $pending_count > 0 ? "You have {$pending_count} pending leave request(s)" : "No pending requests"
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error fetching pending leave count: ' . $e->getMessage()
    ]);
}
?>
