<?php
session_start();
require_once '../../../../config/database.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Check if leave ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid leave ID']);
    exit();
}

$leave_id = $_GET['id'];

try {
    // Get leave request details
    $stmt = $pdo->prepare("
        SELECT 
            lr.*,
            lr.days_requested as total_days
        FROM leave_requests lr
        WHERE lr.id = ? AND lr.employee_id = ?
    ");
    $stmt->execute([$leave_id, $_SESSION['user_id']]);
    $leave = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$leave) {
        echo json_encode(['success' => false, 'message' => 'Leave request not found']);
        exit();
    }
    
    echo json_encode([
        'success' => true,
        'leave' => $leave
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching leave details: ' . $e->getMessage()
    ]);
}
?>
