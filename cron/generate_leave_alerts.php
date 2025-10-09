<?php
/**
 * Automated Leave Alert Generation Cron Job
 * Runs daily to generate and send leave maximization alerts
 * 
 * Usage: php cron/generate_leave_alerts.php
 * Recommended schedule: Daily at 9:00 AM
 */

// Set timezone
date_default_timezone_set('Asia/Manila');

// Include required files
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/app/core/services/EnhancedLeaveAlertService.php';

// Log function
function logMessage($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logFile = dirname(__DIR__) . '/logs/leave_alerts.log';
    $logEntry = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    echo $logEntry;
}

try {
    logMessage("Starting automated leave alert generation...");
    
    $alertService = new EnhancedLeaveAlertService($pdo);
    
    // Generate comprehensive alerts
    $alerts = $alertService->generateComprehensiveAlerts();
    $totalEmployees = count($alerts);
    
    logMessage("Found $totalEmployees employees with alert conditions");
    
    $sentAlerts = 0;
    $urgentAlerts = 0;
    $criticalAlerts = 0;
    $cscComplianceAlerts = 0;
    $errors = [];
    
    // Process each employee's alerts
    foreach ($alerts as $employeeId => $data) {
        $employee = $data['employee'];
        $priority = $data['priority'];
        $alertsList = $data['alerts'];
        $cscCompliance = $data['csc_compliance'];
        
        logMessage("Processing alerts for {$employee['name']} (Priority: $priority)");
        
        // Send alerts based on priority and severity
        foreach ($alertsList as $alert) {
            $shouldSend = false;
            
            // Send urgent and critical alerts immediately
            if ($alert['severity'] === 'urgent' || $alert['severity'] === 'critical') {
                $shouldSend = true;
            }
            
            // Send year-end alerts if within 60 days
            if ($alert['type'] === 'year_end_urgent' || $alert['type'] === 'sil_forfeiture_risk') {
                $shouldSend = true;
            }
            
            if ($shouldSend) {
                $alertId = $alertService->sendAutomatedAlert(
                    $employeeId,
                    $alert['type'],
                    $alert['message'],
                    $alert['severity']
                );
                
                if ($alertId) {
                    $sentAlerts++;
                    
                    if ($alert['severity'] === 'urgent') $urgentAlerts++;
                    if ($alert['severity'] === 'critical') $criticalAlerts++;
                    if ($alert['type'] === 'csc_utilization_low') $cscComplianceAlerts++;
                    
                    logMessage("Sent alert to {$employee['name']}: {$alert['message']}");
                } else {
                    $errors[] = "Failed to send alert for {$employee['name']}";
                    logMessage("ERROR: Failed to send alert for {$employee['name']}");
                }
            }
        }
    }
    
    // Get final statistics
    $stats = $alertService->getAlertStatistics();
    
    // Log summary
    logMessage("Alert generation completed successfully!");
    logMessage("Summary:");
    logMessage("- Total employees processed: $totalEmployees");
    logMessage("- Total alerts sent: $sentAlerts");
    logMessage("- Urgent alerts: $urgentAlerts");
    logMessage("- Critical alerts: $criticalAlerts");
    logMessage("- CSC compliance alerts: $cscComplianceAlerts");
    logMessage("- Errors: " . count($errors));
    
    if (!empty($errors)) {
        logMessage("Error details:");
        foreach ($errors as $error) {
            logMessage("- $error");
        }
    }
    
    // Send summary email to admin (optional)
    if ($sentAlerts > 0) {
        $adminEmail = 'admin@elms.com'; // Configure this
        $subject = "Daily Leave Alert Summary - " . date('Y-m-d');
        $message = "
        <h2>Daily Leave Alert Summary</h2>
        <p><strong>Date:</strong> " . date('Y-m-d H:i:s') . "</p>
        <p><strong>Total Alerts Sent:</strong> $sentAlerts</p>
        <p><strong>Urgent Alerts:</strong> $urgentAlerts</p>
        <p><strong>Critical Alerts:</strong> $criticalAlerts</p>
        <p><strong>CSC Compliance Alerts:</strong> $cscComplianceAlerts</p>
        <p><strong>Employees Processed:</strong> $totalEmployees</p>
        ";
        
        // Uncomment to enable email notifications
        // mail($adminEmail, $subject, $message, "Content-Type: text/html");
    }
    
    logMessage("Automated alert generation completed successfully!");
    
} catch (Exception $e) {
    $errorMessage = "FATAL ERROR: " . $e->getMessage();
    logMessage($errorMessage);
    echo $errorMessage;
    exit(1);
}
?>
