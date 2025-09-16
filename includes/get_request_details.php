<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Check if ID is provided
if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Leave request ID is required']);
    exit();
}

$leave_id = $_GET['id'];

try {
    // Fetch leave request details with employee information
    $stmt = $pdo->prepare("
        SELECT 
            lr.*,
            e.name as employee_name,
            e.email as employee_email,
            e.department,
            e.position,
            e.contact
        FROM leave_requests lr
        JOIN employees e ON lr.employee_id = e.id
        WHERE lr.id = ?
    ");
    $stmt->execute([$leave_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$request) {
        http_response_code(404);
        echo json_encode(['error' => 'Leave request not found']);
        exit();
    }
    
    // Format dates for display
    $request['start_date'] = date('M d, Y', strtotime($request['start_date']));
    $request['end_date'] = date('M d, Y', strtotime($request['end_date']));
    $request['created_at'] = date('M d, Y H:i:s', strtotime($request['created_at']));
    
    // Calculate number of days
    $start = new DateTime($request['start_date']);
    $end = new DateTime($request['end_date']);
    $request['days'] = $start->diff($end)->days + 1;
    
    // Set content type to JSON
    header('Content-Type: application/json');
    echo json_encode($request);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?> 