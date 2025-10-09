<?php
/**
 * Automated Alert Generation API
 * Generates and sends automated leave maximization alerts
 */

session_start();
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/app/core/services/EnhancedLeaveAlertService.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['error' => 'Not authorized']);
    exit();
}

try {
    $alertService = new EnhancedLeaveAlertService($pdo);
    
    // Generate comprehensive alerts
    $alerts = $alertService->generateComprehensiveAlerts();
    
    $sentAlerts = 0;
    $errors = [];
    
    // Send automated alerts for urgent and critical cases
    foreach ($alerts as $employeeId => $data) {
        if ($data['priority'] === 'urgent' || $data['priority'] === 'critical') {
            foreach ($data['alerts'] as $alert) {
                if ($alert['severity'] === 'urgent' || $alert['severity'] === 'critical') {
                    $alertId = $alertService->sendAutomatedAlert(
                        $employeeId,
                        $alert['type'],
                        $alert['message'],
                        $alert['severity']
                    );
                    
                    if ($alertId) {
                        $sentAlerts++;
                    } else {
                        $errors[] = "Failed to send alert for employee ID: $employeeId";
                    }
                }
            }
        }
    }
    
    // Get updated statistics
    $stats = $alertService->getAlertStatistics();
    
    echo json_encode([
        'success' => true,
        'message' => "Generated $sentAlerts automated alerts",
        'sent_alerts' => $sentAlerts,
        'errors' => $errors,
        'statistics' => $stats,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error generating alerts: ' . $e->getMessage()
    ]);
}
?>
