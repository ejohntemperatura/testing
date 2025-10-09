<?php
session_start();
require_once '../../../../config/database.php';
require_once '../../../../app/core/services/EnhancedLeaveAlertService.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../../auth/views/login.php');
    exit();
}

// Get admin information
$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ? AND role = 'admin'");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch();

// Create leave_alerts table if it doesn't exist
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS leave_alerts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            alert_type VARCHAR(50) NOT NULL,
            message TEXT NOT NULL,
            sent_by INT NOT NULL,
            priority ENUM('low', 'moderate', 'critical', 'urgent') DEFAULT 'moderate',
            is_read TINYINT(1) DEFAULT 0,
            read_at TIMESTAMP NULL,
                alert_category ENUM('utilization', 'year_end', 'csc_compliance', 'wellness', 'custom') DEFAULT 'utilization',
            metadata JSON NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(id),
            FOREIGN KEY (sent_by) REFERENCES employees(id)
        )
    ");
} catch (Exception $e) {
    // Table might already exist, ignore error
}

// Initialize enhanced alert service
$alertService = new EnhancedLeaveAlertService($pdo);

// Handle alert sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'send_alert') {
        $employee_id = $_POST['employee_id'] ?? '';
        $alert_type = $_POST['alert_type'] ?? '';
        $message = $_POST['message'] ?? '';
        
        // Validate required fields to prevent empty submissions
        if (empty($employee_id) || empty($alert_type) || empty($message)) {
            $_SESSION['error'] = "Please fill in all required fields before sending the alert.";
            error_log("Alert validation failed: empty fields detected");
        } else {
        // Debug: Log the received data
        error_log("Alert data received: employee_id=$employee_id, alert_type=$alert_type, message=" . substr($message, 0, 50) . "...");
        
        try {
            // Insert alert into database with is_read = 0 to ensure it triggers floating notification
            $stmt = $pdo->prepare("
                INSERT INTO leave_alerts (employee_id, alert_type, message, sent_by, is_read, created_at) 
                VALUES (?, ?, ?, ?, 0, NOW())
            ");
            $stmt->execute([$employee_id, $alert_type, $message, $_SESSION['user_id']]);
            
            $_SESSION['success'] = "Leave maximization alert sent successfully!";
            error_log("Alert sent successfully for employee ID: $employee_id");
        } catch (Exception $e) {
            $_SESSION['error'] = "Error sending alert: " . $e->getMessage();
            error_log("Error sending alert: " . $e->getMessage());
            }
        }
    }
}

// Get total user count first
$userCountStmt = $pdo->prepare("SELECT COUNT(*) as total_users FROM employees WHERE role = 'employee'");
$userCountStmt->execute();
$totalUsers = $userCountStmt->fetchColumn();

// Get enhanced alert data using the new service
$alertData = $alertService->getUrgentAlerts(50);
$alertStats = $alertService->getAlertStatistics();

// Process alert data for display
$employees = [];
foreach ($alertData as $employeeId => $data) {
    $employee = $data['employee'];
    $alerts = $data['alerts'];
    $priority = $data['priority'];
    $cscCompliance = $data['csc_compliance'];
    
    // Calculate overall statistics
    $totalAllocated = 0;
    $totalUsed = 0;
    $totalRemaining = 0;
    
    require_once '../../../../config/leave_types.php';
    $leaveTypesConfig = getLeaveTypes();
    foreach ($leaveTypesConfig as $type => $config) {
        if (!$config['requires_credits']) continue;
        
        $balanceField = $config['credit_field'];
        $usedField = $type . '_used';
        
        $allocated = $employee[$balanceField] ?? 0;
        $used = $employee[$usedField] ?? 0;
        $remaining = max(0, $allocated - $used);
        
        $totalAllocated += $allocated;
        $totalUsed += $used;
        $totalRemaining += $remaining;
        
        $employee[$type . '_remaining'] = $remaining;
        $employee[$type . '_utilization'] = $allocated > 0 ? round(($used / $allocated) * 100, 1) : 0;
    }
    
    $employee['total_allocated'] = $totalAllocated;
    $employee['total_used'] = $totalUsed;
    $employee['total_remaining'] = $totalRemaining;
    $employee['overall_utilization'] = $totalAllocated > 0 ? round(($totalUsed / $totalAllocated) * 100, 1) : 0;
    $employee['priority'] = $priority;
    $employee['alerts'] = $alerts;
    $employee['csc_compliance'] = $cscCompliance;
    
    // Calculate days remaining in year
    $currentDate = new DateTime();
    $yearEnd = new DateTime(date('Y') . '-12-31');
    $daysRemaining = $currentDate->diff($yearEnd)->days;
    $employee['days_remaining_in_year'] = $daysRemaining;
    
    // Generate urgency message based on priority
    switch ($priority) {
        case 'urgent':
            $employee['urgency_level'] = 'high';
            $employee['urgency_message'] = '🚨 URGENT: Immediate action required!';
            break;
        case 'critical':
            $employee['urgency_level'] = 'high';
            $employee['urgency_message'] = '⚠️ CRITICAL: High priority attention needed!';
            break;
        case 'moderate':
            $employee['urgency_level'] = 'medium';
            $employee['urgency_message'] = '📋 MODERATE: Planning and coordination needed.';
            break;
        default:
            $employee['urgency_level'] = 'low';
            $employee['urgency_message'] = '📅 PLANNING: Consider leave utilization.';
    }
    
    $employees[] = $employee;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- OFFLINE Tailwind CSS - No internet required! -->
    <link rel="stylesheet" href="../../../../assets/css/tailwind.css">
        <!-- Font Awesome Local - No internet required! -->
    <link rel="stylesheet" href="../../../../assets/libs/fontawesome/css/all.min.css">
        <!-- Font Awesome Local - No internet required! -->
        
        <link rel="stylesheet" href="../../../../assets/css/style.css">
        <link rel="stylesheet" href="../../../../assets/css/admin_style.css">
        <link rel="stylesheet" href="../../../../assets/css/dark-theme.css">

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ELMS - Leave Maximization Alerts</title>
    
    <script>
    </script>
    
    <style>
        /* Custom scrollbar styling for modals */
        .modal-scroll::-webkit-scrollbar {
            width: 6px;
        }
        
        .modal-scroll::-webkit-scrollbar-track {
            background: rgba(51, 65, 85, 0.3);
            border-radius: 3px;
        }
        
        .modal-scroll::-webkit-scrollbar-thumb {
            background: rgba(148, 163, 184, 0.5);
            border-radius: 3px;
        }
        
        .modal-scroll::-webkit-scrollbar-thumb:hover {
            background: rgba(148, 163, 184, 0.7);
        }
        
        /* Smooth scrolling */
        .modal-scroll {
            scroll-behavior: smooth;
        }
    </style>
    <style>
        .alert-card {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border: 1px solid #ffc107;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .utilization-bar {
            height: 8px;
            border-radius: 4px;
            background: #e9ecef;
            overflow: hidden;
        }
        .utilization-fill {
            height: 100%;
            transition: width 0.3s ease;
        }
        .low-utilization {
            background: #dc3545;
        }
        .medium-utilization {
            background: #ffc107;
        }
        .high-utilization {
            background: #28a745;
        }
        .employee-card {
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }
        .employee-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .needs-alert {
            border-left: 4px solid #ffc107;
            background: #fff9e6;
        }
    </style>
</head>
<body class="bg-slate-900 text-white" data-user-role="admin">
    <?php include '../../../../includes/unified_navbar.php'; ?>

    <div class="flex">
        <!-- Left Sidebar -->
        <aside id="sidebar" class="fixed left-0 top-16 h-screen w-64 bg-slate-900 border-r border-slate-800 overflow-y-auto z-40 transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out">
            <nav class="p-4 space-y-2">
                <!-- Active Navigation Item -->
                <a href="dashboard.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                    <i class="fas fa-tachometer-alt w-5"></i>
                    <span>Dashboard</span>
                </a>
                
                <!-- Section Headers -->
                <div class="space-y-1">
                    <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider px-4 py-2">Management</h3>
                    
                    <!-- Navigation Items -->
                    <a href="manage_user.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                        <i class="fas fa-users-cog w-5"></i>
                        <span>Manage Users</span>
                    </a>
                    
                    <a href="leave_management.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                        <i class="fas fa-calendar-check w-5"></i>
                        <span>Leave Management</span>
                        <span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full" id="pendingLeaveBadge" style="display: none;">0</span>
                    </a>
                    
                    <a href="leave_alerts.php" class="flex items-center space-x-3 px-4 py-3 text-white bg-blue-500/20 rounded-lg border border-blue-500/30">
                        <i class="fas fa-bell w-5"></i>
                        <span>Leave Alerts</span>
                    </a>
                </div>
                
                <div class="space-y-1">
                    <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider px-4 py-2">Reports</h3>
                    
                    <a href="calendar.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                        <i class="fas fa-calendar w-5"></i>
                        <span>Leave Chart</span>
                    </a>
                    
                    <a href="reports.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                        <i class="fas fa-file-alt w-5"></i>
                        <span>Reports</span>
                    </a>
                </div>
                
            </nav>
        </aside>
        
        <!-- Main Content -->
        <main class="flex-1 ml-64 p-6 pt-24">
            <div class="max-w-7xl mx-auto">
                <!-- Page Header -->
                <div class="mb-8">
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 bg-gradient-to-r from-primary to-accent rounded-2xl flex items-center justify-center">
                            <i class="fas fa-bell text-2xl text-white"></i>
                        </div>
                        <div>
                            <h1 class="text-3xl font-bold text-white mb-2">Leave Maximization Alerts</h1>
                            <p class="text-slate-400">Monitor and send alerts to employees with low leave utilization</p>
                        </div>
                    </div>
                </div>


                <!-- Enhanced Alert Statistics Dashboard -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <!-- Total Alerts Card -->
                    <div class="bg-slate-800 rounded-2xl border border-slate-700 p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <p class="text-slate-400 text-sm font-semibold uppercase tracking-wider">Total Alerts</p>
                                <h2 class="text-3xl font-bold text-white mt-2"><?php echo $alertStats['total_employees_with_alerts']; ?></h2>
                            </div>
                            <div class="w-12 h-12 bg-blue-500/20 rounded-lg flex items-center justify-center">
                                <i class="fas fa-bell text-blue-400 text-xl"></i>
                            </div>
                        </div>
                        <div class="flex items-center gap-1 text-blue-400 text-sm font-medium">
                            <i class="fas fa-users"></i>
                            <span>Employees needing attention</span>
                        </div>
                    </div>
                    
                    <!-- Urgent Alerts Card -->
                    <div class="bg-slate-800 rounded-2xl border border-slate-700 p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <p class="text-slate-400 text-sm font-semibold uppercase tracking-wider">Urgent Alerts</p>
                                <h2 class="text-3xl font-bold text-red-400 mt-2"><?php echo $alertStats['urgent_alerts']; ?></h2>
                            </div>
                            <div class="w-12 h-12 bg-red-500/20 rounded-lg flex items-center justify-center">
                                <i class="fas fa-exclamation-triangle text-red-400 text-xl"></i>
                            </div>
                        </div>
                        <div class="flex items-center gap-1 text-red-400 text-sm font-medium">
                            <i class="fas fa-clock"></i>
                            <span>Immediate action required</span>
                        </div>
                    </div>
                    
                    <!-- CSC Compliance Card -->
                    <div class="bg-slate-800 rounded-2xl border border-slate-700 p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <p class="text-slate-400 text-sm font-semibold uppercase tracking-wider">CSC Issues</p>
                                <h2 class="text-3xl font-bold text-orange-400 mt-2"><?php echo $alertStats['csc_compliance_issues'] ?? 0; ?></h2>
                            </div>
                            <div class="w-12 h-12 bg-orange-500/20 rounded-lg flex items-center justify-center">
                                <i class="fas fa-chart-line text-orange-400 text-xl"></i>
                            </div>
                        </div>
                        <div class="flex items-center gap-1 text-orange-400 text-sm font-medium">
                            <i class="fas fa-balance-scale"></i>
                            <span>CSC compliance concerns</span>
                        </div>
                    </div>
                    
                    <!-- Year-End Risks Card -->
                    <div class="bg-slate-800 rounded-2xl border border-slate-700 p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <p class="text-slate-400 text-sm font-semibold uppercase tracking-wider">Year-End Risks</p>
                                <h2 class="text-3xl font-bold text-yellow-400 mt-2"><?php echo $alertStats['year_end_risks']; ?></h2>
                            </div>
                            <div class="w-12 h-12 bg-yellow-500/20 rounded-lg flex items-center justify-center">
                                <i class="fas fa-calendar-times text-yellow-400 text-xl"></i>
                            </div>
                        </div>
                        <div class="flex items-center gap-1 text-yellow-400 text-sm font-medium">
                            <i class="fas fa-hourglass-end"></i>
                            <span>Forfeiture risks</span>
                        </div>
                    </div>
                </div>

                <!-- Enhanced Alert Info -->
                <div class="bg-gradient-to-r from-blue-500/20 to-purple-500/20 border border-blue-500/30 rounded-2xl p-6 mb-8">
                    <div class="flex items-center mb-4">
                        <i class="fas fa-info-circle text-blue-400 text-2xl mr-4"></i>
                        <div>
                            <h3 class="text-xl font-semibold text-white mb-2">Enhanced Leave Maximization System</h3>
                            <p class="text-slate-300">Advanced alert system with CSC compliance tracking, year-end forfeiture warnings, and intelligent prioritization based on Civil Service Commission guidelines.</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-check-circle text-green-400"></i>
                            <span class="text-slate-300 text-sm">CSC Compliance Monitoring</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-check-circle text-green-400"></i>
                            <span class="text-slate-300 text-sm">Intelligent Prioritization</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-check-circle text-green-400"></i>
                            <span class="text-slate-300 text-sm">Year-End Forfeiture Alerts</span>
                        </div>
                    </div>
                </div>


                <!-- Success Message -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="bg-green-500/20 border border-green-500/30 text-green-400 p-4 rounded-xl mb-6 flex items-center">
                        <i class="fas fa-check-circle mr-3"></i>
                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>

                <!-- Error Message -->
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="bg-red-500/20 border border-red-500/30 text-red-400 p-4 rounded-xl mb-6 flex items-center">
                        <i class="fas fa-exclamation-circle mr-3"></i>
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <!-- Employee List -->
                <?php if (empty($employees)): ?>
                    <!-- No Alerts Card -->
                    <div class="flex flex-col items-center justify-center py-16">
                        <div class="bg-slate-800 rounded-2xl border border-slate-700 p-12 text-center max-w-md mx-auto">
                            <div class="w-20 h-20 bg-green-500/20 rounded-full flex items-center justify-center mx-auto mb-6">
                                <i class="fas fa-check-circle text-green-400 text-3xl"></i>
                            </div>
                            <h3 class="text-2xl font-bold text-white mb-4">Excellent Leave Utilization!</h3>
                            <p class="text-slate-400 mb-6">
                                All employees are effectively utilizing their leave credits. No alerts are needed at this time.
                            </p>
                            <div class="bg-green-500/10 border border-green-500/30 rounded-lg p-4 mb-6">
                                <div class="flex items-center justify-center mb-2">
                                    <i class="fas fa-chart-line text-green-400 mr-2"></i>
                                    <span class="text-green-400 font-semibold">Leave Maximization Status</span>
                                </div>
                                <p class="text-sm text-slate-300">
                                    Your organization is successfully maximizing leave utilization across all employees.
                                </p>
                            </div>
                            <div class="flex flex-col sm:flex-row gap-3">
                                <button onclick="window.location.reload()" class="px-6 py-3 bg-primary hover:bg-primary/80 text-white rounded-xl transition-colors flex items-center justify-center">
                                    <i class="fas fa-sync-alt mr-2"></i>
                                    Refresh Data
                                </button>
                                <button onclick="window.location.href='leave_management.php'" class="px-6 py-3 bg-slate-700 hover:bg-slate-600 text-white rounded-xl transition-colors flex items-center justify-center">
                                    <i class="fas fa-calendar-check mr-2"></i>
                                    View Leave Management
                                </button>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
                        <?php foreach ($employees as $index => $employee): ?>
                            <div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden hover:border-slate-600/50 transition-all duration-300 <?php 
                                echo $employee['priority'] === 'urgent' ? 'ring-2 ring-red-500/50' : 
                                    ($employee['priority'] === 'critical' ? 'ring-2 ring-orange-500/50' : 
                                    ($employee['priority'] === 'moderate' ? 'ring-2 ring-yellow-500/50' : 'ring-2 ring-blue-500/50')); 
                            ?>" data-employee-id="<?php echo $employee['id']; ?>">
                            <div class="p-6">
                                    <!-- Header -->
                                <div class="flex justify-between items-start mb-4">
                                    <div>
                                        <h3 class="text-lg font-semibold text-white mb-1"><?php echo htmlspecialchars($employee['name']); ?></h3>
                                            <p class="text-slate-400 text-sm"><?php echo htmlspecialchars($employee['position']); ?> - <?php echo htmlspecialchars($employee['department']); ?></p>
                                    </div>
                                        <div class="text-right">
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium <?php 
                                                echo $employee['priority'] === 'urgent' ? 'bg-red-500/20 text-red-400 border border-red-500/30' : 
                                                    ($employee['priority'] === 'critical' ? 'bg-orange-500/20 text-orange-400 border border-orange-500/30' :
                                                    ($employee['priority'] === 'moderate' ? 'bg-yellow-500/20 text-yellow-400 border border-yellow-500/30' : 'bg-blue-500/20 text-blue-400 border border-blue-500/30')); 
                                            ?>">
                                                <i class="fas <?php 
                                                    echo $employee['priority'] === 'urgent' ? 'fa-exclamation-triangle' : 
                                                        ($employee['priority'] === 'critical' ? 'fa-exclamation-circle' :
                                                        ($employee['priority'] === 'moderate' ? 'fa-clock' : 'fa-calendar')); 
                                                ?> mr-1"></i>
                                                <?php echo strtoupper($employee['priority']); ?>
                                        </span>
                                </div>
                                    </div>

                                    <!-- Urgency Message -->
                                    <div class="mb-4 p-3 rounded-lg <?php 
                                        echo $employee['urgency_level'] === 'high' ? 'bg-red-500/10 border border-red-500/30' : 
                                            ($employee['urgency_level'] === 'medium' ? 'bg-yellow-500/10 border border-yellow-500/30' : 'bg-blue-500/10 border border-blue-500/30'); 
                                    ?>">
                                        <p class="text-sm font-semibold <?php 
                                            echo $employee['urgency_level'] === 'high' ? 'text-red-400' : 
                                                ($employee['urgency_level'] === 'medium' ? 'text-yellow-400' : 'text-blue-400'); 
                                        ?>">
                                            <?php echo $employee['urgency_message']; ?>
                                    </p>
                                </div>

                                    <!-- Overall Statistics -->
                                    <div class="mb-4 p-4 bg-slate-700/30 rounded-lg">
                                        <div class="grid grid-cols-2 gap-4 text-center">
                                            <div>
                                                <div class="text-2xl font-bold text-white"><?php echo $employee['total_remaining']; ?></div>
                                                <div class="text-xs text-slate-400">Days Remaining</div>
                                    </div>
                                            <div>
                                                <div class="text-2xl font-bold <?php 
                                                    echo $employee['overall_utilization'] < 30 ? 'text-red-400' : 
                                                        ($employee['overall_utilization'] < 70 ? 'text-yellow-400' : 'text-green-400'); 
                                                ?>"><?php echo $employee['overall_utilization']; ?>%</div>
                                                <div class="text-xs text-slate-400">Utilization</div>
                                            </div>
                                        </div>
                                        <div class="w-full bg-slate-600 rounded-full h-2 mt-3">
                                        <div class="h-2 rounded-full <?php 
                                                echo $employee['overall_utilization'] < 30 ? 'bg-red-500' : 
                                                    ($employee['overall_utilization'] < 70 ? 'bg-yellow-500' : 'bg-green-500'); 
                                            ?>" style="width: <?php echo $employee['overall_utilization']; ?>%"></div>
                                    </div>
                                </div>

                                    <!-- Key Leave Types -->
                                    <div class="space-y-3 mb-6">
                                        <?php 
                                        $keyLeaveTypes = ['vacation', 'sick', 'special_privilege'];
                                        foreach ($keyLeaveTypes as $type): 
                                            $utilization = $employee[$type . '_utilization'];
                                            $remaining = $employee[$type . '_remaining'];
                                            // Get the correct field name from leave types config
                                            $creditField = $leaveTypesConfig[$type]['credit_field'];
                                            $allocated = $employee[$creditField] ?? 0;
                                            if ($allocated > 0):
                                        ?>
                                        <div class="flex justify-between items-center">
                                            <span class="text-sm text-slate-300"><?php echo ucwords(str_replace('_', ' ', $type)); ?></span>
                                            <div class="flex items-center space-x-2">
                                                <span class="text-sm <?php 
                                                    echo $utilization < 30 ? 'text-red-400' : 
                                                        ($utilization < 70 ? 'text-yellow-400' : 'text-green-400'); 
                                                ?>"><?php echo $utilization; ?>%</span>
                                                <span class="text-xs text-slate-400">(<?php echo $remaining; ?> left)</span>
                                    </div>
                                    </div>
                                        <?php endif; endforeach; ?>
                                </div>

                                    <!-- Enhanced Alert Details -->
                                    <?php if (!empty($employee['alerts'])): ?>
                                    <div class="mb-4">
                                        <p class="text-xs text-slate-400 mb-2">Alert Details:</p>
                                        <div class="space-y-2">
                                            <?php foreach (array_slice($employee['alerts'], 0, 3) as $alert): ?>
                                            <div class="p-2 rounded-lg <?php 
                                                echo $alert['severity'] === 'urgent' ? 'bg-red-500/10 border border-red-500/30' : 
                                                    ($alert['severity'] === 'critical' ? 'bg-orange-500/10 border border-orange-500/30' : 'bg-yellow-500/10 border border-yellow-500/30'); 
                                            ?>">
                                                <div class="flex items-start space-x-2">
                                                    <i class="fas <?php 
                                                        echo $alert['type'] === 'sil_forfeiture_risk' ? 'fa-gavel' : 
                                                            ($alert['type'] === 'year_end_urgent' ? 'fa-calendar-times' : 'fa-exclamation-triangle'); 
                                                    ?> text-xs mt-0.5 <?php 
                                                        echo $alert['severity'] === 'urgent' ? 'text-red-400' : 
                                                            ($alert['severity'] === 'critical' ? 'text-orange-400' : 'text-yellow-400'); 
                                                    ?>"></i>
                                                    <div class="flex-1">
                                                        <p class="text-xs font-medium <?php 
                                                            echo $alert['severity'] === 'urgent' ? 'text-red-300' : 
                                                                ($alert['severity'] === 'critical' ? 'text-orange-300' : 'text-yellow-300'); 
                                                        ?>">
                                                            <?php echo htmlspecialchars($alert['message']); ?>
                                                        </p>
                                                        <?php if (isset($alert['leave_name'])): ?>
                                                        <p class="text-xs text-slate-400 mt-1">
                                                            <?php echo $alert['leave_name']; ?> - <?php echo $alert['utilization']; ?>% used
                                                        </p>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                            <?php if (count($employee['alerts']) > 3): ?>
                                            <div class="text-center">
                                                <span class="px-2 py-1 bg-slate-500/20 text-slate-400 text-xs rounded-full">
                                                    +<?php echo count($employee['alerts']) - 3; ?> more alerts
                                                </span>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- CSC Compliance Indicator -->
                                    <?php if ($employee['csc_compliance']): ?>
                                    <div class="mb-4 p-3 bg-orange-500/10 border border-orange-500/30 rounded-lg">
                                        <div class="flex items-center space-x-2">
                                            <i class="fas fa-gavel text-orange-400 text-sm"></i>
                                            <span class="text-orange-300 text-sm font-medium">CSC Compliance Issue</span>
                                        </div>
                                        <p class="text-orange-200 text-xs mt-1">Civil Service Commission compliance risk</p>
                                    </div>
                                    <?php endif; ?>

                <!-- Action Button -->
                                    <button class="w-full <?php 
                                        echo $employee['priority'] === 'urgent' ? 'bg-red-600 hover:bg-red-700' : 
                                            ($employee['priority'] === 'critical' ? 'bg-orange-600 hover:bg-orange-700' :
                                            ($employee['priority'] === 'moderate' ? 'bg-yellow-600 hover:bg-yellow-700' : 'bg-blue-600 hover:bg-blue-700')); 
                                    ?> text-white px-4 py-3 rounded-xl transition-colors flex items-center justify-center font-semibold" 
                                            onclick="openAlertModal(<?php echo $employee['id']; ?>, '<?php echo htmlspecialchars($employee['name']); ?>', '<?php echo $employee['priority']; ?>', <?php echo $employee['total_remaining']; ?>)">
                                        <i class="fas fa-bell mr-2"></i>
                                        Send <?php echo ucfirst($employee['priority']); ?> Priority Alert
                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Enhanced Leave Maximization Alert Modal -->
    <div id="alertModal" class="fixed inset-0 bg-black/60 backdrop-blur-md z-50 flex items-center justify-center p-4 hidden overflow-y-auto">
        <div class="bg-slate-800/95 backdrop-blur-sm rounded-2xl border border-slate-700 max-w-3xl w-full max-h-[95vh] shadow-2xl transform transition-all duration-300 scale-95 opacity-0 flex flex-col my-4" id="modalContent">
            <!-- Modal Header -->
            <div class="px-6 py-4 border-b border-slate-700 bg-gradient-to-r from-slate-800/50 to-slate-700/30 rounded-t-2xl">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-gradient-to-r from-yellow-500 to-orange-500 rounded-lg flex items-center justify-center">
                            <i class="fas fa-bell text-white text-lg"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-white">Enhanced Leave Alert System</h3>
                            <p class="text-slate-400 text-xs">Send personalized alert with CSC compliance tracking</p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-3">
                        <!-- Priority Indicator -->
                        <div id="modal_priority_indicator" class="px-3 py-1 rounded-full text-xs font-medium bg-blue-500/20 text-blue-400 border border-blue-500/30">
                            <i class="fas fa-flag mr-1"></i>
                            <span id="modal_priority_text">MODERATE PRIORITY</span>
                        </div>
                        <button type="button" class="w-8 h-8 bg-slate-700/50 hover:bg-slate-600/50 rounded-lg flex items-center justify-center text-slate-400 hover:text-white transition-all duration-200" onclick="closeAlertModal()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Modal Body -->
            <form method="POST" action="leave_alerts.php" id="alertForm" class="flex flex-col flex-1 min-h-0">
                <div class="flex-1 overflow-y-auto modal-scroll">
                    <input type="hidden" name="action" value="send_alert">
                    <input type="hidden" name="employee_id" id="modal_employee_id">
                    
                    <!-- Employee Info Card -->
                    <div class="p-6 bg-slate-700/30 rounded-xl border border-slate-600/30">
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-gradient-to-r from-primary to-accent rounded-lg flex items-center justify-center">
                                <i class="fas fa-user text-white text-lg"></i>
                            </div>
                            <div class="flex-1">
                                <h4 class="text-lg font-semibold text-white" id="modal_employee_name">Employee Name</h4>
                                <p class="text-slate-400 text-sm" id="modal_employee_dept">Department</p>
                                <div class="flex items-center space-x-3 mt-1">
                                    <span class="px-2 py-1 bg-yellow-500/20 text-yellow-400 text-xs rounded-full font-medium" id="modal_urgency_level">LOW PRIORITY</span>
                                    <span class="text-slate-400 text-xs" id="modal_utilization">Utilization: 0%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Enhanced Alert Configuration -->
                    <div class="px-6 py-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Alert Type -->
                        <div>
                            <label for="alert_type" class="block text-sm font-medium text-slate-300 mb-2">
                                <i class="fas fa-cog mr-1 text-primary text-xs"></i>Alert Type
                            </label>
                            <select name="alert_type" id="alert_type" required 
                                class="w-full bg-slate-700/50 border border-slate-600 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200">
                            <option value="">Select Alert Type</option>
                                <option value="urgent_year_end">🚨 Urgent Year-End Alert</option>
                                <option value="csc_utilization_low">📊 CSC Utilization Low</option>
                                <option value="critical_utilization">⚠️ Critical Low Utilization</option>
                                <option value="csc_limit_exceeded">🚫 CSC Limit Exceeded</option>
                                <option value="csc_limit_approaching">⚠️ CSC Limit Approaching</option>
                                <option value="year_end_critical">🔥 Year-End Critical</option>
                                <option value="year_end_warning">⚠️ Year-End Warning</option>
                                <option value="moderate_reminder">📋 Moderate Reminder</option>
                                <option value="planning_reminder">📅 Planning Reminder</option>
                                <option value="csc_compliance">📜 CSC Compliance Notice</option>
                                <option value="wellness_focus">💚 Wellness Focus</option>
                                <option value="custom">✏️ Custom Message</option>
                        </select>
                    </div>
                    
                        <!-- Priority Level -->
                        <div>
                            <label for="priority_level" class="block text-sm font-medium text-slate-300 mb-2">
                                <i class="fas fa-flag mr-1 text-primary text-xs"></i>Priority Level
                            </label>
                            <select name="priority_level" id="priority_level" 
                                class="w-full bg-slate-700/50 border border-slate-600 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200">
                                <option value="urgent">🔴 Urgent Priority</option>
                                <option value="critical">🟠 Critical Priority</option>
                                <option value="moderate">🟡 Moderate Priority</option>
                                <option value="low">🔵 Low Priority</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Alert Category -->
                    <div class="px-6 py-4">
                        <label for="alert_category" class="block text-sm font-medium text-slate-300 mb-2">
                            <i class="fas fa-tags mr-1 text-primary text-xs"></i>Alert Category
                        </label>
                        <select name="alert_category" id="alert_category" 
                            class="w-full bg-slate-700/50 border border-slate-600 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200">
                                <option value="utilization">📊 Leave Utilization</option>
                                <option value="year_end">📅 Year-End Forfeiture</option>
                                <option value="csc_compliance">🏛️ CSC Compliance</option>
                                <option value="wellness">💚 Wellness Focus</option>
                                <option value="custom">✏️ Custom Alert</option>
                        </select>
                    </div>

                    <!-- Message Template Selection -->
                    <div class="px-6 py-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">
                            <i class="fas fa-file-text mr-1 text-primary text-xs"></i>Message Template
                        </label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                            <button type="button" onclick="selectTemplate('professional')" 
                                class="p-3 bg-slate-700/30 hover:bg-slate-600/30 border border-slate-600/30 hover:border-primary/50 rounded-lg text-left transition-all duration-200 template-btn">
                                <div class="flex items-center space-x-2">
                                    <i class="fas fa-briefcase text-primary text-sm"></i>
                                    <div>
                                        <div class="text-white font-medium text-sm">Professional</div>
                                        <div class="text-slate-400 text-xs">Formal business tone</div>
                    </div>
                </div>
                            </button>
                            <button type="button" onclick="selectTemplate('friendly')" 
                                class="p-3 bg-slate-700/30 hover:bg-slate-600/30 border border-slate-600/30 hover:border-primary/50 rounded-lg text-left transition-all duration-200 template-btn">
                                <div class="flex items-center space-x-2">
                                    <i class="fas fa-heart text-primary text-sm"></i>
                                    <div>
                                        <div class="text-white font-medium text-sm">Friendly</div>
                                        <div class="text-slate-400 text-xs">Warm and supportive</div>
                                    </div>
                                </div>
                            </button>
                        </div>
                    </div>

                    <!-- Message Editor -->
                    <div class="px-6 py-4">
                        <label for="message" class="block text-sm font-medium text-slate-300 mb-2">
                            <i class="fas fa-edit mr-1 text-primary text-xs"></i>Message Content
                        </label>
                        <div class="relative">
                            <textarea name="message" id="message" rows="5" required 
                                placeholder="Enter your personalized message to the employee..."
                                class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-white text-sm placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent resize-none transition-all duration-200"></textarea>
                        </div>
                    </div>


                </div>

                <!-- Modal Footer -->
                <div class="px-6 py-4 border-t border-slate-700 bg-slate-800 rounded-b-2xl flex-shrink-0">
                    <div class="flex justify-between items-center">
                        <!-- Character Count -->
                        <div class="text-sm text-slate-400">
                            <span id="footerCharCount">0 characters</span>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="flex items-center space-x-3">
                            <button type="button" onclick="closeAlertModal()" 
                                class="px-6 py-2 bg-slate-600 hover:bg-slate-500 text-white rounded-lg transition-colors text-sm font-medium">
                                Cancel
                            </button>
                            <button type="submit" id="sendButton" 
                                class="px-6 py-2 bg-gradient-to-r from-orange-500 to-red-500 hover:from-orange-600 hover:to-red-600 text-white rounded-lg transition-all duration-200 flex items-center text-sm font-medium shadow-lg">
                                <i class="fas fa-paper-plane mr-2"></i>Send Alert
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>


    <script>
        // Toggle user dropdown
        function toggleUserDropdown() {
            const dropdown = document.getElementById('userDropdown');
            const notificationDropdown = document.getElementById('notificationDropdown');
            
            if (dropdown) {
                dropdown.classList.toggle('hidden');
                
                // Ensure dropdown is properly positioned and isolated
                if (!dropdown.classList.contains('hidden')) {
                    dropdown.style.position = 'absolute';
                    dropdown.style.zIndex = '1000';
                    dropdown.style.isolation = 'isolate';
                    
                    // Remove any misplaced elements that might have appeared
                    const misplacedInputs = dropdown.querySelectorAll('input');
                    misplacedInputs.forEach(input => {
                        input.remove();
                    });
                }
                
                // Close notification dropdown when opening user dropdown
                if (notificationDropdown) {
                    notificationDropdown.classList.add('hidden');
                }
            }
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('userDropdown');
            const button = event.target.closest('button[onclick="toggleUserDropdown()"]');
            
            if (dropdown && !dropdown.contains(event.target) && !button) {
                dropdown.classList.add('hidden');
            }
        });

        // Open alert modal with enhanced functionality
        function openAlertModal(employeeId, employeeName, priority = 'low', totalRemaining = 0) {
            console.log('Opening enhanced modal for employee:', employeeId, employeeName, priority, totalRemaining);
            
            // Validate input parameters
            if (!employeeId || !employeeName) {
                console.error('Invalid employee data provided');
                alert('Error: Invalid employee data. Please try again.');
                return;
            }
            
            // Check if elements exist
            const modal = document.getElementById('alertModal');
            const modalContent = document.getElementById('modalContent');
            const employeeIdField = document.getElementById('modal_employee_id');
            const employeeNameField = document.getElementById('modal_employee_name');
            const employeeDeptField = document.getElementById('modal_employee_dept');
            const urgencyLevelField = document.getElementById('modal_urgency_level');
            const utilizationField = document.getElementById('modal_utilization');
            
            if (!modal || !employeeIdField || !employeeNameField) {
                console.error('Modal elements not found');
                alert('Error: Modal elements not found. Please refresh the page.');
                return;
            }
            
            // Set employee data
            employeeIdField.value = employeeId;
            employeeNameField.textContent = employeeName;
            if (employeeDeptField) employeeDeptField.textContent = 'Department'; // You can enhance this with actual department data
            if (urgencyLevelField) urgencyLevelField.textContent = priority.toUpperCase() + ' PRIORITY';
            if (utilizationField) utilizationField.textContent = `Utilization: ${totalRemaining} days remaining`;
            
            // Update priority indicator in modal header
            const priorityIndicator = document.getElementById('modal_priority_indicator');
            const priorityText = document.getElementById('modal_priority_text');
            if (priorityIndicator && priorityText) {
                // Remove existing classes
                priorityIndicator.className = 'px-3 py-1 rounded-full text-xs font-medium border';
                
                // Set new classes based on priority
                switch(priority) {
                    case 'urgent':
                        priorityIndicator.classList.add('bg-red-500/20', 'text-red-400', 'border-red-500/30');
                        priorityText.textContent = 'URGENT PRIORITY';
                        break;
                    case 'critical':
                        priorityIndicator.classList.add('bg-orange-500/20', 'text-orange-400', 'border-orange-500/30');
                        priorityText.textContent = 'CRITICAL PRIORITY';
                        break;
                    case 'moderate':
                        priorityIndicator.classList.add('bg-yellow-500/20', 'text-yellow-400', 'border-yellow-500/30');
                        priorityText.textContent = 'MODERATE PRIORITY';
                        break;
                    default:
                        priorityIndicator.classList.add('bg-blue-500/20', 'text-blue-400', 'border-blue-500/30');
                        priorityText.textContent = 'LOW PRIORITY';
                }
            }
            
            // Auto-select alert type and priority based on employee priority
            const alertTypeField = document.getElementById('alert_type');
            const priorityField = document.getElementById('priority_level');
            const categoryField = document.getElementById('alert_category');
            
            if (alertTypeField && priorityField && categoryField) {
                switch(priority) {
                    case 'urgent':
                        alertTypeField.value = 'csc_utilization_low';
                        priorityField.value = 'urgent';
                        categoryField.value = 'csc_compliance';
                        break;
                    case 'critical':
                        alertTypeField.value = 'year_end_critical';
                        priorityField.value = 'critical';
                        categoryField.value = 'year_end';
                        break;
                    case 'moderate':
                        alertTypeField.value = 'moderate_reminder';
                        priorityField.value = 'moderate';
                        categoryField.value = 'utilization';
                        break;
                    default:
                        alertTypeField.value = 'planning_reminder';
                        priorityField.value = 'low';
                        categoryField.value = 'utilization';
                }
                // Trigger change event to update message
                alertTypeField.dispatchEvent(new Event('change'));
            }
            
            // Show modal with animation
            modal.classList.remove('hidden');
            setTimeout(() => {
                if (modalContent) {
                    modalContent.style.transform = 'scale(1)';
                    modalContent.style.opacity = '1';
                }
            }, 10);
            
            console.log('Enhanced modal opened successfully');
        }

        // Close alert modal with animation
        function closeAlertModal() {
            const modal = document.getElementById('alertModal');
            const modalContent = document.getElementById('modalContent');
            
            if (modalContent) {
                modalContent.style.transform = 'scale(0.95)';
                modalContent.style.opacity = '0';
            }
            
            setTimeout(() => {
                modal.classList.add('hidden');
                // Reset form
                document.getElementById('alertForm').reset();
            }, 300);
        }


        // Select message template
        function selectTemplate(templateType) {
            const templateButtons = document.querySelectorAll('.template-btn');
            templateButtons.forEach(btn => btn.classList.remove('border-primary/50', 'bg-slate-600/30'));
            
            event.target.closest('.template-btn').classList.add('border-primary/50', 'bg-slate-600/30');
            
            // Update message based on template
            const messageField = document.getElementById('message');
            const alertType = document.getElementById('alert_type').value;
            
            let message = '';
            if (templateType === 'professional') {
                message = getProfessionalMessage(alertType);
            } else {
                message = getFriendlyMessage(alertType);
            }
            
            messageField.value = message;
            updateCharCount();
        }

        // Get professional message based on alert type
        function getProfessionalMessage(alertType) {
            const messages = {
                'urgent_year_end': `Dear Employee,

We are writing to inform you that you have significant unused leave credits as we approach the end of the year. With only a few weeks remaining, we strongly encourage you to utilize your remaining leave days.

This is important for:
• Your personal well-being and work-life balance
• Compliance with Civil Service Commission regulations
• Proper leave credit utilization
• Prevention of leave credit forfeiture

Please coordinate with your immediate supervisor to schedule your remaining leave days as soon as possible.

Best regards,
Human Resources Department`,
                'csc_utilization_low': `Dear Employee,

IMPORTANT NOTICE: Your leave utilization is below the recommended Civil Service Commission (CSC) standards. This requires immediate attention to ensure compliance with CSC regulations.

CSC Compliance Requirements:
• Leave credits should be utilized efficiently throughout the year
• Low utilization may indicate poor leave planning
• CSC guidelines emphasize proper leave utilization
• This affects your overall civil service compliance record

Required Actions:
• Review your current leave utilization status
• Plan and schedule your remaining leave days
• Coordinate with your supervisor for proper scheduling
• Ensure compliance with CSC leave utilization guidelines

Please contact HR if you need assistance with leave planning.

Best regards,
Human Resources Department`,
                'critical_utilization': `Dear Employee,

CRITICAL ALERT: Your leave utilization is significantly below the recommended threshold. This requires immediate attention to prevent forfeiture of valuable leave credits.

Current Status:
• Your leave utilization is critically low
• Risk of forfeiting unused leave credits
• Impact on your overall leave benefits

Immediate Actions Required:
• Review your leave balance immediately
• Schedule your remaining leave days
• Contact your supervisor for coordination
• Consider emergency leave planning

This is a critical matter that requires your immediate attention.

Best regards,
Human Resources Department`,
                'moderate_reminder': `Dear Employee,

We hope this message finds you well. We've noticed that you have several unused leave credits for this year. We encourage you to plan and utilize your remaining leave days.

Benefits of taking leave:
• Improved work-life balance
• Enhanced productivity and focus
• Compliance with CSC guidelines
• Maximization of your leave benefits

Please review your leave balance and coordinate with your supervisor to schedule your remaining days.

Best regards,
Human Resources Department`,
                'planning_reminder': `Dear Employee,

We wanted to remind you about your available leave credits for this year. Planning your leave in advance helps ensure you can utilize all your entitled days.

Your leave benefits include:
• Vacation leave for rest and recreation
• Sick leave for health needs
• Special leave privileges as applicable
• Civil Service Commission (CSC) leave credits

Please consider scheduling your remaining leave days to maximize your benefits.

Best regards,
Human Resources Department`,
                'csc_compliance': `Dear Employee,

This is a reminder regarding Civil Service Commission compliance for leave utilization. As a government employee, it's important to utilize your leave credits appropriately.

CSC Guidelines:
• Leave credits should be used within the year
• Proper documentation is required
• Supervisor approval is necessary
• CSC compliance is mandatory

Please ensure you utilize your remaining leave credits in compliance with CSC regulations.

Best regards,
Human Resources Department`,
                'wellness_focus': `Dear Employee,

Your well-being is important to us. We encourage you to take advantage of your available leave credits to rest, recharge, and maintain a healthy work-life balance.

Wellness benefits of taking leave:
• Reduced stress and burnout
• Improved mental health
• Better work performance
• Quality time with family
• Prevention of leave credit forfeiture

Please consider using your remaining leave days for your personal wellness.

Best regards,
Human Resources Department`,
                'csc_limit_exceeded': `Dear Employee,

CRITICAL NOTICE: You have exceeded the Civil Service Commission (CSC) leave limits for this year. This is a serious compliance violation that requires immediate attention.

CSC Violation Details:
• You have exceeded the maximum allowable leave days
• This violates CSC Memorandum Circular guidelines
• Administrative sanctions may apply
• Immediate corrective action is required

Required Actions:
• Contact HR immediately to discuss this violation
• Review your leave records for accuracy
• Coordinate with your supervisor for resolution
• Ensure future compliance with CSC regulations

This matter requires your immediate attention to maintain your civil service record.

Best regards,
Human Resources Department`,
                'csc_limit_approaching': `Dear Employee,

URGENT WARNING: You are approaching the Civil Service Commission (CSC) leave limits for this year. Please take immediate action to avoid compliance violations.

CSC Compliance Status:
• You are close to exceeding maximum allowable leave days
• CSC regulations must be strictly followed
• Preventive action is required immediately
• This affects your civil service compliance record

Immediate Actions Required:
• Review your current leave usage
• Plan remaining leave days carefully
• Coordinate with your supervisor
• Ensure compliance with CSC guidelines

Please contact HR if you need assistance with leave planning.

Best regards,
Human Resources Department`,
                'year_end_critical': `Dear Employee,

CRITICAL ALERT: You have unused leave credits that will be forfeited in the next few days! Immediate action is required to prevent loss of valuable benefits.

Forfeiture Risk Details:
• Unused leave credits will be forfeited on December 31
• This is a critical situation requiring immediate action
• You will lose these benefits permanently
• CSC regulations require proper leave utilization

URGENT ACTIONS REQUIRED:
• Schedule your remaining leave days immediately
• Contact your supervisor for emergency approval
• Coordinate with HR for expedited processing
• Do not delay - time is running out!

This is your final warning before forfeiture occurs.

Best regards,
Human Resources Department`,
                'year_end_warning': `Dear Employee,

IMPORTANT NOTICE: You have unused leave credits that will be forfeited at year-end. Please take action to utilize your remaining leave days.

Forfeiture Warning:
• Unused leave credits will be forfeited on December 31
• You have limited time to use these benefits
• CSC regulations require proper leave utilization
• Don't let your earned benefits go to waste

Recommended Actions:
• Review your leave balance immediately
• Schedule your remaining leave days
• Coordinate with your supervisor
• Plan your leave utilization carefully

Please take action soon to avoid forfeiture of your leave credits.

Best regards,
Human Resources Department`
            };
            
            return messages[alertType] || messages['planning_reminder'];
        }

        // Get friendly message based on alert type
        function getFriendlyMessage(alertType) {
            const messages = {
                'urgent_year_end': `Hi there! 👋

We wanted to reach out because we noticed you still have quite a few leave days left this year, and time is running out! 😅

We really care about your well-being and want to make sure you get the rest and relaxation you deserve. Plus, it's important to use those leave credits before they expire.

Could you please chat with your supervisor about scheduling your remaining days? We'd hate for you to miss out on this benefit!

Take care! 💚
Your HR Team`,
                'csc_utilization_low': `Hey! 📊

We noticed your leave utilization is below the recommended CSC standards. Don't worry, we're here to help you get back on track! 😊

What's happening:
📈 Your leave utilization is lower than recommended
📋 CSC guidelines emphasize proper leave planning
🤝 We want to help you maximize your benefits
💡 Let's work together to improve your utilization

What you can do:
✨ Review your current leave usage
📅 Plan your remaining days strategically
🤝 Talk to your supervisor about scheduling
📚 Make sure you understand CSC guidelines

We're here to help you succeed! 💪

Your supportive HR Team 💙`,
                'critical_utilization': `Hi! ⚠️

We need to talk - your leave utilization is really low and we're worried you might lose your leave credits! 😰

This is super important because:
🚨 You're at risk of forfeiting valuable leave days
🚨 It affects your overall benefits
🚨 We want to make sure you get what you deserve!

Please, please, please:
✨ Check your leave balance right away
✨ Schedule your remaining days
✨ Talk to your supervisor immediately

We're here to help you through this! 💪

Your concerned HR Team 💚`,
                'moderate_reminder': `Hello! 😊

Hope you're doing well! We just wanted to gently remind you that you have some leave days available this year.

Taking time off is so important for:
✨ Recharging your batteries
✨ Spending quality time with loved ones
✨ Coming back refreshed and ready to go!
✨ Making sure you don't lose your benefits!

When you get a chance, please check in with your supervisor about using your remaining leave days.

Warm regards,
Your HR Team 💙`,
                'planning_reminder': `Hi! 🌟

Just a friendly heads up that you have some leave credits available this year. We know how busy things can get, but we want to make sure you don't forget to treat yourself to some well-deserved time off!

Planning ahead makes it easier to:
📅 Coordinate with your team
🏖️ Make the most of your time off
😌 Reduce stress about scheduling
💎 Maximize your leave benefits

Feel free to reach out if you need any help planning your leave!

Best wishes,
Your HR Team 💫`,
                'csc_compliance': `Hello! 📋

We wanted to give you a heads up about CSC compliance regarding your leave credits. Don't worry, it's nothing serious - we just want to make sure you're all set!

A quick reminder that:
✅ Leave credits should be used within the year
✅ CSC compliance is important for your record
✅ We're here to help with any questions
✅ Your supervisor can assist with scheduling

If you have any questions about the guidelines, just let us know!

Best regards,
Your HR Team 📚`,
                'wellness_focus': `Hey there! 💚

We hope you're feeling great! We wanted to check in because we noticed you have some leave days available, and we really want you to take care of yourself.

Your wellness matters to us because:
💪 You're an amazing part of our team
🧘 Taking breaks helps you perform even better
❤️ We care about your happiness and health
🌟 We want you to get the most out of your benefits

Please don't hesitate to use your leave days - you've earned them! And if you need any support, we're here for you.

Take care of yourself! 🌈
Your HR Team 💕`,
                'csc_limit_exceeded': `Hi! 🚨

We need to talk about something important - it looks like you've exceeded the CSC leave limits for this year. Don't worry, we're here to help you sort this out! 😊

What happened:
📊 You've used more leave days than allowed by CSC rules
📋 This is a compliance issue we need to address
🤝 We're here to help you resolve this
💡 Let's work together to fix this

What we need to do:
✨ Contact HR to discuss the situation
📝 Review your leave records together
🤝 Find a solution that works for everyone
📚 Make sure we follow CSC guidelines going forward

Don't stress - we'll get this sorted out together! 💪

Your supportive HR Team 💙`,
                'csc_limit_approaching': `Hey! ⚠️

Just a friendly heads up - you're getting close to the CSC leave limits for this year. We want to make sure you stay compliant! 😊

Current situation:
📊 You're approaching the maximum leave days allowed
📋 CSC rules are important to follow
🤝 We're here to help you plan ahead
💡 Let's make sure you stay within limits

What you can do:
✨ Review your current leave usage
📅 Plan your remaining days carefully
🤝 Talk to your supervisor about scheduling
📚 Make sure you understand the CSC guidelines

We're here to help you stay compliant! 💪

Your helpful HR Team 💙`,
                'year_end_critical': `Hey! 🔥

This is super urgent - you have leave days that will be forfeited very soon! We don't want you to lose your hard-earned benefits! 😱

The situation:
⏰ Your leave credits will be forfeited in just a few days
💔 We don't want you to lose these valuable benefits
🚨 This is a critical situation that needs immediate action
💪 We're here to help you save your leave days!

What you need to do RIGHT NOW:
✨ Schedule your remaining leave days immediately
📞 Contact your supervisor for emergency approval
🤝 Coordinate with HR for fast processing
⚡ Don't wait - time is running out!

We're here to help you save your benefits! 💪

Your concerned HR Team 💚`,
                'year_end_warning': `Hi there! ⚠️

We wanted to give you a heads up - you have some leave days that will be forfeited at the end of the year. Let's make sure you don't lose them! 😊

What's happening:
📅 Your unused leave credits will be forfeited on December 31
💔 We don't want you to lose these benefits
⏰ You still have time to use them
💪 We're here to help you plan

What you can do:
✨ Review your leave balance
📅 Schedule your remaining days
🤝 Talk to your supervisor about planning
📚 Make sure you use all your benefits

Don't let your earned benefits go to waste! 💪

Your helpful HR Team 💙`
            };
            
            return messages[alertType] || messages['planning_reminder'];
        }

        // Preview message

        // Update character count
        function updateCharCount() {
            const message = document.getElementById('message');
            const footerCharCount = document.getElementById('footerCharCount');
            
            if (footerCharCount) {
                footerCharCount.textContent = message.value.length + ' characters';
            }
        }



        // Enhanced auto-fill message based on alert type
        document.addEventListener('DOMContentLoaded', function() {
            const alertTypeField = document.getElementById('alert_type');
            const messageField = document.getElementById('message');
            const priorityField = document.getElementById('priority_level');
            
            if (alertTypeField && messageField) {
                alertTypeField.addEventListener('change', function() {
                    const alertType = this.value;
                    const message = getProfessionalMessage(alertType);
                    messageField.value = message;
                    updateCharCount();
                });
                
                // Add character count listener
                messageField.addEventListener('input', updateCharCount);
            }
            
            // Update priority indicator when priority changes
            if (priorityField) {
                priorityField.addEventListener('change', function() {
                    updatePriorityIndicator(this.value);
                });
            }
        });
        
        // Function to update priority indicator
        function updatePriorityIndicator(priority) {
            const priorityIndicator = document.getElementById('modal_priority_indicator');
            const priorityText = document.getElementById('modal_priority_text');
            
            if (priorityIndicator && priorityText) {
                // Remove existing classes
                priorityIndicator.className = 'px-3 py-1 rounded-full text-xs font-medium border';
                
                // Set new classes based on priority
                switch(priority) {
                    case 'urgent':
                        priorityIndicator.classList.add('bg-red-500/20', 'text-red-400', 'border-red-500/30');
                        priorityText.textContent = 'URGENT PRIORITY';
                        break;
                    case 'critical':
                        priorityIndicator.classList.add('bg-orange-500/20', 'text-orange-400', 'border-orange-500/30');
                        priorityText.textContent = 'CRITICAL PRIORITY';
                        break;
                    case 'moderate':
                        priorityIndicator.classList.add('bg-yellow-500/20', 'text-yellow-400', 'border-yellow-500/30');
                        priorityText.textContent = 'MODERATE PRIORITY';
                        break;
                    default:
                        priorityIndicator.classList.add('bg-blue-500/20', 'text-blue-400', 'border-blue-500/30');
                        priorityText.textContent = 'LOW PRIORITY';
                }
            }
        }

        // AJAX form submission with real-time feedback
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form[method="POST"]');
            if (form) {
                console.log('Form found, adding AJAX submit listener');
                form.addEventListener('submit', function(e) {
                    e.preventDefault(); // Prevent default form submission
                    console.log('AJAX form submitted');
                    
                    // Validate form fields before submission
                    const employeeId = this.querySelector('input[name="employee_id"]').value;
                    const alertType = this.querySelector('select[name="alert_type"]').value;
                    const message = this.querySelector('textarea[name="message"]').value;
                    
                    // Check if form is properly filled
                    if (!employeeId || !alertType || !message.trim()) {
                        alert('Please fill in all required fields before sending the alert.');
                        console.log('Form validation failed: missing required fields');
                        return false;
                    }
                    
                    // Show loading state
                    console.log('Processing leave alert via AJAX...');
                    
                    // Show loading state on button
                    const submitBtn = this.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Sending...';
                        submitBtn.disabled = true;
                    }
                    
                    // Send AJAX request
                    sendAlertAjax(employeeId, alertType, message, submitBtn);
                });
            } else {
                console.error('Form not found!');
            }
        });

        // Send alert via AJAX
        async function sendAlertAjax(employeeId, alertType, message, submitBtn) {
            try {
                // Get additional form data
                const priority = document.getElementById('priority_level')?.value || 'moderate';
                const category = document.getElementById('alert_category')?.value || 'utilization';
                
                const response = await fetch('/ELMS/api/send_alert.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        employee_id: employeeId,
                        alert_type: alertType,
                        message: message,
                        priority: priority,
                        alert_category: category
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Show success message
                    showAlertSuccess(`Alert sent successfully to ${data.employee_name}!`);
                    
                    // Close modal
                    closeAlertModal();
                    
                    // Refresh page to show updated data
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showAlertError('Error sending alert: ' + data.error);
                }
            } catch (error) {
                console.error('Error sending alert:', error);
                showAlertError('Network error sending alert. Please try again.');
            } finally {
                // Reset button state
                if (submitBtn) {
                    submitBtn.innerHTML = '<i class="fas fa-paper-plane mr-1 text-xs"></i>Send Alert';
                    submitBtn.disabled = false;
                }
            }
        }

        // Show success alert
        function showAlertSuccess(message) {
            const alertDiv = document.createElement('div');
            alertDiv.className = 'fixed top-20 right-4 z-50 px-6 py-3 rounded-lg shadow-lg transition-all duration-300 transform translate-x-full bg-green-600 text-white';
            alertDiv.innerHTML = `
                <div class="flex items-center space-x-2">
                    <i class="fas fa-check-circle"></i>
                    <span>${message}</span>
                </div>
            `;
            
            document.body.appendChild(alertDiv);
            
            // Animate in
            setTimeout(() => {
                alertDiv.classList.remove('translate-x-full');
            }, 100);
            
            // Auto remove after 3 seconds
            setTimeout(() => {
                alertDiv.classList.add('translate-x-full');
                setTimeout(() => {
                    if (alertDiv.parentNode) {
                        alertDiv.parentNode.removeChild(alertDiv);
                    }
                }, 300);
            }, 3000);
        }

        // Show error alert
        function showAlertError(message) {
            const alertDiv = document.createElement('div');
            alertDiv.className = 'fixed top-20 right-4 z-50 px-6 py-3 rounded-lg shadow-lg transition-all duration-300 transform translate-x-full bg-red-600 text-white';
            alertDiv.innerHTML = `
                <div class="flex items-center space-x-2">
                    <i class="fas fa-exclamation-circle"></i>
                    <span>${message}</span>
                </div>
            `;
            
            document.body.appendChild(alertDiv);
            
            // Animate in
            setTimeout(() => {
                alertDiv.classList.remove('translate-x-full');
            }, 100);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                alertDiv.classList.add('translate-x-full');
                setTimeout(() => {
                    if (alertDiv.parentNode) {
                        alertDiv.parentNode.removeChild(alertDiv);
                    }
                }, 300);
            }, 5000);
        }

        // Function to show alerts
        function showAlert(type, message) {
            // Remove existing alerts
            const existingAlerts = document.querySelectorAll('.alert');
            existingAlerts.forEach(alert => alert.remove());
            
            // Create new alert
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            // Insert at top of main content
            const mainContent = document.querySelector('.main-content .container-fluid');
            mainContent.insertBefore(alertDiv, mainContent.firstChild);
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }

        // Function to update employee card after sending alert
        function updateEmployeeCard(employeeId) {
            const employeeCard = document.querySelector(`[data-employee-id="${employeeId}"]`).closest('.employee-card');
            if (employeeCard) {
                // Add a visual indicator that alert was sent
                const button = employeeCard.querySelector('button');
                button.innerHTML = '<i class="fas fa-check me-2"></i>Alert Sent';
                button.classList.remove('btn-warning');
                button.classList.add('btn-success');
                button.disabled = true;
                
                // Reset after 3 seconds
                setTimeout(() => {
                    button.innerHTML = '<i class="fas fa-bell me-2"></i>Send Alert';
                    button.classList.remove('btn-success');
                    button.classList.add('btn-warning');
                    button.disabled = false;
                }, 3000);
            }
        }


        // Function to fetch pending leave count
        function fetchPendingLeaveCount() {
            fetch('api/get_pending_leave_count.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const badge = document.getElementById('pendingLeaveBadge');
                        if (data.count > 0) {
                            badge.textContent = data.count;
                            badge.style.display = 'inline-block';
                        } else {
                            badge.style.display = 'none';
                        }
                    }
                })
                .catch(error => {
                    console.error('Error fetching pending leave count:', error);
                });
        }

        // Fetch pending leave count on page load
        fetchPendingLeaveCount();

        // Update pending leave count every 30 seconds
        setInterval(fetchPendingLeaveCount, 30000);
    </script>
            </div>
        </main>
    </div>
</body>
</html>

