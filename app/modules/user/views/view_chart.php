<?php
session_start();
require_once '../../../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../../../auth/views/login.php');
    exit();
}

// Get user information
$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$employee = $stmt->fetch();

// Get user's leave requests for chart data (only approved leaves)
$stmt = $pdo->prepare("
    SELECT 
        lr.*,
        CASE 
            WHEN lr.approved_days IS NOT NULL AND lr.approved_days > 0 
            THEN lr.approved_days
            ELSE DATEDIFF(lr.end_date, lr.start_date) + 1 
        END as actual_days_approved
    FROM leave_requests lr 
    WHERE lr.employee_id = ? 
    AND lr.status = 'approved'
    ORDER BY lr.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$leave_requests = $stmt->fetchAll();

// Calculate leave statistics for charts (only approved leaves)
$leaveStats = [
    'total_approved' => count($leave_requests),
    'approved' => count($leave_requests), // All are approved since we filtered
    'pending' => 0,
    'rejected' => 0,
    'by_type' => [],
    'by_month' => []
];

foreach ($leave_requests as $request) {
    // By type counts
    $type = $request['leave_type'];
    if (!isset($leaveStats['by_type'][$type])) {
        $leaveStats['by_type'][$type] = 0;
    }
    $leaveStats['by_type'][$type]++;
    
    // By month counts
    $month = date('Y-m', strtotime($request['created_at']));
    if (!isset($leaveStats['by_month'][$month])) {
        $leaveStats['by_month'][$month] = 0;
    }
    $leaveStats['by_month'][$month]++;
}

// Leave type mapping for display
$leaveTypeMapping = [
    'vacation' => 'Vacation',
    'special_privilege' => 'Special Privilege',
    'sick' => 'Sick Leave',
    'study' => 'Study Leave',
    'solo_parent' => 'Solo Parent',
    'vawc' => 'VAWC Leave',
    'rehabilitation' => 'Rehabilitation',
    'special_women' => 'Special Women',
    'special_emergency' => 'Emergency',
    'adoption' => 'Adoption',
    'maternity' => 'Maternity',
    'paternity' => 'Paternity',
    'mandatory' => 'Mandatory'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ELMS - Leave Chart</title>
    <link rel="stylesheet" href="../../../../assets/css/tailwind.css">
    <link rel="stylesheet" href="../../../../assets/libs/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../../../../assets/css/style.css">
    <link rel="stylesheet" href="../../../../assets/css/dark-theme.css">
    <script src="../../../../assets/libs/chartjs/chart.umd.min.js"></script>
</head>
<body class="bg-slate-900 text-white min-h-screen" data-user-role="user">
    <?php include '../../../../includes/unified_navbar.php'; ?>

    <div class="flex">
        <!-- Left Sidebar -->
        <aside id="sidebar" class="fixed left-0 top-16 h-[calc(100vh-4rem)] w-64 bg-slate-900 border-r border-slate-800 overflow-y-auto z-40">
            <nav class="p-4 space-y-2">
                <!-- Navigation Item -->
                <a href="dashboard.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700/50 rounded-lg transition-colors">
                    <i class="fas fa-tachometer-alt w-5"></i>
                    <span>Dashboard</span>
                </a>
                
                <div class="space-y-1">
                    <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider px-4 py-2">Leave Management</h3>
                    <a href="leave_history.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700/50 rounded-lg transition-colors">
                        <i class="fas fa-history w-5"></i>
                        <span>Leave History</span>
                    </a>
                    <a href="leave_credits.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700/50 rounded-lg transition-colors">
                        <i class="fas fa-calculator w-5"></i>
                        <span>Leave Credits</span>
                    </a>
                </div>
                
                <div class="space-y-1">
                    <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider px-4 py-2">Reports</h3>
                    <a href="calendar.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700/50 rounded-lg transition-colors">
                        <i class="fas fa-calendar-alt w-5"></i>
                        <span>Leave Chart</span>
                    </a>
                </div>
                
                <div class="space-y-1">
                    <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider px-4 py-2">Account</h3>
                    <a href="profile.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700/50 rounded-lg transition-colors">
                        <i class="fas fa-user w-5"></i>
                        <span>Profile</span>
                    </a>
                </div>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 ml-64 pt-24 px-6 pb-6">
            <div class="max-w-7xl mx-auto">
                <!-- Header -->
                <div class="mb-10 mt-16">
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 bg-gradient-to-r from-blue-500 to-cyan-500 rounded-2xl flex items-center justify-center">
                            <i class="fas fa-chart-line text-2xl text-white"></i>
                        </div>
                        <div>
                            <h1 class="text-3xl font-bold text-white mb-2">Leave Analytics</h1>
                            <p class="text-slate-400">Visualize your leave request patterns and statistics</p>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl border border-slate-700/50 p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-slate-400 text-sm">Total Approved</p>
                                <p class="text-2xl font-bold text-white"><?php echo $leaveStats['total_approved']; ?></p>
                            </div>
                            <div class="w-12 h-12 bg-blue-500/20 rounded-xl flex items-center justify-center">
                                <i class="fas fa-calendar-alt text-blue-400 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl border border-slate-700/50 p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-slate-400 text-sm">Approved</p>
                                <p class="text-2xl font-bold text-green-400"><?php echo $leaveStats['approved']; ?></p>
                            </div>
                            <div class="w-12 h-12 bg-green-500/20 rounded-xl flex items-center justify-center">
                                <i class="fas fa-check-circle text-green-400 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl border border-slate-700/50 p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-slate-400 text-sm">Pending</p>
                                <p class="text-2xl font-bold text-yellow-400">0</p>
                            </div>
                            <div class="w-12 h-12 bg-yellow-500/20 rounded-xl flex items-center justify-center">
                                <i class="fas fa-clock text-yellow-400 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl border border-slate-700/50 p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-slate-400 text-sm">Rejected</p>
                                <p class="text-2xl font-bold text-red-400">0</p>
                            </div>
                            <div class="w-12 h-12 bg-red-500/20 rounded-xl flex items-center justify-center">
                                <i class="fas fa-times-circle text-red-400 text-xl"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                    <!-- Leave Status Chart -->
                    <div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl border border-slate-700/50 p-6">
                        <h3 class="text-xl font-semibold text-white mb-6 flex items-center">
                            <i class="fas fa-chart-pie text-blue-400 mr-3"></i>
                            Leave Request Status
                        </h3>
                        <div class="chart-container" style="position: relative; height: 300px;">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>

                    <!-- Leave Type Chart -->
                    <div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl border border-slate-700/50 p-6">
                        <h3 class="text-xl font-semibold text-white mb-6 flex items-center">
                            <i class="fas fa-chart-bar text-green-400 mr-3"></i>
                            Leave Requests by Type
                        </h3>
                        <div class="chart-container" style="position: relative; height: 300px;">
                            <canvas id="typeChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Monthly Trends Chart -->
                <div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl border border-slate-700/50 p-6 mb-8">
                    <h3 class="text-xl font-semibold text-white mb-6 flex items-center">
                        <i class="fas fa-chart-line text-purple-400 mr-3"></i>
                        Monthly Leave Trends
                    </h3>
                    <div class="chart-container" style="position: relative; height: 400px;">
                        <canvas id="monthlyChart"></canvas>
                    </div>
                </div>

                <!-- Leave Type Usage Table -->
                <div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl border border-slate-700/50 p-6">
                    <h3 class="text-xl font-semibold text-white mb-6 flex items-center">
                        <i class="fas fa-table text-orange-400 mr-3"></i>
                        Leave Type Breakdown
                    </h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-slate-700">
                                    <th class="text-left py-3 px-4 text-slate-300 font-semibold">Leave Type</th>
                                    <th class="text-right py-3 px-4 text-slate-300 font-semibold">Requests</th>
                                    <th class="text-right py-3 px-4 text-slate-300 font-semibold">Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $totalRequests = $leaveStats['total_approved'];
                                foreach ($leaveStats['by_type'] as $type => $count): 
                                    $percentage = $totalRequests > 0 ? ($count / $totalRequests) * 100 : 0;
                                ?>
                                <tr class="border-b border-slate-700/50 hover:bg-slate-700/30 transition-colors">
                                    <td class="py-3 px-4 text-white"><?php echo $leaveTypeMapping[$type] ?? ucfirst(str_replace('_', ' ', $type)); ?></td>
                                    <td class="py-3 px-4 text-right text-slate-300"><?php echo $count; ?></td>
                                    <td class="py-3 px-4 text-right">
                                        <span class="text-slate-300"><?php echo number_format($percentage, 1); ?>%</span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Status Chart (Doughnut)
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Approved', 'Pending', 'Rejected'],
                datasets: [{
                    data: [<?php echo $leaveStats['approved']; ?>, 0, 0],
                    backgroundColor: ['#10B981', '#F59E0B', '#EF4444'],
                    borderWidth: 2,
                    borderColor: '#1F2937'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: '#E5E7EB',
                            padding: 20
                        }
                    }
                }
            }
        });

        // Type Chart (Bar)
        const typeCtx = document.getElementById('typeChart').getContext('2d');
        const typeLabels = [<?php echo implode(',', array_map(function($type) use ($leaveTypeMapping) { return "'" . ($leaveTypeMapping[$type] ?? ucfirst(str_replace('_', ' ', $type))) . "'"; }, array_keys($leaveStats['by_type']))); ?>];
        const typeData = [<?php echo implode(',', array_values($leaveStats['by_type'])); ?>];
        
        new Chart(typeCtx, {
            type: 'bar',
            data: {
                labels: typeLabels,
                datasets: [{
                    label: 'Number of Requests',
                    data: typeData,
                    backgroundColor: '#3B82F6',
                    borderColor: '#1D4ED8',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: {
                            color: '#E5E7EB'
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            color: '#9CA3AF'
                        },
                        grid: {
                            color: '#374151'
                        }
                    },
                    x: {
                        ticks: {
                            color: '#9CA3AF'
                        },
                        grid: {
                            color: '#374151'
                        }
                    }
                }
            }
        });

        // Monthly Chart (Line)
        const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
        const monthlyLabels = [<?php echo implode(',', array_map(function($month) { return "'" . date('M Y', strtotime($month . '-01')) . "'"; }, array_keys($leaveStats['by_month']))); ?>];
        const monthlyData = [<?php echo implode(',', array_values($leaveStats['by_month'])); ?>];
        
        new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: monthlyLabels,
                datasets: [{
                    label: 'Leave Requests',
                    data: monthlyData,
                    borderColor: '#8B5CF6',
                    backgroundColor: 'rgba(139, 92, 246, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: {
                            color: '#E5E7EB'
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            color: '#9CA3AF'
                        },
                        grid: {
                            color: '#374151'
                        }
                    },
                    x: {
                        ticks: {
                            color: '#9CA3AF'
                        },
                        grid: {
                            color: '#374151'
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
