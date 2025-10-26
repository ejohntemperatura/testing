<?php
// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');

session_start();
require_once '../../config/database.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

// Get user's recent alerts
$stmt = $pdo->prepare("
    SELECT la.*, e.name as sent_by_name 
    FROM leave_alerts la 
    LEFT JOIN employees e ON la.sent_by = e.id 
    WHERE la.employee_id = ? 
    ORDER BY la.created_at DESC 
    LIMIT 10
");
$stmt->execute([$_SESSION['user_id']]);
$alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Format alerts for display
$formatted_alerts = [];
foreach ($alerts as $alert) {
    $formatted_alerts[] = [
        'id' => $alert['id'],
        'alert_type' => $alert['alert_type'],
        'message' => $alert['message'],
        'sent_by_name' => $alert['sent_by_name'],
        'created_at' => $alert['created_at'],
        'formatted_date' => date('M d, Y \a\t h:i A', strtotime($alert['created_at'])),
        'alert_title' => getAlertTitle($alert['alert_type'])
    ];
}

function getAlertTitle($alert_type) {
    switch($alert_type) {
        case 'low_utilization':
            return 'Low Leave Utilization Alert';
        case 'year_end_reminder':
            return 'Year-End Leave Reminder';
        case 'custom':
            return 'Custom Alert';
        default:
            return 'Leave Alert';
    }
}

echo json_encode([
    'success' => true,
    'alerts' => $formatted_alerts,
    'count' => count($formatted_alerts)
]);
?>
