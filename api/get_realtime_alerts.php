<?php
// Suppress notices and warnings to prevent JSON corruption
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/config/database.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not authorized']);
    exit();
}

try {
    // Get user's recent alerts (last 20 for real-time updates)
    $stmt = $pdo->prepare("
        SELECT la.*, e.name as sent_by_name, e.position as sent_by_position
        FROM leave_alerts la 
        LEFT JOIN employees e ON la.sent_by = e.id 
        WHERE la.employee_id = ? 
        ORDER BY la.created_at DESC 
        LIMIT 20
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get unread count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as unread_count
        FROM leave_alerts 
        WHERE employee_id = ? 
        AND is_read = 0
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $unread_count = $stmt->fetch()['unread_count'];
    
    // Get new alerts count (alerts from last 5 minutes)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as new_count
        FROM leave_alerts 
        WHERE employee_id = ? 
        AND created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $new_count = $stmt->fetch()['new_count'];
    
    // Format alerts for better display
    foreach ($alerts as &$alert) {
        $alert['formatted_date'] = date('M j, Y g:i A', strtotime($alert['created_at']));
        $alert['time_ago'] = getTimeAgo($alert['created_at']);
        $alert['alert_icon'] = getAlertIcon($alert['alert_type']);
        $alert['alert_color'] = getAlertColor($alert['alert_type']);
    }
    
    echo json_encode([
        'success' => true,
        'alerts' => $alerts,
        'unread_count' => $unread_count,
        'new_count' => $new_count,
        'timestamp' => time(),
        'user_id' => $_SESSION['user_id']
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error fetching alerts: ' . $e->getMessage()
    ]);
}

function getTimeAgo($datetime) {
    // Create DateTime objects for proper timezone handling
    $now = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $past = new DateTime($datetime, new DateTimeZone('Asia/Manila'));
    
    // Calculate difference in seconds
    $diff = $now->getTimestamp() - $past->getTimestamp();
    
    if ($diff < 0) $diff = 0; // Handle future dates
    
    if ($diff < 60) return 'just now';
    if ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' minute' . ($minutes != 1 ? 's' : '') . ' ago';
    }
    if ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours != 1 ? 's' : '') . ' ago';
    }
    if ($diff < 2592000) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days != 1 ? 's' : '') . ' ago';
    }
    
    return $past->format('M j, Y');
}

function getAlertIcon($alertType) {
    $icons = [
        'urgent_year_end' => 'fas fa-exclamation-triangle',
        'csc_utilization_low' => 'fas fa-chart-line',
        'critical_utilization' => 'fas fa-exclamation-circle',
        'csc_limit_exceeded' => 'fas fa-ban',
        'csc_limit_approaching' => 'fas fa-exclamation-triangle',
        'year_end_critical' => 'fas fa-fire',
        'year_end_warning' => 'fas fa-exclamation-triangle',
        'moderate_reminder' => 'fas fa-clock',
        'planning_reminder' => 'fas fa-calendar',
        'csc_compliance' => 'fas fa-file-contract',
        'wellness_focus' => 'fas fa-heart',
        'custom' => 'fas fa-bell'
    ];
    return $icons[$alertType] ?? 'fas fa-bell';
}

function getAlertColor($alertType) {
    $colors = [
        'urgent_year_end' => 'text-red-400 bg-red-500/20 border-red-500/30',
        'csc_utilization_low' => 'text-orange-400 bg-orange-500/20 border-orange-500/30',
        'critical_utilization' => 'text-red-400 bg-red-500/20 border-red-500/30',
        'csc_limit_exceeded' => 'text-red-400 bg-red-500/20 border-red-500/30',
        'csc_limit_approaching' => 'text-orange-400 bg-orange-500/20 border-orange-500/30',
        'year_end_critical' => 'text-red-400 bg-red-500/20 border-red-500/30',
        'year_end_warning' => 'text-orange-400 bg-orange-500/20 border-orange-500/30',
        'moderate_reminder' => 'text-yellow-400 bg-yellow-500/20 border-yellow-500/30',
        'planning_reminder' => 'text-blue-400 bg-blue-500/20 border-blue-500/30',
        'csc_compliance' => 'text-purple-400 bg-purple-500/20 border-purple-500/30',
        'wellness_focus' => 'text-green-400 bg-green-500/20 border-green-500/30',
        'custom' => 'text-slate-400 bg-slate-500/20 border-slate-500/30'
    ];
    return $colors[$alertType] ?? 'text-slate-400 bg-slate-500/20 border-slate-500/30';
}
?>
