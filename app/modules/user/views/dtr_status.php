<?php
session_start();
require_once '../../../../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Unauthorized');
}

// Get today's DTR record
$today = date('Y-m-d');
$stmt = $pdo->prepare("SELECT * FROM dtr WHERE user_id = ? AND date = ?");
$stmt->execute([$_SESSION['user_id'], $today]);
$today_record = $stmt->fetch();

// Prepare response data
$response = [
    'hasRecord' => false,
    'morning_time_in' => null,
    'morning_time_out' => null,
    'afternoon_time_in' => null,
    'afternoon_time_out' => null,
    'total_hours' => 0,
    'current_status' => 'no_record'
];

if ($today_record) {
    $response['hasRecord'] = true;
    
    if ($today_record['morning_time_in']) {
        $response['morning_time_in'] = date('h:i A', strtotime($today_record['morning_time_in']));
    }
    
    if ($today_record['morning_time_out']) {
        $response['morning_time_out'] = date('h:i A', strtotime($today_record['morning_time_out']));
    }
    
    if ($today_record['afternoon_time_in']) {
        $response['afternoon_time_in'] = date('h:i A', strtotime($today_record['afternoon_time_in']));
    }
    
    if ($today_record['afternoon_time_out']) {
        $response['afternoon_time_out'] = date('h:i A', strtotime($today_record['afternoon_time_out']));
    }
    
    // Calculate total hours worked
    $total_hours_worked = 0;
    if ($today_record['morning_time_in'] && $today_record['morning_time_out']) {
        $morning_in = strtotime($today_record['morning_time_in']);
        $morning_out = strtotime($today_record['morning_time_out']);
        $total_hours_worked += ($morning_out - $morning_in) / 3600;
    }
    if ($today_record['afternoon_time_in'] && $today_record['afternoon_time_out']) {
        $afternoon_in = strtotime($today_record['afternoon_time_in']);
        $afternoon_out = strtotime($today_record['afternoon_time_out']);
        $total_hours_worked += ($afternoon_out - $afternoon_in) / 3600;
    }
    
    $response['total_hours'] = round($total_hours_worked, 2);
    
    // Determine current status
    if (!$today_record['morning_time_in']) {
        $response['current_status'] = 'ready_to_time_in';
    } else if ($today_record['morning_time_in'] && !$today_record['morning_time_out']) {
        $response['current_status'] = 'timed_in_morning';
    } else if ($today_record['morning_time_in'] && $today_record['morning_time_out'] && !$today_record['afternoon_time_in']) {
        $response['current_status'] = 'ready_afternoon_time_in';
    } else if ($today_record['afternoon_time_in'] && !$today_record['afternoon_time_out']) {
        $response['current_status'] = 'timed_in_afternoon';
    } else if ($today_record['afternoon_time_out']) {
        $response['current_status'] = 'completed';
    }
}

// Set JSON header and return response
header('Content-Type: application/json');
echo json_encode($response);
?> 