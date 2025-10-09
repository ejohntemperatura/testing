<?php
session_start();
require_once '../../../../config/database.php';

// Check if user is logged in and is an admin or manager
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin','manager','director'])) {
    header('Location: ../../../auth/views/login.php');
    exit();
}

// Get user information
$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Get date range for filtering
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// Fetch statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_requests,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_requests,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_requests
    FROM leave_requests 
    WHERE start_date BETWEEN ? AND ?
");
$stmt->execute([$start_date, $end_date]);
$stats = $stmt->fetch();

// Fetch leave requests by department
$stmt = $pdo->prepare("
    SELECT 
        e.department,
        COUNT(*) as total_requests,
        SUM(CASE WHEN lr.status = 'approved' THEN 1 ELSE 0 END) as approved_requests,
        SUM(CASE WHEN lr.status = 'rejected' THEN 1 ELSE 0 END) as rejected_requests,
        SUM(CASE WHEN lr.status = 'pending' THEN 1 ELSE 0 END) as pending_requests
    FROM leave_requests lr
    JOIN employees e ON lr.employee_id = e.id
    WHERE lr.start_date BETWEEN ? AND ?
    GROUP BY e.department
    ORDER BY total_requests DESC
");
$stmt->execute([$start_date, $end_date]);
$department_stats = $stmt->fetchAll();

// Fetch leave requests by type
$stmt = $pdo->prepare("
    SELECT 
        leave_type,
        COUNT(*) as total_requests,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_requests,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_requests,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_requests
    FROM leave_requests 
    WHERE start_date BETWEEN ? AND ?
    GROUP BY leave_type
    ORDER BY total_requests DESC
");
$stmt->execute([$start_date, $end_date]);
$leave_type_stats = $stmt->fetchAll();

// Fetch monthly trends
$stmt = $pdo->prepare("
    SELECT 
        DATE_FORMAT(start_date, '%Y-%m') as month,
        COUNT(*) as total_requests,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_requests,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_requests
    FROM leave_requests 
    WHERE start_date BETWEEN ? AND ?
    GROUP BY DATE_FORMAT(start_date, '%Y-%m')
    ORDER BY month
");
$stmt->execute([$start_date, $end_date]);
$monthly_trends = $stmt->fetchAll();

// Enhanced export functionality
if (isset($_POST['export'])) {
    $export_type = $_POST['export_type'];
    
    if ($export_type === 'leave_requests') {
        // Export leave requests
        $stmt = $pdo->prepare("
            SELECT 
                e.name as employee_name,
                e.department,
                e.position,
                lr.leave_type,
                lr.start_date,
                lr.end_date,
                lr.days_requested,
                lr.reason,
                lr.status,
                lr.location_type,
                lr.medical_condition,
                lr.is_late,
                lr.created_at
            FROM leave_requests lr
            JOIN employees e ON lr.employee_id = e.id
            WHERE lr.start_date BETWEEN ? AND ?
            ORDER BY lr.created_at DESC
        ");
        $stmt->execute([$start_date, $end_date]);
        $export_data = $stmt->fetchAll();
        
        // Generate CSV
        $filename = "leave_requests_" . date('Y-m-d') . ".csv";
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Employee', 'Department', 'Position', 'Leave Type', 'Start Date', 'End Date', 'Days', 'Reason', 'Status', 'Location', 'Medical', 'Late', 'Created At']);
        
        foreach ($export_data as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit();
    }
    
    if ($export_type === 'employee_summary') {
        // Export employee summary with leave balances
        $stmt = $pdo->prepare("
            SELECT 
                e.name,
                e.email,
                e.department,
                e.position,
                e.gender,
                e.is_solo_parent,
                e.service_start_date,
                e.vacation_leave_balance,
                e.sick_leave_balance,
                e.special_leave_privilege_balance,
                e.maternity_leave_balance,
                e.paternity_leave_balance,
                e.solo_parent_leave_balance,
                e.vawc_leave_balance,
                e.rehabilitation_leave_balance,
                e.terminal_leave_balance
            FROM employees e
            ORDER BY e.department, e.name
        ");
        $stmt->execute();
        $export_data = $stmt->fetchAll();
        
        $filename = "employee_summary_" . date('Y-m-d') . ".csv";
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Name', 'Email', 'Department', 'Position', 'Gender', 'Solo Parent', 'Service Start', 'Vacation Leave', 'Sick Leave', 'Special Privilege', 'Maternity Leave', 'Paternity Leave', 'Solo Parent Leave', 'VAWC Leave', 'Rehabilitation Leave', 'Terminal Leave']);
        
        foreach ($export_data as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit();
    }
    
    if ($export_type === 'department_summary') {
        // Export department summary
        $stmt = $pdo->prepare("
            SELECT 
                e.department,
                COUNT(DISTINCT e.id) as total_employees,
                COUNT(lr.id) as total_requests,
                SUM(CASE WHEN lr.status = 'approved' THEN 1 ELSE 0 END) as approved_requests,
                SUM(CASE WHEN lr.status = 'rejected' THEN 1 ELSE 0 END) as rejected_requests,
                SUM(CASE WHEN lr.status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
                SUM(lr.days_requested) as total_days_requested,
                AVG(lr.days_requested) as avg_days_per_request
            FROM employees e
            LEFT JOIN leave_requests lr ON e.id = lr.employee_id AND lr.start_date BETWEEN ? AND ?
            GROUP BY e.department
            ORDER BY total_requests DESC
        ");
        $stmt->execute([$start_date, $end_date]);
        $export_data = $stmt->fetchAll();
        
        $filename = "department_summary_" . date('Y-m-d') . ".csv";
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Department', 'Total Employees', 'Total Requests', 'Approved', 'Rejected', 'Pending', 'Total Days', 'Avg Days per Request']);
        
        foreach ($export_data as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit();
    }
    
    if ($export_type === 'leave_balances') {
        // Export leave balances
        $stmt = $pdo->prepare("
            SELECT 
                e.name,
                e.department,
                e.vacation_leave_balance,
                e.sick_leave_balance,
                e.special_leave_privilege_balance,
                e.maternity_leave_balance,
                e.paternity_leave_balance,
                e.solo_parent_leave_balance,
                e.vawc_leave_balance,
                e.rehabilitation_leave_balance,
                e.terminal_leave_balance,
                e.last_leave_credit_update
            FROM employees e
            ORDER BY e.department, e.name
        ");
        $stmt->execute();
        $export_data = $stmt->fetchAll();
        
        $filename = "leave_balances_" . date('Y-m-d') . ".csv";
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Employee', 'Department', 'Vacation Leave', 'Sick Leave', 'Special Privilege', 'Maternity Leave', 'Paternity Leave', 'Solo Parent Leave', 'VAWC Leave', 'Rehabilitation Leave', 'Terminal Leave', 'Last Update']);
        
        foreach ($export_data as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit();
    }
}

// Handle comprehensive Excel report generation
if (isset($_POST['generate_excel_report'])) {
    require_once '../../../../app/core/services/EnhancedReportGenerator.php';
    $reportGenerator = new EnhancedReportGenerator($pdo);
    $reportGenerator->generateComprehensiveReport($start_date, $end_date);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- OFFLINE Tailwind CSS - No internet required! -->
    <link rel="stylesheet" href="../../../../assets/css/tailwind.css">
        <!-- Font Awesome Local - No internet required! -->
    <link rel="stylesheet" href="../../../../assets/libs/fontawesome/css/all.min.css">
    

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - ELMS</title>
    <script>
    </script>
    
    <link rel="stylesheet" href="../../../../assets/css/style.css">
    <link rel="stylesheet" href="../../../../assets/css/dark-theme.css">
    <link rel="stylesheet" href="../../../../assets/css/admin_style.css">
    <script src="../../../../assets/libs/chartjs/chart.umd.min.js"></script>
    
    <script>
        // Toggle analytics section
        function toggleAnalytics() {
            const analyticsSection = document.getElementById('analytics-section');
            if (analyticsSection.classList.contains('hidden')) {
                analyticsSection.classList.remove('hidden');
            } else {
                analyticsSection.classList.add('hidden');
            }
        }

        // Show analytics tab
        function showAnalyticsTab(tabName) {
            // Hide all analytics content
            document.querySelectorAll('.analytics-content').forEach(content => {
                content.classList.add('hidden');
            });
            
            // Remove active class from all analytics tabs
            document.querySelectorAll('[id$="-tab"]').forEach(tab => {
                if (tab.id.includes('analytics') || tab.id.includes('overview') || tab.id.includes('trends') || tab.id.includes('departments') || tab.id.includes('employees')) {
                    tab.classList.remove('border-blue-500', 'text-blue-400');
                    tab.classList.add('border-transparent', 'text-slate-400');
                }
            });
            
            // Show selected analytics content
            document.getElementById(tabName + '-content').classList.remove('hidden');
            
            // Add active class to selected analytics tab
            const activeTab = document.getElementById(tabName + '-tab');
            activeTab.classList.remove('border-transparent', 'text-slate-400');
            activeTab.classList.add('border-blue-500', 'text-blue-400');
        }

        // Toggle user dropdown
        function toggleUserDropdown() {
            console.log('toggleUserDropdown called');
            const dropdown = document.getElementById('userDropdown');
            const notificationDropdown = document.getElementById('notificationDropdown');
            console.log('Dropdown element:', dropdown);
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
                console.log('Dropdown classes after toggle:', dropdown.className);
            } else {
                console.error('Dropdown element not found');
            }
        }

        // Make function globally available
        window.toggleUserDropdown = toggleUserDropdown;

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('userDropdown');
            const button = event.target.closest('button[onclick="toggleUserDropdown()"]');
            
            if (dropdown && !dropdown.contains(event.target) && !button) {
                dropdown.classList.add('hidden');
            }
        });

        // Ensure function is available when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, toggleUserDropdown function available:', typeof window.toggleUserDropdown);
        });
    </script>
</head>
<body class="bg-slate-900 text-white">
    <!-- Sidebar -->
    <?php 
        $role = $_SESSION['role'];
        $panelTitle = $role === 'director' ? 'Director Panel' : ($role === 'manager' ? 'Department Head' : 'Admin Panel');
        $dashboardLink = $role === 'director' ? 'dashboard.php' : ($role === 'manager' ? 'dashboard.php' : 'dashboard.php');
    ?>
    <?php include '../../../../includes/unified_navbar.php'; ?>

    <div class="flex">
        <!-- Left Sidebar -->
        <aside id="sidebar" class="fixed left-0 top-16 h-screen w-64 bg-slate-900 border-r border-slate-800 overflow-y-auto z-40 transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out">
            <nav class="p-4 space-y-2">
                <!-- Active Navigation Item -->
                <a href="<?php echo $dashboardLink; ?>" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
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
                 
                    <a href="leave_alerts.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                        <i class="fas fa-bell w-5"></i>
                        <span>Leave Alerts</span>
                    </a>
                
                
                    <div class="space-y-1">
                    <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider px-4 py-2">Reports</h3>
                    
                    <a href="calendar.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                        <i class="fas fa-calendar w-5"></i>
                        <span>Leave Chart</span>
                    </a>
                
                <!-- Active Navigation Item -->
                <a href="reports.php" class="flex items-center space-x-3 px-4 py-3 text-white bg-blue-500/20 rounded-lg border border-blue-500/30">
                    <i class="fas fa-file-alt w-5"></i>
                    <span>Reports</span>
                </a>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <main class="flex-1 ml-64 p-6 pt-24">
            <div class="max-w-7xl mx-auto">

                <!-- Page Header -->
                <div class="mb-8">
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 bg-gradient-to-r from-primary to-accent rounded-2xl flex items-center justify-center">
                            <i class="fas fa-file-alt text-2xl text-white"></i>
                        </div>
                        <div>
                            <h1 class="text-3xl font-bold text-white mb-2">Reports & Analytics</h1>
                            <p class="text-slate-400">Comprehensive leave management reports and insights</p>
                        </div>
                    </div>
                </div>

                <!-- Date Range Filter -->
                <div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden mb-8">
                    <div class="px-6 py-4 border-b border-slate-700 bg-slate-700/30">
                        <h3 class="text-xl font-semibold text-white flex items-center">
                            <i class="fas fa-calendar text-primary mr-3"></i>Date Range Filter
                        </h3>
                    </div>
                    <div class="p-6">
                        <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label for="start_date" class="block text-sm font-semibold text-slate-300 mb-2">Start Date</label>
                                <input type="date" name="start_date" id="start_date" value="<?php echo $start_date; ?>" 
                                       class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>
                            <div>
                                <label for="end_date" class="block text-sm font-semibold text-slate-300 mb-2">End Date</label>
                                <input type="date" name="end_date" id="end_date" value="<?php echo $end_date; ?>" 
                                       class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>
                            <div class="flex items-end">
                                <button type="submit" class="w-full bg-gradient-to-r from-primary to-accent hover:from-primary/90 hover:to-accent/90 text-white font-semibold py-3 px-6 rounded-xl transition-all duration-300 transform hover:scale-[1.02] hover:shadow-xl flex items-center justify-center">
                                    <i class="fas fa-search mr-2"></i>Apply Filter
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Report Generation Section -->
                <div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden mb-8">
                    <div class="px-6 py-4 border-b border-slate-700 bg-slate-700/30">
                        <h3 class="text-xl font-semibold text-white flex items-center">
                            <i class="fas fa-chart-bar text-primary mr-3"></i>Generate Reports
                        </h3>
                        <p class="text-slate-400 text-sm mt-1">Choose from various report types and formats</p>
                    </div>
                    <div class="p-6">
                        <!-- Report Type Selection -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                            <!-- Quick Reports -->
                            <div class="bg-gradient-to-br from-green-500/10 to-emerald-500/10 rounded-xl p-6 border border-green-500/20 hover:border-green-500/40 transition-all duration-300">
                                <div class="flex items-center mb-4">
                                    <div class="w-12 h-12 bg-green-500/20 rounded-lg flex items-center justify-center mr-4">
                                        <i class="fas fa-bolt text-green-400 text-xl"></i>
                                    </div>
                            <div>
                                        <h4 class="text-lg font-semibold text-white">Quick Reports</h4>
                                        <p class="text-slate-400 text-sm">Fast CSV exports</p>
                                    </div>
                                </div>
                                <form method="POST" class="space-y-3">
                                    <select name="export_type" required class="w-full bg-slate-600 border border-slate-500 rounded-lg px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-green-500">
                                        <option value="">Select Quick Report</option>
                                        <option value="leave_requests">Leave Requests</option>
                                        <option value="employee_summary">Employee Summary</option>
                                        <option value="department_summary">Department Summary</option>
                                        <option value="leave_balances">Leave Balances</option>
                                </select>
                                    <button type="submit" name="export" class="w-full bg-green-500 hover:bg-green-600 text-white font-semibold py-2 px-4 rounded-lg transition-colors flex items-center justify-center">
                                        <i class="fas fa-download mr-2"></i>Export CSV
                                    </button>
                                </form>
                            </div>

                            <!-- Excel Reports -->
                            <div class="bg-gradient-to-br from-blue-500/10 to-cyan-500/10 rounded-xl p-6 border border-blue-500/20 hover:border-blue-500/40 transition-all duration-300">
                                <div class="flex items-center mb-4">
                                    <div class="w-12 h-12 bg-blue-500/20 rounded-lg flex items-center justify-center mr-4">
                                        <i class="fas fa-file-excel text-blue-400 text-xl"></i>
                                    </div>
                                    <div>
                                        <h4 class="text-lg font-semibold text-white">Excel Reports</h4>
                                        <p class="text-slate-400 text-sm">Comprehensive multi-sheet reports</p>
                                    </div>
                                </div>
                                <form method="POST" class="space-y-3">
                                    <button type="submit" name="generate_excel_report" 
                                            class="w-full bg-blue-500 hover:bg-blue-600 text-white font-semibold py-2 px-4 rounded-lg transition-colors flex items-center justify-center">
                                        <i class="fas fa-chart-line mr-2"></i>Generate Excel Report
                                    </button>
                                </form>
                            </div>

                            <!-- Analytics & Insights -->
                            <div class="bg-gradient-to-br from-purple-500/10 to-pink-500/10 rounded-xl p-6 border border-purple-500/20 hover:border-purple-500/40 transition-all duration-300">
                                <div class="flex items-center mb-4">
                                    <div class="w-12 h-12 bg-purple-500/20 rounded-lg flex items-center justify-center mr-4">
                                        <i class="fas fa-chart-pie text-purple-400 text-xl"></i>
                                    </div>
                                    <div>
                                        <h4 class="text-lg font-semibold text-white">Analytics</h4>
                                        <p class="text-slate-400 text-sm">View detailed insights</p>
                                    </div>
                                </div>
                                <button onclick="toggleAnalytics()" 
                                        class="w-full bg-purple-500 hover:bg-purple-600 text-white font-semibold py-2 px-4 rounded-lg transition-colors flex items-center justify-center">
                                    <i class="fas fa-chart-bar mr-2"></i>View Analytics
                                </button>
                            </div>
                        </div>

                        <!-- Advanced Report Options -->
                        <div class="bg-slate-700/30 rounded-xl p-6 border border-slate-600">
                            <h4 class="text-lg font-semibold text-white mb-4 flex items-center">
                                <i class="fas fa-cog text-slate-400 mr-2"></i>Advanced Report Options
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                <div class="space-y-2">
                                    <label class="block text-sm font-semibold text-slate-300">Report Format</label>
                                    <select class="w-full bg-slate-600 border border-slate-500 rounded-lg px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-primary">
                                        <option value="excel">Excel (.xlsx)</option>
                                        <option value="csv">CSV (.csv)</option>
                                        <option value="pdf">PDF (.pdf)</option>
                                    </select>
                                </div>
                                <div class="space-y-2">
                                    <label class="block text-sm font-semibold text-slate-300">Include Charts</label>
                                    <select class="w-full bg-slate-600 border border-slate-500 rounded-lg px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-primary">
                                        <option value="yes">Yes</option>
                                        <option value="no">No</option>
                                    </select>
                                </div>
                                <div class="space-y-2">
                                    <label class="block text-sm font-semibold text-slate-300">Data Granularity</label>
                                    <select class="w-full bg-slate-600 border border-slate-500 rounded-lg px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-primary">
                                        <option value="detailed">Detailed</option>
                                        <option value="summary">Summary Only</option>
                                        <option value="custom">Custom</option>
                                    </select>
                                </div>
                                <div class="space-y-2">
                                    <label class="block text-sm font-semibold text-slate-300">Auto Schedule</label>
                                    <select class="w-full bg-slate-600 border border-slate-500 rounded-lg px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-primary">
                                        <option value="none">None</option>
                                        <option value="daily">Daily</option>
                                        <option value="weekly">Weekly</option>
                                        <option value="monthly">Monthly</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Analytics Section (Hidden by default) -->
                <div id="analytics-section" class="hidden mb-8">
                    <div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden">
                        <div class="px-6 py-4 border-b border-slate-700 bg-slate-700/30">
                            <h3 class="text-xl font-semibold text-white flex items-center">
                                <i class="fas fa-chart-bar text-primary mr-3"></i>Analytics & Insights
                            </h3>
                        </div>
                        <div class="p-6">
                            <!-- Analytics Tabs -->
                            <div class="border-b border-slate-600 mb-6">
                                <nav class="-mb-px flex space-x-8">
                                    <button onclick="showAnalyticsTab('overview')" id="overview-tab" 
                                            class="py-2 px-1 border-b-2 border-blue-500 font-medium text-sm text-blue-400">
                                        <i class="fas fa-chart-pie mr-2"></i>Overview
                                    </button>
                                    <button onclick="showAnalyticsTab('trends')" id="trends-tab" 
                                            class="py-2 px-1 border-b-2 border-transparent font-medium text-sm text-slate-400 hover:text-slate-300">
                                        <i class="fas fa-trending-up mr-2"></i>Trends
                                    </button>
                                    <button onclick="showAnalyticsTab('departments')" id="departments-tab" 
                                            class="py-2 px-1 border-b-2 border-transparent font-medium text-sm text-slate-400 hover:text-slate-300">
                                        <i class="fas fa-building mr-2"></i>Departments
                                    </button>
                                    <button onclick="showAnalyticsTab('employees')" id="employees-tab" 
                                            class="py-2 px-1 border-b-2 border-transparent font-medium text-sm text-slate-400 hover:text-slate-300">
                                        <i class="fas fa-users mr-2"></i>Employees
                                    </button>
                                </nav>
                            </div>

                            <!-- Overview Tab -->
                            <div id="overview-content" class="analytics-content">
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                                    <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-lg p-4 text-white">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <p class="text-blue-100 text-sm">Total Requests</p>
                                                <p class="text-2xl font-bold"><?php echo $stats['total_requests']; ?></p>
                                            </div>
                                            <i class="fas fa-list text-3xl opacity-75"></i>
                                        </div>
                                    </div>
                                    <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-lg p-4 text-white">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <p class="text-green-100 text-sm">Approval Rate</p>
                                                <p class="text-2xl font-bold"><?php echo $stats['total_requests'] > 0 ? round(($stats['approved_requests'] / $stats['total_requests']) * 100, 1) : 0; ?>%</p>
                                            </div>
                                            <i class="fas fa-check-circle text-3xl opacity-75"></i>
                                        </div>
                                    </div>
                                    <div class="bg-gradient-to-r from-yellow-500 to-yellow-600 rounded-lg p-4 text-white">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <p class="text-yellow-100 text-sm">Pending</p>
                                                <p class="text-2xl font-bold"><?php echo $stats['pending_requests']; ?></p>
                                            </div>
                                            <i class="fas fa-clock text-3xl opacity-75"></i>
                                        </div>
                                    </div>
                                    <div class="bg-gradient-to-r from-purple-500 to-purple-600 rounded-lg p-4 text-white">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <p class="text-purple-100 text-sm">Departments</p>
                                                <p class="text-2xl font-bold"><?php echo count($department_stats); ?></p>
                                            </div>
                                            <i class="fas fa-building text-3xl opacity-75"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Trends Tab -->
                            <div id="trends-content" class="analytics-content hidden">
                                <div class="bg-slate-700/50 rounded-lg p-4">
                                    <h4 class="text-lg font-semibold text-white mb-4">Monthly Trends</h4>
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-slate-600">
                                            <thead class="bg-slate-600">
                                                <tr>
                                                    <th class="px-4 py-2 text-left text-xs font-medium text-slate-300 uppercase">Month</th>
                                                    <th class="px-4 py-2 text-left text-xs font-medium text-slate-300 uppercase">Total</th>
                                                    <th class="px-4 py-2 text-left text-xs font-medium text-slate-300 uppercase">Approved</th>
                                                    <th class="px-4 py-2 text-left text-xs font-medium text-slate-300 uppercase">Rejected</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-slate-800 divide-y divide-slate-600">
                                                <?php foreach ($monthly_trends as $trend): ?>
                                                <tr>
                                                    <td class="px-4 py-2 text-sm text-slate-300"><?php echo $trend['month']; ?></td>
                                                    <td class="px-4 py-2 text-sm text-white"><?php echo $trend['total_requests']; ?></td>
                                                    <td class="px-4 py-2 text-sm text-green-400"><?php echo $trend['approved_requests']; ?></td>
                                                    <td class="px-4 py-2 text-sm text-red-400"><?php echo $trend['rejected_requests']; ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Departments Tab -->
                            <div id="departments-content" class="analytics-content hidden">
                                <div class="bg-slate-700/50 rounded-lg p-4">
                                    <h4 class="text-lg font-semibold text-white mb-4">Department Performance</h4>
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-slate-600">
                                            <thead class="bg-slate-600">
                                                <tr>
                                                    <th class="px-4 py-2 text-left text-xs font-medium text-slate-300 uppercase">Department</th>
                                                    <th class="px-4 py-2 text-left text-xs font-medium text-slate-300 uppercase">Total</th>
                                                    <th class="px-4 py-2 text-left text-xs font-medium text-slate-300 uppercase">Approved</th>
                                                    <th class="px-4 py-2 text-left text-xs font-medium text-slate-300 uppercase">Rejected</th>
                                                    <th class="px-4 py-2 text-left text-xs font-medium text-slate-300 uppercase">Pending</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-slate-800 divide-y divide-slate-600">
                                                <?php foreach ($department_stats as $dept): ?>
                                                <tr>
                                                    <td class="px-4 py-2 text-sm font-medium text-white"><?php echo htmlspecialchars($dept['department']); ?></td>
                                                    <td class="px-4 py-2 text-sm text-slate-300"><?php echo $dept['total_requests']; ?></td>
                                                    <td class="px-4 py-2 text-sm text-green-400"><?php echo $dept['approved_requests']; ?></td>
                                                    <td class="px-4 py-2 text-sm text-red-400"><?php echo $dept['rejected_requests']; ?></td>
                                                    <td class="px-4 py-2 text-sm text-yellow-400"><?php echo $dept['pending_requests']; ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Employees Tab -->
                            <div id="employees-content" class="analytics-content hidden">
                                <div class="bg-slate-700/50 rounded-lg p-4">
                                    <h4 class="text-lg font-semibold text-white mb-4">Leave Types Distribution</h4>
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-slate-600">
                                            <thead class="bg-slate-600">
                                                <tr>
                                                    <th class="px-4 py-2 text-left text-xs font-medium text-slate-300 uppercase">Leave Type</th>
                                                    <th class="px-4 py-2 text-left text-xs font-medium text-slate-300 uppercase">Total</th>
                                                    <th class="px-4 py-2 text-left text-xs font-medium text-slate-300 uppercase">Approved</th>
                                                    <th class="px-4 py-2 text-left text-xs font-medium text-slate-300 uppercase">Rejected</th>
                                                    <th class="px-4 py-2 text-left text-xs font-medium text-slate-300 uppercase">Pending</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-slate-800 divide-y divide-slate-600">
                                                <?php foreach ($leave_type_stats as $type): ?>
                                                <tr>
                                                    <td class="px-4 py-2 text-sm font-medium text-white"><?php echo htmlspecialchars($type['leave_type']); ?></td>
                                                    <td class="px-4 py-2 text-sm text-slate-300"><?php echo $type['total_requests']; ?></td>
                                                    <td class="px-4 py-2 text-sm text-green-400"><?php echo $type['approved_requests']; ?></td>
                                                    <td class="px-4 py-2 text-sm text-red-400"><?php echo $type['rejected_requests']; ?></td>
                                                    <td class="px-4 py-2 text-sm text-yellow-400"><?php echo $type['pending_requests']; ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="bg-slate-800 rounded-2xl p-6 border border-slate-700 hover:border-slate-600/50 transition-all duration-300">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-slate-400 text-sm font-medium">Total Requests</p>
                                <p class="text-2xl font-bold text-white"><?php echo $stats['total_requests']; ?></p>
                            </div>
                            <div class="w-12 h-12 bg-primary/20 rounded-xl flex items-center justify-center">
                                <i class="fas fa-list text-primary text-xl"></i>
                            </div>
                        </div>
                    </div>
                    <div class="bg-slate-800 rounded-2xl p-6 border border-slate-700 hover:border-slate-600/50 transition-all duration-300">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-slate-400 text-sm font-medium">Pending</p>
                                <p class="text-2xl font-bold text-white"><?php echo $stats['pending_requests']; ?></p>
                            </div>
                            <div class="w-12 h-12 bg-yellow-500/20 rounded-xl flex items-center justify-center">
                                <i class="fas fa-clock text-yellow-400 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-slate-800 rounded-2xl p-6 border border-slate-700 hover:border-slate-600/50 transition-all duration-300">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-slate-400 text-sm font-medium">Approved</p>
                                <p class="text-2xl font-bold text-white"><?php echo $stats['approved_requests']; ?></p>
                            </div>
                            <div class="w-12 h-12 bg-green-500/20 rounded-xl flex items-center justify-center">
                                <i class="fas fa-check-circle text-green-400 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-slate-800 rounded-2xl p-6 border border-slate-700 hover:border-slate-600/50 transition-all duration-300">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-slate-400 text-sm font-medium">Rejected</p>
                                <p class="text-2xl font-bold text-white"><?php echo $stats['rejected_requests']; ?></p>
                            </div>
                            <div class="w-12 h-12 bg-red-500/20 rounded-xl flex items-center justify-center">
                                <i class="fas fa-times-circle text-red-400 text-xl"></i>
                            </div>
                        </div>
                    </div>
                </div>

            <!-- Charts Row -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-chart-pie me-2"></i>Leave Requests by Department
                            </h5>
                        </div>
                        <div class="card-body">
                            <canvas id="departmentChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-chart-bar me-2"></i>Leave Requests by Type
                            </h5>
                        </div>
                        <div class="card-body">
                            <canvas id="leaveTypeChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Monthly Trends -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-chart-line me-2"></i>Monthly Trends
                            </h5>
                        </div>
                        <div class="card-body">
                            <canvas id="monthlyTrendsChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detailed Tables -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-building me-2"></i>Department Summary
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Department</th>
                                            <th>Total</th>
                                            <th>Approved</th>
                                            <th>Rejected</th>
                                            <th>Pending</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($department_stats as $dept): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($dept['department']); ?></td>
                                            <td><?php echo $dept['total_requests']; ?></td>
                                            <td><span class="badge bg-success"><?php echo $dept['approved_requests']; ?></span></td>
                                            <td><span class="badge bg-danger"><?php echo $dept['rejected_requests']; ?></span></td>
                                            <td><span class="badge bg-warning"><?php echo $dept['pending_requests']; ?></span></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-calendar-alt me-2"></i>Leave Type Summary
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Leave Type</th>
                                            <th>Total</th>
                                            <th>Approved</th>
                                            <th>Rejected</th>
                                            <th>Pending</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($leave_type_stats as $type): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($type['leave_type']); ?></td>
                                            <td><?php echo $type['total_requests']; ?></td>
                                            <td><span class="badge bg-success"><?php echo $type['approved_requests']; ?></span></td>
                                            <td><span class="badge bg-danger"><?php echo $type['rejected_requests']; ?></span></td>
                                            <td><span class="badge bg-warning"><?php echo $type['pending_requests']; ?></span></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../../../../assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>

        // Department Chart
        const departmentCtx = document.getElementById('departmentChart').getContext('2d');
        new Chart(departmentCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($department_stats, 'department')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($department_stats, 'total_requests')); ?>,
                    backgroundColor: [
                        '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF',
                        '#FF9F40', '#FF6384', '#C9CBCF', '#4BC0C0', '#FF6384'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Leave Type Chart
        const leaveTypeCtx = document.getElementById('leaveTypeChart').getContext('2d');
        new Chart(leaveTypeCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($leave_type_stats, 'leave_type')); ?>,
                datasets: [{
                    label: 'Total Requests',
                    data: <?php echo json_encode(array_column($leave_type_stats, 'total_requests')); ?>,
                    backgroundColor: '#36A2EB'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Monthly Trends Chart
        const monthlyCtx = document.getElementById('monthlyTrendsChart').getContext('2d');
        new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($monthly_trends, 'month')); ?>,
                datasets: [{
                    label: 'Total Requests',
                    data: <?php echo json_encode(array_column($monthly_trends, 'total_requests')); ?>,
                    borderColor: '#36A2EB',
                    backgroundColor: 'rgba(54, 162, 235, 0.1)',
                    tension: 0.1
                }, {
                    label: 'Approved',
                    data: <?php echo json_encode(array_column($monthly_trends, 'approved_requests')); ?>,
                    borderColor: '#4BC0C0',
                    backgroundColor: 'rgba(75, 192, 192, 0.1)',
                    tension: 0.1
                }, {
                    label: 'Rejected',
                    data: <?php echo json_encode(array_column($monthly_trends, 'rejected_requests')); ?>,
                    borderColor: '#FF6384',
                    backgroundColor: 'rgba(255, 99, 132, 0.1)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
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
        });
    </script>
            </div>
        </main>
    </div>
</body>
</html> 