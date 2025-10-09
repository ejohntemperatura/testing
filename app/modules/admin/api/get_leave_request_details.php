<?php
session_start();
require_once __DIR__ . '/../../../../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid leave request ID']);
    exit();
}

$leave_id = (int)$_GET['id'];

try {
    // First, check what columns actually exist in the leave_requests table
    $columns_stmt = $pdo->query("DESCRIBE leave_requests");
    $existing_columns = $columns_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Build dynamic query based on existing columns
    $select_fields = ["lr.*"];
    $join_conditions = [];
    
    // Add employee fields
    $select_fields[] = "e.name as employee_name";
    $select_fields[] = "e.position";
    $select_fields[] = "e.department";
    $select_fields[] = "e.email as employee_email";
    
    // Check if contact_number exists in employees table
    try {
        $emp_columns_stmt = $pdo->query("DESCRIBE employees");
        $emp_columns = $emp_columns_stmt->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('contact_number', $emp_columns)) {
            $select_fields[] = "e.contact_number as employee_contact";
        }
    } catch (Exception $e) {
        // Ignore if can't check employee columns
    }
    
    // Add approval fields if they exist
    if (in_array('dept_head_approved_by', $existing_columns)) {
        $select_fields[] = "dept_approver.name as dept_head_name";
        $join_conditions[] = "LEFT JOIN employees dept_approver ON lr.dept_head_approved_by = dept_approver.id";
    }
    
    if (in_array('director_approved_by', $existing_columns)) {
        $select_fields[] = "director_approver.name as director_name";
        $join_conditions[] = "LEFT JOIN employees director_approver ON lr.director_approved_by = director_approver.id";
    }
    
    if (in_array('admin_approved_by', $existing_columns)) {
        $select_fields[] = "admin_approver.name as admin_name";
        $join_conditions[] = "LEFT JOIN employees admin_approver ON lr.admin_approved_by = admin_approver.id";
    }
    
    // Build the final query
    $query = "SELECT " . implode(", ", $select_fields) . " 
              FROM leave_requests lr 
              JOIN employees e ON lr.employee_id = e.id";
    
    if (!empty($join_conditions)) {
        $query .= " " . implode(" ", $join_conditions);
    }
    
    $query .= " WHERE lr.id = ?";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$leave_id]);
    $leave_request = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$leave_request) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Leave request not found']);
        exit();
    }
    
    // Calculate days requested if not already set
    if (!$leave_request['days_requested']) {
        $start = new DateTime($leave_request['start_date']);
        $end = new DateTime($leave_request['end_date']);
        $days = $start->diff($end)->days + 1;
        $leave_request['days_requested'] = $days;
    }
    
    // Format location type for display
    if ($leave_request['location_type']) {
        switch ($leave_request['location_type']) {
            case 'within_philippines':
                $leave_request['location_type'] = 'Within Philippines';
                break;
            case 'outside_philippines':
                $leave_request['location_type'] = 'Outside Philippines';
                break;
            default:
                $leave_request['location_type'] = ucfirst(str_replace('_', ' ', $leave_request['location_type']));
                break;
        }
    }
    
    // Format medical condition for display
    if ($leave_request['medical_condition']) {
        switch ($leave_request['medical_condition']) {
            case 'minor':
                $leave_request['medical_condition'] = 'Minor';
                break;
            case 'serious':
                $leave_request['medical_condition'] = 'Serious';
                break;
            case 'chronic':
                $leave_request['medical_condition'] = 'Chronic';
                break;
            default:
                $leave_request['medical_condition'] = ucfirst(str_replace('_', ' ', $leave_request['medical_condition']));
                break;
        }
    }
    
    // Format special women condition for display
    if ($leave_request['special_women_condition']) {
        switch ($leave_request['special_women_condition']) {
            case 'pregnancy':
                $leave_request['special_women_condition'] = 'Pregnancy';
                break;
            case 'menstruation':
                $leave_request['special_women_condition'] = 'Menstruation';
                break;
            case 'miscarriage':
                $leave_request['special_women_condition'] = 'Miscarriage';
                break;
            case 'other':
                $leave_request['special_women_condition'] = 'Other';
                break;
            default:
                $leave_request['special_women_condition'] = ucfirst(str_replace('_', ' ', $leave_request['special_women_condition']));
                break;
        }
    }
    
    // Format study type for display
    if ($leave_request['study_type']) {
        switch ($leave_request['study_type']) {
            case 'conference':
                $leave_request['study_type'] = 'Conference';
                break;
            case 'training':
                $leave_request['study_type'] = 'Training';
                break;
            case 'seminar':
                $leave_request['study_type'] = 'Seminar';
                break;
            case 'course':
                $leave_request['study_type'] = 'Course';
                break;
            case 'exam':
                $leave_request['study_type'] = 'Exam';
                break;
            default:
                $leave_request['study_type'] = ucfirst(str_replace('_', ' ', $leave_request['study_type']));
                break;
        }
    }
    
    echo json_encode([
        'success' => true,
        'leave_request' => $leave_request
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("API Error in get_leave_request_details.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching leave request details: ' . $e->getMessage(),
        'error_details' => $e->getMessage()
    ]);
}
?>

