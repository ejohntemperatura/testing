<?php
// Start output buffering to prevent any output before JSON
ob_start();

session_start();
require_once __DIR__ . '/../../../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request ID']);
    exit();
}

$request_id = (int)$_GET['id'];

try {
    // Get leave request details with employee information
    $stmt = $pdo->prepare("
        SELECT 
            lr.*,
            e.name as employee_name,
            e.email as employee_email,
            e.department,
            e.position,
            e.contact,
            dept_head.name as dept_head_name,
            director.name as director_name
        FROM leave_requests lr
        JOIN employees e ON lr.employee_id = e.id
        LEFT JOIN employees dept_head ON lr.dept_head_approved_by = dept_head.id
        LEFT JOIN employees director ON lr.director_approved_by = director.id
        WHERE lr.id = ?
    ");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$request) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Leave request not found']);
        exit();
    }
    
    // Format the response
    $response = [
        'success' => true,
        'leave' => [
            'id' => $request['id'],
            'employee_name' => $request['employee_name'],
            'employee_email' => $request['employee_email'],
            'department' => $request['department'],
            'position' => $request['position'],
            'contact' => $request['contact'],
            'leave_type' => ucfirst(str_replace('_', ' ', $request['leave_type'])),
            'start_date' => date('M d, Y', strtotime($request['start_date'])),
            'end_date' => date('M d, Y', strtotime($request['end_date'])),
            'reason' => $request['reason'],
            'status' => $request['final_approval_status'] ?? $request['status'],
            'created_at' => date('M d, Y', strtotime($request['created_at'])),
            'days_requested' => $request['days_requested'],
            
            // Department Head approval details
            'dept_head_approval' => $request['dept_head_approval'],
            'dept_head_name' => $request['dept_head_name'],
            'dept_head_approved_at' => $request['dept_head_approved_at'] ? date('M d, Y H:i A', strtotime($request['dept_head_approved_at'])) : null,
            'dept_head_rejection_reason' => $request['dept_head_rejection_reason'],
            
            // Director approval details
            'director_approval' => $request['director_approval'],
            'director_name' => $request['director_name'],
            'director_approved_at' => $request['director_approved_at'] ? date('M d, Y H:i A', strtotime($request['director_approved_at'])) : null,
            'director_rejection_reason' => $request['director_rejection_reason'],
            'approved_days_with_pay' => $request['approved_days_with_pay'],
            'approved_days_without_pay' => $request['approved_days_without_pay'],
            'director_approval_notes' => $request['director_approval_notes'],
            
            // Additional leave details
            'medical_condition' => $request['medical_condition'],
            'illness_specify' => $request['illness_specify'],
            'location_type' => $request['location_type'],
            'location_specify' => $request['location_specify'],
            'study_type' => $request['study_type'],
            'monetization_details' => $request['monetization_details'],
            'terminal_leave_details' => $request['terminal_leave_details'],
            'is_late' => $request['is_late'],
            'late_justification' => $request['late_justification']
        ]
    ];
    
    // Clear any output buffer and set proper headers
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode($response);
    
} catch (Exception $e) {
    // Clear any output buffer before sending error
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>
