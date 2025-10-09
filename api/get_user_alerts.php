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
    // Get user's recent alerts (last 10)
    $stmt = $pdo->prepare("
        SELECT la.*, e.name as sent_by_name 
        FROM leave_alerts la 
        LEFT JOIN employees e ON la.sent_by = e.id 
        WHERE la.employee_id = ? 
        ORDER BY la.created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get unread count (only alerts that haven't been read)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as unread_count
        FROM leave_alerts 
        WHERE employee_id = ? 
        AND is_read = 0
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $unread_count = $stmt->fetch()['unread_count'];
    
    echo json_encode([
        'success' => true,
        'alerts' => $alerts,
        'unread_count' => $unread_count,
        'user_id' => $_SESSION['user_id'] // Debug info
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error fetching alerts: ' . $e->getMessage()
    ]);
}
?>
