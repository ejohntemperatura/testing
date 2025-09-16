<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

try {
    // Get count of pending leave requests
    $stmt = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'pending'");
    $pending_count = $stmt->fetchColumn();
    
    echo json_encode([
        'success' => true, 
        'count' => (int)$pending_count
    ]);
} catch (PDOException $e) {
    error_log("Database error fetching pending leave count: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
