<?php
session_start();
require_once '../../../../config/database.php';
require_once '../../../../app/core/services/LeaveCreditsManager.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

try {
    $leave_type = $_POST['leave_type'] ?? '';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $days = (int)($_POST['days'] ?? 0);
    
    if (empty($leave_type) || empty($start_date) || empty($end_date) || $days <= 0) {
        echo json_encode(['error' => 'Invalid parameters']);
        exit();
    }
    
    $creditsManager = new LeaveCreditsManager($pdo);
    $result = $creditsManager->checkLeaveCredits($_SESSION['user_id'], $leave_type, $start_date, $end_date);
    
    echo json_encode($result);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
