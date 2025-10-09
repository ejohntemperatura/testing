<?php
/**
 * API endpoint to get leave request details for Director modal
 */

header('Content-Type: application/json');

session_start();
require_once '../../../../config/database.php';

// Check if user is logged in and is a director
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'director') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get request ID
$request_id = $_GET['id'] ?? '';

if (empty($request_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Request ID is required']);
    exit();
}

try {
    // Get leave request details with employee and approver information
    $stmt = $pdo->prepare("
        SELECT 
            lr.*,
            e.name as employee_name,
            e.email as employee_email,
            e.department,
            e.position,
            e.contact,
            dept_approver.name as dept_head_name,
            dept_approver.position as dept_head_position
        FROM leave_requests lr 
        JOIN employees e ON lr.employee_id = e.id 
        LEFT JOIN employees dept_approver ON lr.dept_head_approved_by = dept_approver.id
        WHERE lr.id = ?
    ");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$request) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Leave request not found']);
        exit();
    }
    
    // Calculate days requested
    $start_date = new DateTime($request['start_date']);
    $end_date = new DateTime($request['end_date']);
    $days_requested = $start_date->diff($end_date)->days + 1;
    
    // Format dates
    $request['start_date'] = date('M d, Y', strtotime($request['start_date']));
    $request['end_date'] = date('M d, Y', strtotime($request['end_date']));
    $request['days_requested'] = $days_requested;
    $request['leave_type'] = ucfirst(str_replace('_', ' ', $request['leave_type']));
    
    // Format location type for display
    if ($request['location_type']) {
        switch ($request['location_type']) {
            case 'within_philippines':
                $request['location_type'] = 'Within Philippines';
                break;
            case 'outside_philippines':
                $request['location_type'] = 'Outside Philippines';
                break;
            default:
                $request['location_type'] = ucfirst(str_replace('_', ' ', $request['location_type']));
                break;
        }
    }
    
    // Format medical condition for display
    if ($request['medical_condition']) {
        switch ($request['medical_condition']) {
            case 'minor':
                $request['medical_condition'] = 'Minor';
                break;
            case 'serious':
                $request['medical_condition'] = 'Serious';
                break;
            case 'chronic':
                $request['medical_condition'] = 'Chronic';
                break;
            default:
                $request['medical_condition'] = ucfirst(str_replace('_', ' ', $request['medical_condition']));
                break;
        }
    }
    
    // Format special women condition for display
    if ($request['special_women_condition']) {
        switch ($request['special_women_condition']) {
            case 'pregnancy':
                $request['special_women_condition'] = 'Pregnancy';
                break;
            case 'menstruation':
                $request['special_women_condition'] = 'Menstruation';
                break;
            case 'miscarriage':
                $request['special_women_condition'] = 'Miscarriage';
                break;
            case 'other':
                $request['special_women_condition'] = 'Other';
                break;
            default:
                $request['special_women_condition'] = ucfirst(str_replace('_', ' ', $request['special_women_condition']));
                break;
        }
    }
    
    // Format study type for display
    if ($request['study_type']) {
        switch ($request['study_type']) {
            case 'conference':
                $request['study_type'] = 'Conference';
                break;
            case 'training':
                $request['study_type'] = 'Training';
                break;
            case 'seminar':
                $request['study_type'] = 'Seminar';
                break;
            case 'course':
                $request['study_type'] = 'Course';
                break;
            case 'exam':
                $request['study_type'] = 'Exam';
                break;
            default:
                $request['study_type'] = ucfirst(str_replace('_', ' ', $request['study_type']));
                break;
        }
    }
    
    // Format approval timestamps
    if ($request['dept_head_approved_at']) {
        $request['dept_head_approved_at'] = date('M d, Y \a\t g:i A', strtotime($request['dept_head_approved_at']));
    }
    
    echo json_encode([
        'success' => true,
        'leave' => $request
    ]);
    
} catch (Exception $e) {
    error_log('Error fetching leave request details: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>

