<?php
session_start();
require_once '../config/database.php';

// Auto-process emails when internet is available
require_once '../includes/auto_email_processor.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/index.php');
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

// Count employees with low leave utilization (less than 50% of any leave type)
$currentYear = date('Y');
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT e.id) as low_utilization_count
    FROM employees e
    LEFT JOIN (
        SELECT employee_id, SUM(DATEDIFF(end_date, start_date) + 1) as days_used
        FROM leave_requests 
        WHERE leave_type = 'vacation' AND YEAR(start_date) = ? AND dept_head_approval = 'approved' AND director_approval = 'approved'
        GROUP BY employee_id
    ) vacation_used ON e.id = vacation_used.employee_id
    LEFT JOIN (
        SELECT employee_id, SUM(DATEDIFF(end_date, start_date) + 1) as days_used
        FROM leave_requests 
        WHERE leave_type = 'sick' AND YEAR(start_date) = ? AND dept_head_approval = 'approved' AND director_approval = 'approved'
        GROUP BY employee_id
    ) sick_used ON e.id = sick_used.employee_id
    WHERE e.role = 'employee'
    AND (
        (e.vacation_leave_balance > 0 AND COALESCE(vacation_used.days_used, 0) / e.vacation_leave_balance < 0.5) OR
        (e.sick_leave_balance > 0 AND COALESCE(sick_used.days_used, 0) / e.sick_leave_balance < 0.5)
    )
");
$stmt->execute([$currentYear, $currentYear]);
$low_utilization_count = $stmt->fetchColumn();

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

// Fetch all leave requests for calendar
$stmt = $pdo->prepare("
    SELECT lr.*, e.name as employee_name,
           CASE 
               WHEN lr.dept_head_approval = 'rejected' OR lr.director_approval = 'rejected' THEN 'rejected'
               WHEN lr.dept_head_approval = 'approved' AND lr.director_approval = 'approved' THEN 'approved'
               ELSE 'pending'
           END as final_approval_status
    FROM leave_requests lr 
    JOIN employees e ON lr.employee_id = e.id 
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
        <link rel="stylesheet" href="../assets/css/tailwind.css">
        <!-- Font Awesome Local - No internet required! -->
    <link rel="stylesheet" href="../assets/libs/fontawesome/css/all.min.css">
        <!-- Font Awesome Local - No internet required! -->
        
        <link rel="stylesheet" href="../assets/css/style.css">
        <link rel="stylesheet" href="../assets/css/admin_style.css">
        <link rel="stylesheet" href="../assets/css/dark-theme.css">
        <script src="../assets/libs/chartjs/chart.umd.min.js"></script>
        <style>
            /* Remove conflicting z-index - let unified navbar handle it */
        </style>
</head>
<body class="bg-slate-900 text-white">
    <?php include '../includes/unified_navbar.php'; ?>

    <div class="flex">
        <!-- Left Sidebar -->
        <aside id="sidebar" class="fixed left-0 top-16 h-screen w-64 bg-slate-900 border-r border-slate-800 overflow-y-auto z-40 transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out">
            <nav class="p-4 space-y-2">
                <!-- Active Navigation Item -->
                <a href="admin_dashboard.php" class="flex items-center space-x-3 px-4 py-3 text-white bg-blue-500/20 rounded-lg border border-blue-500/30">
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
                    
                    <a href="view_chart.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
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
        <main class="flex-1 md:ml-64 p-4 md:p-6 pt-24">
            <div class="max-w-7xl mx-auto">
                <!-- Welcome Section -->
                <div class="mb-10 mt-16">
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-5">
                            <div class="w-16 h-16 bg-gradient-to-r from-cyan-600 to-blue-600 rounded-xl flex items-center justify-center shadow-lg flex-shrink-0">
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
                                <p class="text-slate-400 text-sm font-semibold uppercase tracking-wider">Low Utilization</p>
                                <h2 class="text-4xl font-bold text-white mt-2"><?php echo $low_utilization_count; ?></h2>
                            </div>
                            <div class="w-12 h-12 bg-slate-600/20 rounded-lg flex items-center justify-center">
                                <i class="fas fa-exclamation-triangle text-slate-400 text-xl"></i>
                            </div>
                        </div>
                        <div class="flex items-center gap-1 text-slate-400 text-sm font-medium">
                            <i class="fas fa-bell"></i>
                            <span>Need alerts</span>
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
                                                        <?php echo ucfirst($request['leave_type']); ?>
                                                    </span>
                                                    <div class="text-slate-400 text-xs mt-1">
                                                        <?php echo date('M d', strtotime($request['start_date'])); ?> - <?php echo date('M d', strtotime($request['end_date'])); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 md:px-6 py-4 hidden sm:table-cell">
                                        <span class="bg-blue-500/20 text-blue-400 px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wide">
                                            <?php echo ucfirst($request['leave_type']); ?>
                                        </span>
                                    </td>
                                    <td class="px-3 md:px-6 py-4 text-slate-300 text-sm hidden md:table-cell"><?php echo date('M d, Y', strtotime($request['start_date'])); ?></td>
                                    <td class="px-3 md:px-6 py-4 text-slate-300 text-sm hidden md:table-cell"><?php echo date('M d, Y', strtotime($request['end_date'])); ?></td>
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
                                <a href="view_chart.php" class="inline-flex items-center gap-2 bg-primary hover:bg-primary/90 text-white px-4 py-2 rounded-lg transition-colors">
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
            fetch('clean_api.php?id=' + leaveId)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.text();
                })
                .then(text => {
                    // Clean the response text to remove any warnings
                    const cleanText = text.replace(/^[^{]*/, '');
                    return JSON.parse(cleanText);
                })
                .then(data => {
                    if (data.success && data.leave) {
                        const leave = data.leave;
                        modal.querySelector('.text-center').innerHTML = `
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <h6 class="text-lg font-semibold text-white mb-3">Employee Information</h6>
                                    <div class="space-y-2">
                                        <p class="text-slate-300"><strong class="text-white">Name:</strong> ${leave.employee_name || 'N/A'}</p>
                                        <p class="text-slate-300"><strong class="text-white">Department:</strong> ${leave.employee_department || 'N/A'}</p>
                                        <p class="text-slate-300"><strong class="text-white">Email:</strong> ${leave.employee_email || 'N/A'}</p>
                                        <p class="text-slate-300"><strong class="text-white">Position:</strong> ${leave.employee_position || 'N/A'}</p>
                                        <p class="text-slate-300"><strong class="text-white">Contact:</strong> ${leave.employee_contact || 'N/A'}</p>
                                    </div>
                                </div>
                                <div>
                                    <h6 class="text-lg font-semibold text-white mb-3">Leave Details</h6>
                                    <div class="space-y-2">
                                        <p class="text-slate-300"><strong class="text-white">Type:</strong> ${leave.leave_type || 'N/A'}</p>
                                        <p class="text-slate-300"><strong class="text-white">Start Date:</strong> ${leave.start_date || 'N/A'}</p>
                                        <p class="text-slate-300"><strong class="text-white">End Date:</strong> ${leave.end_date || 'N/A'}</p>
                                        <p class="text-slate-300"><strong class="text-white">Days Requested:</strong> ${leave.days_requested || 'N/A'}</p>
                                        <p class="text-slate-300"><strong class="text-white">Status:</strong> 
                                            <span class="px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wide ${leave.final_approval_status === 'Approved' ? 'bg-green-500/20 text-green-400' : (leave.final_approval_status === 'Pending' ? 'bg-yellow-500/20 text-yellow-400' : 'bg-red-500/20 text-red-400')}">${leave.final_approval_status || 'N/A'}</span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-6">
                                <h6 class="text-lg font-semibold text-white mb-3">Reason</h6>
                                <p class="text-slate-300 bg-slate-700 p-4 rounded-lg">${leave.reason || 'N/A'}</p>
                            </div>
                            <div class="mt-6">
                                <h6 class="text-lg font-semibold text-white mb-3">Approval Status</h6>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="bg-slate-700 p-4 rounded-lg">
                                        <h7 class="text-md font-semibold text-white mb-2">Department Head</h7>
                                        <p class="text-slate-300 text-sm"><strong>Status:</strong> ${leave.dept_head_approval || 'Pending'}</p>
                                        <p class="text-slate-300 text-sm"><strong>Approved By:</strong> ${leave.dept_head_approved_by || 'N/A'}</p>
                                        <p class="text-slate-300 text-sm"><strong>Date:</strong> ${leave.dept_head_approved_at || 'N/A'}</p>
                                        ${leave.dept_head_rejection_reason && leave.dept_head_rejection_reason !== 'N/A' ? `<p class="text-slate-300 text-sm"><strong>Reason:</strong> ${leave.dept_head_rejection_reason}</p>` : ''}
                                    </div>
                                    <div class="bg-slate-700 p-4 rounded-lg">
                                        <h7 class="text-md font-semibold text-white mb-2">Director</h7>
                                        <p class="text-slate-300 text-sm"><strong>Status:</strong> ${leave.director_approval || 'Pending'}</p>
                                        <p class="text-slate-300 text-sm"><strong>Approved By:</strong> ${leave.director_approved_by || 'N/A'}</p>
                                        <p class="text-slate-300 text-sm"><strong>Date:</strong> ${leave.director_approved_at || 'N/A'}</p>
                                        ${leave.director_rejection_reason && leave.director_rejection_reason !== 'N/A' ? `<p class="text-slate-300 text-sm"><strong>Reason:</strong> ${leave.director_rejection_reason}</p>` : ''}
                                    </div>
                                </div>
                            </div>
                            <div class="mt-6 flex justify-end">
                                <button onclick="closeModal()" class="bg-slate-600 hover:bg-slate-500 text-white px-6 py-2 rounded-lg transition-colors">
                                    Close
                                </button>
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