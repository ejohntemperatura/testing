<?php
session_start();
require_once '../../../../config/database.php';

// Auto-process emails when internet is available
require_once '../../../../app/core/services/auto_email_processor.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../../../auth/views/login.php');
    exit();
}

// Fetch admin details
$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ? AND role = 'admin'");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch();

// Fetch statistics
$stmt = $pdo->query("SELECT COUNT(*) FROM employees");
$total_employees = $stmt->fetchColumn();

$stmt = $pdo->query("
    SELECT COUNT(*) FROM leave_requests 
    WHERE NOT (dept_head_approval = 'approved' AND director_approval = 'approved')
    AND NOT (dept_head_approval = 'rejected' OR director_approval = 'rejected')
");
$pending_requests = $stmt->fetchColumn();

$stmt = $pdo->query("
    SELECT COUNT(*) FROM leave_requests 
    WHERE dept_head_approval = 'approved' AND director_approval = 'approved'
");
$approved_requests = $stmt->fetchColumn();

$stmt = $pdo->query("
    SELECT COUNT(*) FROM leave_requests 
    WHERE dept_head_approval = 'rejected' OR director_approval = 'rejected'
");
$rejected_requests = $stmt->fetchColumn();

// Get enhanced alert statistics
require_once '../../../../app/core/services/EnhancedLeaveAlertService.php';
$alertService = new EnhancedLeaveAlertService($pdo);
$alertStats = $alertService->getAlertStatistics();
$low_utilization_count = $alertStats['total_employees_with_alerts'];

// Get leave types configuration for proper display names
require_once '../../../../config/leave_types.php';
$leaveTypes = getLeaveTypes();

// Fetch recent leave requests
$stmt = $pdo->prepare("
    SELECT lr.*, e.name as employee_name,
           CASE 
               WHEN lr.dept_head_approval = 'rejected' OR lr.director_approval = 'rejected' THEN 'rejected'
               WHEN lr.dept_head_approval = 'approved' AND lr.director_approval = 'approved' THEN 'approved'
               ELSE 'pending'
           END as final_approval_status
    FROM leave_requests lr 
    JOIN employees e ON lr.employee_id = e.id 
    ORDER BY lr.created_at DESC 
    LIMIT 10
");
$stmt->execute();
$recent_requests = $stmt->fetchAll();

// Fetch APPROVED leave requests for calendar only
$stmt = $pdo->prepare("
    SELECT lr.*, e.name as employee_name,
           CASE 
               WHEN lr.dept_head_approval = 'rejected' OR lr.director_approval = 'rejected' THEN 'rejected'
               WHEN lr.dept_head_approval = 'approved' AND lr.director_approval = 'approved' THEN 'approved'
               ELSE 'pending'
           END as final_approval_status
    FROM leave_requests lr 
    JOIN employees e ON lr.employee_id = e.id 
    WHERE lr.status = 'approved'
    ORDER BY lr.start_date ASC
");
$stmt->execute();
$leave_requests = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ELMS - Admin Dashboard</title>
        <!-- OFFLINE Tailwind CSS - No internet required! -->
        <link rel="stylesheet" href="../../../../assets/css/tailwind.css">
        <!-- Font Awesome Local - No internet required! -->
    <link rel="stylesheet" href="../../../../assets/libs/fontawesome/css/all.min.css">
        <!-- Font Awesome Local - No internet required! -->
        
        <link rel="stylesheet" href="../../../../assets/css/style.css">
        <link rel="stylesheet" href="../../../../assets/css/admin_style.css">
        <link rel="stylesheet" href="../../../../assets/css/dark-theme.css">
        <script src="../../../../assets/libs/chartjs/chart.umd.min.js"></script>
        <style>
            /* Remove conflicting z-index - let unified navbar handle it */
        </style>
</head>
<body class="bg-slate-900 text-white" data-user-role="admin">
    <?php include '../../../../includes/unified_navbar.php'; ?>

    <div class="flex">
        <!-- Left Sidebar -->
        <aside id="sidebar" class="fixed left-0 top-16 h-screen w-64 bg-slate-900 border-r border-slate-800 overflow-y-auto z-40 transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out">
            <nav class="p-4 space-y-2">
                <!-- Active Navigation Item -->
                <a href="dashboard.php" class="flex items-center space-x-3 px-4 py-3 text-white bg-blue-500/20 rounded-lg border border-blue-500/30">
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
                        <span class="bg-slate-600 text-white text-xs px-2 py-1 rounded-full" id="pendingLeaveBadge" style="display: none;">0</span>
                    </a>
                    
                    <a href="leave_alerts.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
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
        <main class="flex-1 md:ml-64 p-6 pt-24">
            <div class="max-w-7xl mx-auto">
                <!-- Welcome Section -->
                <div class="mb-10 mt-16">
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-5">
                            <div class="w-16 h-16 bg-gradient-to-r from-slate-600 to-slate-700 rounded-xl flex items-center justify-center shadow-lg flex-shrink-0">
                                <i class="fas fa-user-shield text-2xl text-white"></i>
                            </div>
                            <div class="flex-1">
                                <h1 class="text-3xl font-bold text-white mb-2 leading-tight">Welcome, <?php echo htmlspecialchars($admin['name']); ?>!</h1>
                                <p class="text-slate-400 text-lg leading-relaxed flex items-center">
                                    <i class="fas fa-calendar-alt mr-2"></i>
                                    Today is <?php echo date('l, F j, Y'); ?> • <?php echo date('H:i A'); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 md:gap-6 mb-6 md:mb-8">
                    <!-- Total Users Card -->
                    <a href="manage_user.php" class="block bg-slate-800 rounded-lg p-6 border border-slate-700 hover:border-slate-600 transition-all duration-200 hover:scale-[1.02]">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <p class="text-slate-400 text-sm font-semibold uppercase tracking-wider">Total Users</p>
                                <h2 class="text-4xl font-bold text-white mt-2"><?php echo $total_employees; ?></h2>
                            </div>
                            <div class="w-12 h-12 bg-primary/20 rounded-lg flex items-center justify-center">
                                <i class="fas fa-users text-primary text-xl"></i>
                            </div>
                        </div>
                        <div class="flex items-center gap-1 text-green-400 text-sm font-medium">
                            <i class="fas fa-arrow-up"></i>
                            <span>Active users</span>
                        </div>
                    </a>
                    
                    <!-- Pending Requests Card -->
                    <a href="leave_management.php?status=pending" class="block bg-slate-800 rounded-lg p-6 border border-slate-700 hover:border-slate-600 transition-all duration-200 hover:scale-[1.02]">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <p class="text-slate-400 text-sm font-semibold uppercase tracking-wider">Pending Requests</p>
                                <h2 class="text-4xl font-bold text-white mt-2"><?php echo $pending_requests; ?></h2>
                            </div>
                            <div class="w-12 h-12 bg-yellow-500/20 rounded-lg flex items-center justify-center">
                                <i class="fas fa-clock text-yellow-500 text-xl"></i>
                            </div>
                        </div>
                        <div class="flex items-center gap-1 text-slate-400 text-sm font-medium">
                            <i class="fas fa-hourglass-half"></i>
                            <span>Awaiting review</span>
                        </div>
                    </a>
                    
                    <!-- Approved Requests Card -->
                    <a href="leave_management.php?status=approved" class="block bg-slate-800 rounded-lg p-6 border border-slate-700 hover:border-slate-600 transition-all duration-200 hover:scale-[1.02]">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <p class="text-slate-400 text-sm font-semibold uppercase tracking-wider">Approved Requests</p>
                                <h2 class="text-4xl font-bold text-white mt-2"><?php echo $approved_requests; ?></h2>
                            </div>
                            <div class="w-12 h-12 bg-slate-600/20 rounded-lg flex items-center justify-center">
                                <i class="fas fa-check-circle text-slate-400 text-xl"></i>
                            </div>
                        </div>
                        <div class="flex items-center gap-1 text-slate-400 text-sm font-medium">
                            <i class="fas fa-arrow-up"></i>
                            <span>This month</span>
                        </div>
                    </a>
                    
                    <!-- Rejected Requests Card -->
                    <a href="leave_management.php?status=rejected" class="block bg-slate-800 rounded-lg p-6 border border-slate-700 hover:border-slate-600 transition-all duration-200 hover:scale-[1.02]">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <p class="text-slate-400 text-sm font-semibold uppercase tracking-wider">Rejected Requests</p>
                                <h2 class="text-4xl font-bold text-white mt-2"><?php echo $rejected_requests; ?></h2>
                            </div>
                            <div class="w-12 h-12 bg-slate-600/20 rounded-lg flex items-center justify-center">
                                <i class="fas fa-times-circle text-slate-400 text-xl"></i>
                            </div>
                        </div>
                        <div class="flex items-center gap-1 text-slate-400 text-sm font-medium">
                            <i class="fas fa-arrow-down"></i>
                            <span>This month</span>
                        </div>
                    </a>
                    
                    <!-- Low Utilization Alert Card -->
                    <a href="leave_alerts.php" class="block bg-slate-800 rounded-lg p-6 border border-slate-700 hover:border-slate-600 transition-all duration-200 hover:scale-[1.02]">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <p class="text-slate-400 text-sm font-semibold uppercase tracking-wider">Total Alerts</p>
                                <h2 class="text-4xl font-bold text-white mt-2"><?php echo $alertStats['total_employees_with_alerts']; ?></h2>
                            </div>
                            <div class="w-12 h-12 bg-blue-500/20 rounded-lg flex items-center justify-center">
                                <i class="fas fa-bell text-blue-400 text-xl"></i>
                            </div>
                        </div>
                        <div class="flex items-center gap-1 text-blue-400 text-sm font-medium">
                            <i class="fas fa-users"></i>
                            <span>Need attention</span>
                        </div>
                    </a>
                    
                </div>

                <!-- Recent Leave Requests Table -->
                <div class="bg-slate-800 rounded-lg border border-slate-700 overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-700 bg-slate-700">
                        <h3 class="text-xl font-semibold text-white m-0 flex items-center gap-3">
                            <i class="fas fa-list text-primary"></i>
                            Recent Leave Requests
                        </h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[800px]">
                            <thead class="bg-slate-700">
                                <tr>
                                    <th class="px-3 md:px-6 py-4 text-left text-xs font-semibold text-slate-300 uppercase tracking-wider">Employee</th>
                                    <th class="px-3 md:px-6 py-4 text-left text-xs font-semibold text-slate-300 uppercase tracking-wider hidden sm:table-cell">Type</th>
                                    <th class="px-3 md:px-6 py-4 text-left text-xs font-semibold text-slate-300 uppercase tracking-wider hidden md:table-cell">Start Date</th>
                                    <th class="px-3 md:px-6 py-4 text-left text-xs font-semibold text-slate-300 uppercase tracking-wider hidden md:table-cell">End Date</th>
                                    <th class="px-3 md:px-6 py-4 text-left text-xs font-semibold text-slate-300 uppercase tracking-wider">
                                        <div class="flex items-center gap-2">
                                            <span>Status</span>
                                            <button type="button" 
                                                    onclick="showStatusInfoHelp()"
                                                    title="View Status Information"
                                                    class="text-blue-400 hover:text-blue-300 transition-colors">
                                                <i class="fas fa-info-circle text-xs"></i>
                                            </button>
                                        </div>
                                    </th>
                                    <th class="px-3 md:px-6 py-4 text-left text-xs font-semibold text-slate-300 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-700">
                                <?php foreach ($recent_requests as $request): ?>
                                <tr class="hover:bg-slate-700/50 transition-colors">
                                    <td class="px-3 md:px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 md:w-10 md:h-10 bg-primary rounded-full flex items-center justify-center text-white">
                                                <i class="fas fa-user text-xs md:text-sm"></i>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <div class="font-medium text-white text-sm truncate"><?php echo htmlspecialchars($request['employee_name']); ?></div>
                                                <small class="text-slate-400 text-xs">ID: #<?php echo $request['employee_id']; ?></small>
                                                <!-- Mobile: Show additional info -->
                                                <div class="sm:hidden mt-1">
                                                    <span class="bg-blue-500/20 text-blue-400 px-2 py-1 rounded-full text-xs font-semibold uppercase tracking-wide">
                                                        <?php echo getLeaveTypeDisplayName($request['leave_type'], $request['original_leave_type'] ?? null, $leaveTypes); ?>
                                                    </span>
                                                    <div class="text-slate-400 text-xs mt-1">
                                                        <?php 
                                                        // Use approved end date if available and different from requested days, otherwise original end date
                                                        if ($request['status'] === 'approved' && $request['approved_days'] && $request['approved_days'] != $request['days_requested']) {
                                                            $approved_end_date = date('Y-m-d', strtotime($request['start_date'] . ' +' . ($request['approved_days'] - 1) . ' days'));
                                                            echo date('M d', strtotime($request['start_date'])) . ' - ' . date('M d', strtotime($approved_end_date));
                                                        } else {
                                                            echo date('M d', strtotime($request['start_date'])) . ' - ' . date('M d', strtotime($request['end_date']));
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 md:px-6 py-4 hidden sm:table-cell">
                                        <span class="bg-blue-500/20 text-blue-400 px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wide">
                                            <?php echo getLeaveTypeDisplayName($request['leave_type'], $request['original_leave_type'] ?? null, $leaveTypes); ?>
                                        </span>
                                    </td>
                                    <td class="px-3 md:px-6 py-4 text-slate-300 text-sm hidden md:table-cell"><?php echo date('M d, Y', strtotime($request['start_date'])); ?></td>
                                    <td class="px-3 md:px-6 py-4 text-slate-300 text-sm hidden md:table-cell">
                                        <?php 
                                        // Use approved end date if available and different from requested days, otherwise original end date
                                        if ($request['status'] === 'approved' && $request['approved_days'] && $request['approved_days'] != $request['days_requested']) {
                                            $approved_end_date = date('Y-m-d', strtotime($request['start_date'] . ' +' . ($request['approved_days'] - 1) . ' days'));
                                            echo date('M d, Y', strtotime($approved_end_date));
                                        } else {
                                            echo date('M d, Y', strtotime($request['end_date']));
                                        }
                                        ?>
                                    </td>
                                    <td class="px-3 md:px-6 py-4">
                                        <span class="px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wide <?php 
                                            $final_status = $request['final_approval_status'] ?? $request['status'];
                                            echo $final_status == 'approved' ? 'bg-slate-600/20 text-slate-300' : 
                                                ($final_status == 'pending' ? 'bg-slate-500/20 text-slate-300' : 'bg-slate-700/20 text-slate-400'); 
                                        ?>">
                                            <?php echo ucfirst($final_status); ?>
                                        </span>
                                    </td>
                                    <td class="px-3 md:px-6 py-4">
                                        <div class="flex gap-1 md:gap-2">
                                            <button onclick="viewRequestDetails(<?php echo $request['id']; ?>)" 
                                                    title="View Details"
                                                    class="bg-primary hover:bg-primary/90 text-white p-1.5 md:p-2 rounded-lg transition-colors">
                                                <i class="fas fa-eye text-xs"></i>
                                            </button>
                                            <a href="leave_management.php" 
                                               title="Manage Leave Requests"
                                               class="bg-slate-600 hover:bg-slate-500 text-white p-1.5 md:p-2 rounded-lg transition-colors inline-flex items-center">
                                                <i class="fas fa-cog text-xs"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Leave Calendar Section -->
                <div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden mb-8">
                    <div class="px-6 py-4 border-b border-slate-700/50">
                        <h3 class="text-xl font-semibold text-white flex items-center gap-3">
                            <i class="fas fa-calendar-alt text-primary"></i>
                            Leave Calendar
                        </h3>
                        <p class="text-slate-400 text-sm mt-1">View all leave requests in calendar format</p>
                    </div>
                    <div class="p-6">
                        <div id="calendar" class="h-96 bg-slate-700/30 rounded-lg flex items-center justify-center">
                            <div class="text-center">
                                <i class="fas fa-calendar-alt text-4xl text-slate-500 mb-4"></i>
                                <p class="text-slate-400 mb-4">Calendar view will be available here</p>
                                <a href="calendar.php" class="inline-flex items-center gap-2 bg-primary hover:bg-primary/90 text-white px-4 py-2 rounded-lg transition-colors">
                                    <i class="fas fa-external-link-alt"></i>
                                    View Full Calendar
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Mobile Search Overlay -->
    <div id="mobileSearchOverlay" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
        <div class="bg-slate-800 p-4">
            <div class="flex items-center space-x-4">
                <div class="flex-1 relative">
                    <input type="text" 
                           placeholder="Search..." 
                           class="w-full bg-slate-700 border border-slate-600 rounded-lg px-4 py-3 pl-10 text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-slate-400"></i>
                </div>
                <button onclick="toggleSearch()" class="text-slate-400 hover:text-white">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
    </div>

    <script>
        // Pass leave types data to JavaScript
        window.leaveTypes = <?php echo json_encode($leaveTypes); ?>;
        
        // Helper function to get leave type display name in JavaScript
        function getLeaveTypeDisplayNameJS(leaveType, originalLeaveType = null) {
            const leaveTypes = window.leaveTypes;
            if (!leaveTypes) return leaveType;
            
            // Check if leave is without pay
            let isWithoutPay = false;
            
            // If leave_type is explicitly 'without_pay', it's without pay
            if (leaveType === 'without_pay') {
                isWithoutPay = true;
            }
            // If original_leave_type exists and current type is 'without_pay' or empty, it was converted to without pay
            else if (originalLeaveType && (leaveType === 'without_pay' || !leaveType)) {
                isWithoutPay = true;
            }
            // Check if the current leave type is inherently without pay
            else if (leaveTypes[leaveType] && leaveTypes[leaveType].without_pay) {
                isWithoutPay = true;
            }
            // Check if the original leave type was inherently without pay
            else if (originalLeaveType && leaveTypes[originalLeaveType] && leaveTypes[originalLeaveType].without_pay) {
                isWithoutPay = true;
            }
            
            // Determine the base leave type to display
            let baseType = null;
            if (originalLeaveType && (leaveType === 'without_pay' || !leaveType)) {
                // Use original type if it was converted to without pay
                baseType = originalLeaveType;
            } else {
                // Use current type
                baseType = leaveType;
            }
            
            // Get the display name
            if (leaveTypes[baseType]) {
                const leaveTypeConfig = leaveTypes[baseType];
                
                if (isWithoutPay) {
                    // Show name with without pay indicator
                    if (leaveTypeConfig.name_with_note) {
                        return leaveTypeConfig.name_with_note;
                    } else {
                        return leaveTypeConfig.name + ' (Without Pay)';
                    }
                } else {
                    // Show regular name
                    return leaveTypeConfig.name;
                }
            } else {
                // Fallback for unknown types
                const displayName = baseType.charAt(0).toUpperCase() + baseType.slice(1).replace(/_/g, ' ');
                return isWithoutPay ? displayName + ' (Without Pay)' : displayName;
            }
        }
        
        // Toggle functions for navigation
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('-translate-x-full');
        }

        function toggleSearch() {
            const searchOverlay = document.getElementById('mobileSearchOverlay');
            searchOverlay.classList.toggle('hidden');
        }



        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const sidebarButton = event.target.closest('[onclick="toggleSidebar()"]');
            
            if (window.innerWidth < 768 && !sidebar.contains(event.target) && !sidebarButton) {
                sidebar.classList.add('-translate-x-full');
            }
        });

        // Handle window resize
        window.addEventListener('resize', function() {
            const sidebar = document.getElementById('sidebar');
            if (window.innerWidth >= 768) {
                sidebar.classList.remove('-translate-x-full');
            } else {
                sidebar.classList.add('-translate-x-full');
            }
        });

        // Helper function for status badge styling
        function getStatusBadgeClass(status) {
            const colorMap = {
                'approved': 'bg-green-500/20 text-green-400 border-green-500/30',
                'rejected': 'bg-red-500/20 text-red-400 border-red-500/30',
                'pending': 'bg-yellow-500/20 text-yellow-400 border-yellow-500/30'
            };
            return colorMap[status] || 'bg-slate-500/20 text-slate-400 border-slate-500/30';
        }

        function viewRequestDetails(leaveId) {
            // Create modal to show detailed information
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
            modal.id = 'requestModal';
            modal.innerHTML = `
                <div class="bg-slate-800 rounded-lg p-6 w-full max-w-2xl mx-4 max-h-screen overflow-y-auto">
                    <div class="flex items-center justify-between mb-4">
                        <h5 class="text-xl font-semibold text-white">Leave Request Details</h5>
                        <button type="button" class="text-slate-400 hover:text-white" onclick="closeModal()">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                    <div class="text-center py-8">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto"></div>
                        <p class="mt-2 text-slate-400">Loading request details...</p>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            
            // Fetch request details
            fetch('../api/get_leave_request_details.php?id=' + leaveId)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success && data.leave_request) {
                        const leave = data.leave_request;
                        modal.querySelector('.text-center').innerHTML = `
                            <div class="space-y-6">
                                <!-- Employee Information -->
                                <div class="bg-slate-700/30 rounded-xl p-6 border border-slate-600/30">
                                    <h4 class="text-lg font-semibold text-white mb-4 flex items-center">
                                        <i class="fas fa-user text-primary mr-3"></i>Employee Information
                                    </h4>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="text-sm font-medium text-slate-400">Name</label>
                                            <p class="text-white font-semibold">${leave.employee_name || 'N/A'}</p>
                                        </div>
                                        <div>
                                            <label class="text-sm font-medium text-slate-400">Position</label>
                                            <p class="text-white">${leave.position || 'N/A'}</p>
                                        </div>
                                        <div>
                                            <label class="text-sm font-medium text-slate-400">Department</label>
                                            <p class="text-white">${leave.department || 'N/A'}</p>
                                        </div>
                                        <div>
                                            <label class="text-sm font-medium text-slate-400">Email</label>
                                            <p class="text-white">${leave.employee_email || 'N/A'}</p>
                                        </div>
                                        ${leave.employee_contact ? `
                                        <div>
                                            <label class="text-sm font-medium text-slate-400">Contact</label>
                                            <p class="text-white">${leave.employee_contact}</p>
                                        </div>
                                        ` : ''}
                                        <div>
                                            <label class="text-sm font-medium text-slate-400">Request ID</label>
                                            <p class="text-white font-mono">#${leave.id || 'N/A'}</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Leave Details -->
                                <div class="bg-slate-700/30 rounded-xl p-6 border border-slate-600/30">
                                    <h4 class="text-lg font-semibold text-white mb-4 flex items-center">
                                        <i class="fas fa-calendar-alt text-primary mr-3"></i>Leave Details
                                    </h4>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="text-sm font-medium text-slate-400">Leave Type</label>
                                            <p class="text-white">${leave.leave_type}</p>
                                        </div>
                                        <div>
                                            <label class="text-sm font-medium text-slate-400">Days Requested</label>
                                            <p class="text-white">${leave.days_requested || 'N/A'} day${leave.days_requested != 1 ? 's' : ''}</p>
                                        </div>
                                        ${leave.approved_days && leave.approved_days > 0 && leave.status === 'approved' ? `
                                        <div>
                                            <label class="text-sm font-medium text-slate-400">Days Approved</label>
                                            <p class="text-green-400 font-semibold">
                                                ${leave.approved_days} day${leave.approved_days != 1 ? 's' : ''}
                                                ${leave.pay_status ? `<span class="text-xs ml-1">(${leave.pay_status.replace('_', ' ')})</span>` : ''}
                                            </p>
                                        </div>
                                        ` : ''}
                                        <div>
                                            <label class="text-sm font-medium text-slate-400">Start Date</label>
                                            <p class="text-white">${leave.start_date ? new Date(leave.start_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'N/A'}</p>
                                        </div>
                                        <div>
                                            <label class="text-sm font-medium text-slate-400">End Date</label>
                                            <p class="text-white">${(() => {
                                                // Use approved end date if available, otherwise original end date
                                                if (leave.status === 'approved' && leave.approved_days && leave.approved_days !== leave.days_requested) {
                                                    const startDate = new Date(leave.start_date);
                                                    const approvedEndDate = new Date(startDate);
                                                    approvedEndDate.setDate(startDate.getDate() + (leave.approved_days - 1));
                                                    return approvedEndDate.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
                                                } else {
                                                    return leave.end_date ? new Date(leave.end_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'N/A';
                                                }
                                            })()}</p>
                                        </div>
                                        <div class="md:col-span-2">
                                            <label class="text-sm font-medium text-slate-400">Reason</label>
                                            <p class="text-white bg-slate-800/50 rounded-lg p-3 mt-1">${leave.reason || 'No reason provided'}</p>
                                        </div>
                                    </div>
                                </div>

                                ${(leave.is_late == 1 || leave.is_late === '1' || leave.is_late === true) && leave.late_justification ? `
                                <!-- Late Application Details -->
                                <div class="bg-yellow-500/20 border border-yellow-500/30 rounded-xl p-6">
                                    <h4 class="text-lg font-semibold text-yellow-400 mb-4 flex items-center">
                                        <i class="fas fa-exclamation-triangle mr-3"></i>Late Application Details
                                    </h4>
                                    <div class="space-y-3">
                                        <div>
                                            <label class="text-sm font-medium text-yellow-300">Late Justification</label>
                                            <p class="text-white bg-slate-800/50 rounded-lg p-3 mt-1">${leave.late_justification}</p>
                                        </div>
                                        <div>
                                            <label class="text-sm font-medium text-yellow-300">Application Type</label>
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-500/20 text-yellow-400 border border-yellow-500/30">
                                                Late Application
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                ` : ''}

                                ${leave.location_type ? `
                                <!-- Location Details -->
                                <div class="bg-slate-700/30 rounded-xl p-6 border border-slate-600/30">
                                    <h4 class="text-lg font-semibold text-white mb-4 flex items-center">
                                        <i class="fas fa-map-marker-alt text-primary mr-3"></i>Location Details
                                    </h4>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="text-sm font-medium text-slate-400">Location Type</label>
                                            <p class="text-white">${leave.location_type ? leave.location_type.charAt(0).toUpperCase() + leave.location_type.slice(1) : 'N/A'}</p>
                                        </div>
                                        ${leave.location_specify ? `
                                        <div>
                                            <label class="text-sm font-medium text-slate-400">Specific Location</label>
                                            <p class="text-white">${leave.location_specify}</p>
                                        </div>
                                        ` : ''}
                                    </div>
                                </div>
                                ` : ''}

                                ${leave.medical_condition || leave.medical_certificate_path ? `
                                <!-- Medical Details -->
                                <div class="bg-slate-700/30 rounded-xl p-6 border border-slate-600/30">
                                    <h4 class="text-lg font-semibold text-white mb-4 flex items-center">
                                        <i class="fas fa-medkit text-primary mr-3"></i>Medical Details
                                    </h4>
                                    <div class="space-y-3">
                                        ${leave.medical_condition ? `
                                        <div>
                                            <label class="text-sm font-medium text-slate-400">Medical Condition</label>
                                            <p class="text-white">${leave.medical_condition}</p>
                                        </div>
                                        ` : ''}
                                        ${leave.illness_specify ? `
                                        <div>
                                            <label class="text-sm font-medium text-slate-400">Illness Specification</label>
                                            <p class="text-white">${leave.illness_specify}</p>
                                        </div>
                                        ` : ''}
                                        ${leave.medical_certificate_path ? `
                                        <div>
                                            <label class="text-sm font-medium text-slate-400">Medical Certificate</label>
                                            <div class="flex items-center space-x-3 mt-2">
                                                <i class="fas fa-file-medical text-green-400"></i>
                                                <a href="../../api/view_medical_certificate.php?file=${encodeURIComponent(leave.medical_certificate_path)}" 
                                                   target="_blank" 
                                                   class="text-blue-400 hover:text-blue-300 underline">
                                                    View Medical Certificate
                                                </a>
                                            </div>
                                        </div>
                                        ` : ''}
                                    </div>
                                </div>
                                ` : ''}

                                ${leave.special_women_condition ? `
                                <!-- Special Women Condition -->
                                <div class="bg-pink-500/20 border border-pink-500/30 rounded-xl p-6">
                                    <h4 class="text-lg font-semibold text-pink-400 mb-4 flex items-center">
                                        <i class="fas fa-female mr-3"></i>Special Women Condition
                                    </h4>
                                    <div>
                                        <label class="text-sm font-medium text-pink-300">Condition</label>
                                        <p class="text-white">${leave.special_women_condition}</p>
                                    </div>
                                </div>
                                ` : ''}

                                ${leave.study_type ? `
                                <!-- Study Details -->
                                <div class="bg-blue-500/20 border border-blue-500/30 rounded-xl p-6">
                                    <h4 class="text-lg font-semibold text-blue-400 mb-4 flex items-center">
                                        <i class="fas fa-graduation-cap mr-3"></i>Study Details
                                    </h4>
                                    <div>
                                        <label class="text-sm font-medium text-blue-300">Study Type</label>
                                        <p class="text-white">${leave.study_type}</p>
                                    </div>
                                </div>
                                ` : ''}

                                <!-- Approval Status -->
                                <div class="bg-slate-700/30 rounded-xl p-6 border border-slate-600/30">
                                    <h4 class="text-lg font-semibold text-white mb-4 flex items-center">
                                        <i class="fas fa-clipboard-check text-primary mr-3"></i>Approval Status
                                    </h4>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div class="text-center">
                                            <label class="text-sm font-medium text-slate-400 mb-2 block">Department Head</label>
                                            <div class="mb-2">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ${getStatusBadgeClass(leave.dept_head_approval || 'pending')} border">
                                                    ${(leave.dept_head_approval || 'pending').charAt(0).toUpperCase() + (leave.dept_head_approval || 'pending').slice(1)}
                                                </span>
                                            </div>
                                            ${leave.dept_head_name ? `<p class="text-xs text-slate-400">by ${leave.dept_head_name}</p>` : ''}
                                            ${leave.dept_head_approved_at ? `<p class="text-xs text-slate-400">${new Date(leave.dept_head_approved_at).toLocaleDateString()}</p>` : ''}
                                            ${leave.dept_head_rejection_reason ? `<p class="text-xs text-red-400 mt-1">${leave.dept_head_rejection_reason}</p>` : ''}
                                        </div>
                                        <div class="text-center">
                                            <label class="text-sm font-medium text-slate-400 mb-2 block">Director</label>
                                            <div class="mb-2">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ${getStatusBadgeClass((leave.dept_head_approval === 'rejected') ? 'rejected' : (leave.director_approval || 'pending'))} border">
                                                    ${((leave.dept_head_approval === 'rejected') ? 'rejected' : (leave.director_approval || 'pending')).charAt(0).toUpperCase() + ((leave.dept_head_approval === 'rejected') ? 'rejected' : (leave.director_approval || 'pending')).slice(1)}
                                                </span>
                                            </div>
                                            ${leave.director_name ? `<p class="text-xs text-slate-400">by ${leave.director_name}</p>` : ''}
                                            ${leave.director_approved_at ? `<p class="text-xs text-slate-400">${new Date(leave.director_approved_at).toLocaleDateString()}</p>` : ''}
                                            ${leave.director_rejection_reason ? `<p class="text-xs text-red-400 mt-1">${leave.director_rejection_reason}</p>` : ''}
                                        </div>
                                    </div>
                                    <div class="mt-6 pt-4 border-t border-slate-600/30">
                                        <div class="text-center">
                                            <label class="text-sm font-medium text-slate-400 mb-2 block">Final Status</label>
                                            <div class="mb-2">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ${getStatusBadgeClass(leave.status || 'pending')} border">
                                                    ${(leave.status || 'pending').charAt(0).toUpperCase() + (leave.status || 'pending').slice(1)}
                                                </span>
                                            </div>
                                            <p class="text-xs text-slate-400">Based on Department Head and Director approvals</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Request Information -->
                                <div class="bg-slate-700/30 rounded-xl p-6 border border-slate-600/30">
                                    <h4 class="text-lg font-semibold text-white mb-4 flex items-center">
                                        <i class="fas fa-info-circle text-primary mr-3"></i>Request Information
                                    </h4>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="text-sm font-medium text-slate-400">Submitted On</label>
                                            <p class="text-white">${leave.created_at ? new Date(leave.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : 'N/A'}</p>
                                        </div>
                                        <div>
                                            <label class="text-sm font-medium text-slate-400">Final Status</label>
                                            <p class="text-white">${leave.status ? leave.status.charAt(0).toUpperCase() + leave.status.slice(1) : 'Pending'}</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Actions -->
                                <div class="flex justify-end space-x-3">
                                    <button onclick="closeModal()" class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg transition-colors">
                                        Close
                                    </button>
                                    ${(leave.dept_head_approval === 'approved' && leave.director_approval === 'approved') ? 
                                        `<a href="print_leave_request.php?id=${leave.id}" target="_blank" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                                            <i class="fas fa-print mr-2"></i>Print
                                        </a>` : ''
                                    }
                                </div>
                            </div>
                        `;
                    } else {
                        throw new Error(data.error || 'Failed to load leave details');
                    }
                })
                .catch(error => {
                    modal.querySelector('.text-center').innerHTML = `
                        <div class="bg-red-500/20 border border-red-500/30 rounded-lg p-4">
                            <div class="flex items-center">
                                <i class="fas fa-exclamation-triangle text-red-400 mr-2"></i>
                                <span class="text-red-400">Error loading request details: ${error}</span>
                            </div>
                        </div>
                    `;
                });
        }

        function closeModal() {
            const modal = document.getElementById('requestModal');
            if (modal) {
                modal.remove();
            }
        }

        function showNotification(message, type) {
            const bgColor = type === 'success' ? 'bg-green-500/20 border-green-500/30' : 'bg-red-500/20 border-red-500/30';
            const textColor = type === 'success' ? 'text-green-400' : 'text-red-400';
            const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle';
            
            const notification = document.createElement('div');
            notification.className = `fixed top-5 right-5 z-50 min-w-80 max-w-md ${bgColor} border rounded-lg p-4 shadow-lg`;
            notification.innerHTML = `
                <div class="flex items-center">
                    <i class="fas ${icon} ${textColor} mr-3"></i>
                    <span class="${textColor} flex-1">${message}</span>
                    <button onclick="this.parentElement.parentElement.remove()" class="text-slate-400 hover:text-white ml-2">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            document.body.appendChild(notification);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 5000);
        }

        // Show status information help modal
        function showStatusInfoHelp() {
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
            modal.id = 'statusInfoHelpModal';
            modal.innerHTML = `
                <div class="bg-slate-800 rounded-lg p-6 w-full max-w-4xl mx-4 max-h-screen overflow-y-auto">
                    <div class="flex items-center justify-between mb-6">
                        <h5 class="text-2xl font-semibold text-white flex items-center">
                            <i class="fas fa-info-circle text-primary mr-3"></i>Status Information Guide
                        </h5>
                        <button type="button" class="text-slate-400 hover:text-white" onclick="closeStatusModal()">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                    
                    <div class="bg-blue-500/20 border border-blue-500/30 rounded-lg p-4 mb-6">
                        <h6 class="text-lg font-semibold text-blue-400 flex items-center mb-2">
                            <i class="fas fa-lightbulb mr-2"></i>Understanding Leave Request Status
                        </h6>
                        <p class="text-slate-300">This table shows the current status of leave requests in the system. Here's what each status means:</p>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <h6 class="text-yellow-400 text-lg font-semibold mb-3 flex items-center">
                                <i class="fas fa-clock mr-2"></i>Pending Status
                            </h6>
                            <div class="space-y-2 text-slate-300">
                                <p><strong class="text-white">Meaning:</strong> Leave request is waiting for approval</p>
                                <p><strong class="text-white">What you can do:</strong> Go to Leave Management to approve/reject</p>
                                <p><strong class="text-white">Next step:</strong> Use the settings button to access management</p>
                            </div>
                        </div>
                        <div>
                            <h6 class="text-green-400 text-lg font-semibold mb-3 flex items-center">
                                <i class="fas fa-check-circle mr-2"></i>Approved Status
                            </h6>
                            <div class="space-y-2 text-slate-300">
                                <p><strong class="text-white">Meaning:</strong> Leave request has been approved</p>
                                <p><strong class="text-white">What this means:</strong> Employee can take the leave</p>
                                <p><strong class="text-white">Note:</strong> Leave balance will be deducted</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="border-t border-slate-700 pt-6 mb-6"></div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <h6 class="text-red-400 text-lg font-semibold mb-3 flex items-center">
                                <i class="fas fa-times-circle mr-2"></i>Rejected Status
                            </h6>
                            <div class="space-y-2 text-slate-300">
                                <p><strong class="text-white">Meaning:</strong> Leave request has been rejected</p>
                                <p><strong class="text-white">What this means:</strong> Employee cannot take the leave</p>
                                <p><strong class="text-white">Note:</strong> Employee will be notified</p>
                            </div>
                        </div>
                        <div>
                            <h6 class="text-primary text-lg font-semibold mb-3 flex items-center">
                                <i class="fas fa-cog mr-2"></i>Leave Management
                            </h6>
                            <div class="space-y-2 text-slate-300">
                                <p><strong class="text-white">Purpose:</strong> Access full leave management system</p>
                                <p><strong class="text-white">Includes:</strong> Approve, reject, and manage all requests</p>
                                <p><strong class="text-white">Action:</strong> Click the settings button</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-yellow-500/20 border border-yellow-500/30 rounded-lg p-4 mb-6">
                        <h6 class="text-lg font-semibold text-yellow-400 flex items-center mb-3">
                            <i class="fas fa-exclamation-triangle mr-2"></i>Important Notes
                        </h6>
                        <ul class="text-slate-300 space-y-1">
                            <li>• Use the <strong class="text-white">Leave Management</strong> page to approve or reject requests</li>
                            <li>• This dashboard shows a summary view only</li>
                            <li>• All approval actions must be done in the management section</li>
                            <li>• All actions are logged for audit purposes</li>
                        </ul>
                    </div>
                    
                    <div class="flex justify-end">
                        <button onclick="closeStatusModal()" class="bg-slate-600 hover:bg-slate-500 text-white px-6 py-2 rounded-lg transition-colors">
                            Close
                        </button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }

        function closeStatusModal() {
            const modal = document.getElementById('statusInfoHelpModal');
            if (modal) {
                modal.remove();
            }
        }

        // Add click handlers for sidebar navigation
        document.addEventListener('DOMContentLoaded', function() {
            // Add active class to current page
            const currentPage = window.location.pathname.split('/').pop();
            const sidebarLinks = document.querySelectorAll('aside nav a');
            sidebarLinks.forEach(link => {
                if (link.getAttribute('href') === currentPage) {
                    link.classList.remove('text-slate-300', 'hover:text-white', 'hover:bg-slate-700');
                    link.classList.add('text-white', 'bg-primary/20', 'border', 'border-primary/30');
                }
            });
            
            // Admin dashboard loaded
        });

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
</body>
</html> 