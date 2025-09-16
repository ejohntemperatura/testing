<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin or manager
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin','manager','director'])) {
    header('Location: ../auth/index.php');
    exit();
}

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

// Handle export functionality
if (isset($_POST['export'])) {
    $export_type = $_POST['export_type'];
    
    if ($export_type === 'leave_requests') {
        // Export leave requests
        $stmt = $pdo->prepare("
            SELECT 
                e.name as employee_name,
                e.department,
                lr.leave_type,
                lr.start_date,
                lr.end_date,
                lr.reason,
                lr.status,
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
        fputcsv($output, ['Employee', 'Department', 'Leave Type', 'Start Date', 'End Date', 'Reason', 'Status', 'Created At']);
        
        foreach ($export_data as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - ELMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#0891b2',    // Cyan-600 - Main brand color
                        secondary: '#f97316',  // Orange-500 - Accent/action color
                        accent: '#06b6d4',     // Cyan-500 - Highlight color
                        background: '#0f172a', // Slate-900 - Main background
                        foreground: '#f8fafc', // Slate-50 - Primary text
                        muted: '#64748b'       // Slate-500 - Secondary text
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-900 text-white">
    <!-- Sidebar -->
    <?php 
        $role = $_SESSION['role'];
        $panelTitle = $role === 'director' ? 'Director Panel' : ($role === 'manager' ? 'Department Head' : 'Admin Panel');
        $dashboardLink = $role === 'director' ? 'director_head_dashboard.php' : ($role === 'manager' ? 'department_head_dashboard.php' : 'admin_dashboard.php');
    ?>
    <!-- Top Navigation Bar -->
    <nav class="bg-slate-800 border-b border-slate-700 fixed top-0 left-0 right-0 z-50 h-16">
        <div class="px-6 py-4 h-full">
            <div class="flex items-center justify-between h-full">
                <!-- Logo and Title -->
                <div class="flex items-center space-x-4">
                    <div class="flex items-center space-x-2">
                        <div class="w-8 h-8 bg-gradient-to-r from-primary to-accent rounded-lg flex items-center justify-center">
                            <i class="fas fa-file-alt text-white text-sm"></i>
                        </div>
                        <span class="text-xl font-bold text-white">ELMS Admin</span>
                    </div>
                </div>
                
                <!-- User Menu -->
                <div class="flex items-center space-x-4">
                    <a href="../auth/logout.php" class="text-slate-300 hover:text-white transition-colors flex items-center space-x-2">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="flex">
        <!-- Left Sidebar -->
        <aside class="fixed left-0 top-16 h-screen w-64 bg-slate-800 border-r border-slate-700 overflow-y-auto z-40">
            <nav class="p-4 space-y-2">
                <!-- Other Navigation Items -->
                <a href="<?php echo $dashboardLink; ?>" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                    <i class="fas fa-tachometer-alt w-5"></i>
                    <span>Dashboard</span>
                </a>
                
                <a href="manage_user.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                    <i class="fas fa-users-cog w-5"></i>
                    <span>Manage User</span>
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
                
                <a href="view_chart.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                    <i class="fas fa-calendar w-5"></i>
                    <span>Leave Chart</span>
                </a>
                
                <!-- Active Navigation Item -->
                <a href="reports.php" class="flex items-center space-x-3 px-4 py-3 text-white bg-primary/20 rounded-lg border border-primary/30">
                    <i class="fas fa-file-alt w-5"></i>
                    <span>Reports</span>
                </a>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <main class="flex-1 ml-64 p-6">
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
                <div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl border border-slate-700/50 overflow-hidden mb-8">
                    <div class="px-6 py-4 border-b border-slate-700/50 bg-slate-700/30">
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

                <!-- Export Section -->
                <div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl border border-slate-700/50 overflow-hidden mb-8">
                    <div class="px-6 py-4 border-b border-slate-700/50 bg-slate-700/30">
                        <h3 class="text-xl font-semibold text-white flex items-center">
                            <i class="fas fa-download text-primary mr-3"></i>Export Reports
                        </h3>
                    </div>
                    <div class="p-6">
                        <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="export_type" class="block text-sm font-semibold text-slate-300 mb-2">Report Type</label>
                                <select name="export_type" id="export_type" required class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                    <option value="">Select Report Type</option>
                                    <option value="leave_requests">Leave Requests Report</option>
                                    <option value="employee_summary">Employee Summary Report</option>
                                    <option value="department_summary">Department Summary Report</option>
                                </select>
                            </div>
                            <div class="flex items-end">
                                <button type="submit" name="export" class="w-full bg-green-500 hover:bg-green-600 text-white font-semibold py-3 px-6 rounded-xl transition-colors flex items-center justify-center">
                                    <i class="fas fa-download mr-2"></i>Export CSV
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-6 border border-slate-700/50 hover:border-slate-600/50 transition-all duration-300">
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
                    <div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-6 border border-slate-700/50 hover:border-slate-600/50 transition-all duration-300">
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
                    
                    <div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-6 border border-slate-700/50 hover:border-slate-600/50 transition-all duration-300">
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
                    
                    <div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-6 border border-slate-700/50 hover:border-slate-600/50 transition-all duration-300">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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