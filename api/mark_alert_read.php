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

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['alert_id'])) {
    echo json_encode(['error' => 'Missing alert ID']);
    exit();
}

$alert_id = $input['alert_id'];

try {
    // Mark alert as read
    $stmt = $pdo->prepare("
        UPDATE leave_alerts 
        SET is_read = 1, read_at = NOW() 
        WHERE id = ? AND employee_id = ?
    ");
    $stmt->execute([$alert_id, $_SESSION['user_id']]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Alert marked as read'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Alert not found or already read'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error marking alert as read: ' . $e->getMessage()
    ]);
}
?>
