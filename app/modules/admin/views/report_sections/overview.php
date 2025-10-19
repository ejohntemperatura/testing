<?php
$stats = $reportData['system_stats'] ?? [];
$departmentStats = $reportData['department_stats'] ?? [];
$leaveTypeStats = $reportData['leave_type_stats'] ?? [];
$monthlyTrends = $reportData['monthly_trends'] ?? [];
?>

<!-- Key Metrics -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="metric-card rounded-2xl p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-400 text-sm font-medium">Total Requests</p>
                <p class="text-3xl font-bold text-white"><?php echo $stats['total_requests'] ?? 0; ?></p>
                <p class="text-slate-400 text-xs mt-1"><?php echo $stats['total_days_requested'] ?? 0; ?> total days</p>
            </div>
            <div class="w-12 h-12 bg-blue-500/20 rounded-xl flex items-center justify-center">
                <i class="fas fa-list text-blue-400 text-xl"></i>
            </div>
        </div>
    </div>
    
    <div class="metric-card rounded-2xl p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-400 text-sm font-medium">Approval Rate</p>
                <p class="text-3xl font-bold text-white"><?php echo $stats['approval_rate'] ?? 0; ?>%</p>
                <p class="text-green-400 text-xs mt-1"><?php echo $stats['approved_requests'] ?? 0; ?> approved</p>
            </div>
            <div class="w-12 h-12 bg-green-500/20 rounded-xl flex items-center justify-center">
                <i class="fas fa-check-circle text-green-400 text-xl"></i>
            </div>
        </div>
    </div>
    
    <div class="metric-card rounded-2xl p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-400 text-sm font-medium">Pending Requests</p>
                <p class="text-3xl font-bold text-white"><?php echo $stats['pending_requests'] ?? 0; ?></p>
                <p class="text-yellow-400 text-xs mt-1"><?php echo $stats['pending_rate'] ?? 0; ?>% of total</p>
            </div>
            <div class="w-12 h-12 bg-yellow-500/20 rounded-xl flex items-center justify-center">
                <i class="fas fa-clock text-yellow-400 text-xl"></i>
            </div>
        </div>
    </div>
    
    <div class="metric-card rounded-2xl p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-400 text-sm font-medium">Avg Days/Request</p>
                <p class="text-3xl font-bold text-white"><?php echo number_format($stats['avg_days_per_request'] ?? 0, 1); ?></p>
                <p class="text-slate-400 text-xs mt-1">per request</p>
            </div>
            <div class="w-12 h-12 bg-purple-500/20 rounded-xl flex items-center justify-center">
                <i class="fas fa-chart-line text-purple-400 text-xl"></i>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
    <!-- Department Chart -->
    <div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-700 bg-slate-700/30">
            <h3 class="text-lg font-semibold text-white flex items-center">
                <i class="fas fa-building text-blue-400 mr-2"></i>Requests by Department
            </h3>
        </div>
        <div class="p-6">
            <div class="chart-container">
                <canvas id="departmentChart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Leave Type Chart -->
    <div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-700 bg-slate-700/30">
            <h3 class="text-lg font-semibold text-white flex items-center">
                <i class="fas fa-calendar-alt text-green-400 mr-2"></i>Requests by Leave Type
            </h3>
        </div>
        <div class="p-6">
            <div class="chart-container">
                <canvas id="leaveTypeChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Monthly Trends Chart -->
<div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden mb-8">
    <div class="px-6 py-4 border-b border-slate-700 bg-slate-700/30">
        <h3 class="text-lg font-semibold text-white flex items-center">
            <i class="fas fa-chart-line text-purple-400 mr-2"></i>Monthly Trends
        </h3>
    </div>
    <div class="p-6">
        <div class="chart-container">
            <canvas id="monthlyTrendsChart"></canvas>
        </div>
    </div>
</div>

<!-- Data Tables -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    <!-- Department Summary Table -->
    <div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-700 bg-slate-700/30">
            <h3 class="text-lg font-semibold text-white flex items-center">
                <i class="fas fa-building text-blue-400 mr-2"></i>Department Summary
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-600">
                <thead class="bg-slate-700">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-300 uppercase">Department</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-300 uppercase">Employees</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-300 uppercase">Requests</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-300 uppercase">Approved</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-300 uppercase">Pending</th>
                    </tr>
                </thead>
                <tbody class="bg-slate-800 divide-y divide-slate-600">
                    <?php foreach ($departmentStats as $dept): ?>
                    <tr class="hover:bg-slate-700/50">
                        <td class="px-4 py-3 text-sm font-medium text-white"><?php echo htmlspecialchars($dept['department']); ?></td>
                        <td class="px-4 py-3 text-sm text-slate-300"><?php echo $dept['total_employees']; ?></td>
                        <td class="px-4 py-3 text-sm text-slate-300"><?php echo $dept['total_requests']; ?></td>
                        <td class="px-4 py-3 text-sm text-green-400"><?php echo $dept['approved_requests']; ?></td>
                        <td class="px-4 py-3 text-sm text-yellow-400"><?php echo $dept['pending_requests']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Leave Type Summary Table -->
    <div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-700 bg-slate-700/30">
            <h3 class="text-lg font-semibold text-white flex items-center">
                <i class="fas fa-calendar-alt text-green-400 mr-2"></i>Leave Type Summary
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-600">
                <thead class="bg-slate-700">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-300 uppercase">Leave Type</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-300 uppercase">Requests</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-300 uppercase">Approved</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-300 uppercase">Days</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-300 uppercase">Avg Days</th>
                    </tr>
                </thead>
                <tbody class="bg-slate-800 divide-y divide-slate-600">
                    <?php foreach ($leaveTypeStats as $type): ?>
                    <tr class="hover:bg-slate-700/50">
                        <td class="px-4 py-3 text-sm font-medium text-white"><?php echo htmlspecialchars($type['leave_type']); ?></td>
                        <td class="px-4 py-3 text-sm text-slate-300"><?php echo $type['total_requests']; ?></td>
                        <td class="px-4 py-3 text-sm text-green-400"><?php echo $type['approved_requests']; ?></td>
                        <td class="px-4 py-3 text-sm text-slate-300"><?php echo $type['total_days_requested']; ?></td>
                        <td class="px-4 py-3 text-sm text-slate-300"><?php echo number_format($type['avg_days_per_request'], 1); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Department Chart
const departmentCtx = document.getElementById('departmentChart').getContext('2d');
new Chart(departmentCtx, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_column($departmentStats, 'department')); ?>,
        datasets: [{
            data: <?php echo json_encode(array_column($departmentStats, 'total_requests')); ?>,
            backgroundColor: [
                '#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6',
                '#06B6D4', '#84CC16', '#F97316', '#EC4899', '#6B7280'
            ],
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

// Leave Type Chart
const leaveTypeCtx = document.getElementById('leaveTypeChart').getContext('2d');
new Chart(leaveTypeCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($leaveTypeStats, 'leave_type')); ?>,
        datasets: [{
            label: 'Total Requests',
            data: <?php echo json_encode(array_column($leaveTypeStats, 'total_requests')); ?>,
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

// Monthly Trends Chart
const monthlyCtx = document.getElementById('monthlyTrendsChart').getContext('2d');
new Chart(monthlyCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_column($monthlyTrends, 'month')); ?>,
        datasets: [{
            label: 'Total Requests',
            data: <?php echo json_encode(array_column($monthlyTrends, 'total_requests')); ?>,
            borderColor: '#3B82F6',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            tension: 0.4,
            fill: true
        }, {
            label: 'Approved',
            data: <?php echo json_encode(array_column($monthlyTrends, 'approved_requests')); ?>,
            borderColor: '#10B981',
            backgroundColor: 'rgba(16, 185, 129, 0.1)',
            tension: 0.4,
            fill: true
        }, {
            label: 'Rejected',
            data: <?php echo json_encode(array_column($monthlyTrends, 'rejected_requests')); ?>,
            borderColor: '#EF4444',
            backgroundColor: 'rgba(239, 68, 68, 0.1)',
            tension: 0.4,
            fill: true
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

