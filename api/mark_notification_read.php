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

// Check if alert ID is provided
if (!isset($_POST['alert_id']) || !is_numeric($_POST['alert_id'])) {
    echo json_encode(['error' => 'Invalid alert ID']);
    exit();
}

$alert_id = (int)$_POST['alert_id'];

try {
    // Mark the specific alert as read
    $stmt = $pdo->prepare("
        UPDATE leave_alerts 
        SET is_read = 1 
        WHERE id = ? AND employee_id = ?
    ");
    $result = $stmt->execute([$alert_id, $_SESSION['user_id']]);
    
    if ($result && $stmt->rowCount() > 0) {
        // Get updated unread count
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
            'unread_count' => $unread_count,
            'message' => 'Notification marked as read'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Notification not found or already read'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error marking notification as read: ' . $e->getMessage()
    ]);
}
?>
