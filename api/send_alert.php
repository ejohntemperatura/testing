<?php
session_start();
require_once dirname(__DIR__) . '/config/database.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['error' => 'Not authorized']);
    exit();
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['employee_id']) || !isset($input['alert_type']) || !isset($input['message'])) {
    echo json_encode(['error' => 'Missing required fields']);
    exit();
}

$employee_id = $input['employee_id'];
$alert_type = $input['alert_type'];
$message = $input['message'];
$priority = $input['priority'] ?? 'moderate';
$alert_category = $input['alert_category'] ?? 'utilization';

try {
    // Insert alert into database with enhanced fields
    $stmt = $pdo->prepare("
        INSERT INTO leave_alerts (employee_id, alert_type, message, sent_by, priority, alert_category, is_read, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, 0, NOW())
    ");
    $stmt->execute([$employee_id, $alert_type, $message, $_SESSION['user_id'], $priority, $alert_category]);
    
    // Get the alert ID
    $alert_id = $pdo->lastInsertId();
    
    // Get employee name for response
    $stmt = $pdo->prepare("SELECT name FROM employees WHERE id = ?");
    $stmt->execute([$employee_id]);
    $employee = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'message' => 'Alert sent successfully!',
        'alert_id' => $alert_id,
        'employee_name' => $employee['name'],
        'priority' => $priority,
        'category' => $alert_category
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error sending alert: ' . $e->getMessage()
    ]);
}
?>
