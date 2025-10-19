<?php
$employeePerformance = $reportData['employee_performance'] ?? [];
$systemStats = $reportData['system_stats'] ?? [];
?>

<!-- Performance Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="metric-card rounded-2xl p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-400 text-sm font-medium">Total Employees</p>
                <p class="text-3xl font-bold text-white"><?php echo count($employeePerformance); ?></p>
                <p class="text-slate-400 text-xs mt-1">with leave requests</p>
            </div>
            <div class="w-12 h-12 bg-blue-500/20 rounded-xl flex items-center justify-center">
                <i class="fas fa-users text-blue-400 text-xl"></i>
            </div>
        </div>
    </div>
    
    <div class="metric-card rounded-2xl p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-400 text-sm font-medium">Avg Requests/Employee</p>
                <p class="text-3xl font-bold text-white"><?php echo count($employeePerformance) > 0 ? round(array_sum(array_column($employeePerformance, 'total_requests')) / count($employeePerformance), 1) : 0; ?></p>
                <p class="text-slate-400 text-xs mt-1">per employee</p>
            </div>
            <div class="w-12 h-12 bg-green-500/20 rounded-xl flex items-center justify-center">
                <i class="fas fa-chart-bar text-green-400 text-xl"></i>
            </div>
        </div>
    </div>
    
    <div class="metric-card rounded-2xl p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-400 text-sm font-medium">Top Performer</p>
                <p class="text-lg font-bold text-white"><?php echo !empty($employeePerformance) ? htmlspecialchars($employeePerformance[0]['name']) : 'N/A'; ?></p>
                <p class="text-slate-400 text-xs mt-1"><?php echo !empty($employeePerformance) ? $employeePerformance[0]['total_requests'] : 0; ?> requests</p>
            </div>
            <div class="w-12 h-12 bg-purple-500/20 rounded-xl flex items-center justify-center">
                <i class="fas fa-trophy text-purple-400 text-xl"></i>
            </div>
        </div>
    </div>
    
    <div class="metric-card rounded-2xl p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-400 text-sm font-medium">Total Days Used</p>
                <p class="text-3xl font-bold text-white"><?php echo array_sum(array_column($employeePerformance, 'total_days_requested')); ?></p>
                <p class="text-slate-400 text-xs mt-1">across all employees</p>
            </div>
            <div class="w-12 h-12 bg-orange-500/20 rounded-xl flex items-center justify-center">
                <i class="fas fa-calendar text-orange-400 text-xl"></i>
            </div>
        </div>
    </div>
</div>

<!-- Performance Charts -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
    <!-- Department Performance Chart -->
    <div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-700 bg-slate-700/30">
            <h3 class="text-lg font-semibold text-white flex items-center">
                <i class="fas fa-building text-blue-400 mr-2"></i>Department Performance
            </h3>
        </div>
        <div class="p-6">
            <div class="chart-container">
                <canvas id="departmentPerformanceChart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Request Distribution Chart -->
    <div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-700 bg-slate-700/30">
            <h3 class="text-lg font-semibold text-white flex items-center">
                <i class="fas fa-chart-pie text-green-400 mr-2"></i>Request Distribution
            </h3>
        </div>
        <div class="p-6">
            <div class="chart-container">
                <canvas id="requestDistributionChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Employee Performance Table -->
<div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-700 bg-slate-700/30">
        <h3 class="text-lg font-semibold text-white flex items-center">
            <i class="fas fa-table text-blue-400 mr-2"></i>Employee Performance Details
        </h3>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-600">
            <thead class="bg-slate-700">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-300 uppercase">Employee</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-300 uppercase">Department</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-300 uppercase">Total Requests</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-300 uppercase">Approved</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-300 uppercase">Pending</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-300 uppercase">Rejected</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-300 uppercase">Total Days</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-300 uppercase">Leave Balances</th>
                </tr>
            </thead>
            <tbody class="bg-slate-800 divide-y divide-slate-600">
                <?php foreach ($employeePerformance as $emp): ?>
                <tr class="hover:bg-slate-700/50">
                    <td class="px-4 py-3 text-sm font-medium text-white"><?php echo htmlspecialchars($emp['name']); ?></td>
                    <td class="px-4 py-3 text-sm text-slate-300"><?php echo htmlspecialchars($emp['department']); ?></td>
                    <td class="px-4 py-3 text-sm text-slate-300"><?php echo $emp['total_requests']; ?></td>
                    <td class="px-4 py-3 text-sm text-green-400"><?php echo $emp['approved_requests']; ?></td>
                    <td class="px-4 py-3 text-sm text-yellow-400"><?php echo $emp['pending_requests']; ?></td>
                    <td class="px-4 py-3 text-sm text-red-400"><?php echo $emp['rejected_requests']; ?></td>
                    <td class="px-4 py-3 text-sm text-slate-300"><?php echo $emp['total_days_requested']; ?></td>
                    <td class="px-4 py-3 text-sm text-slate-300">
                        <div class="flex space-x-2">
                            <span class="px-2 py-1 bg-blue-500/20 text-blue-400 rounded text-xs">VL: <?php echo $emp['vacation_leave_balance']; ?></span>
                            <span class="px-2 py-1 bg-red-500/20 text-red-400 rounded text-xs">SL: <?php echo $emp['sick_leave_balance']; ?></span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (empty($employeePerformance)): ?>
<div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden">
    <div class="p-12 text-center">
        <i class="fas fa-user-check text-6xl text-slate-500 mb-4"></i>
        <h3 class="text-xl font-semibold text-slate-400 mb-2">No Performance Data</h3>
        <p class="text-slate-500">No employee performance data found for the selected period and filters.</p>
    </div>
</div>
<?php endif; ?>

<script>
// Department Performance Chart
const departmentPerformanceCtx = document.getElementById('departmentPerformanceChart').getContext('2d');
const departmentData = <?php echo json_encode(array_count_values(array_column($employeePerformance, 'department'))); ?>;
new Chart(departmentPerformanceCtx, {
    type: 'bar',
    data: {
        labels: Object.keys(departmentData),
        datasets: [{
            label: 'Employees with Requests',
            data: Object.values(departmentData),
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

// Request Distribution Chart
const requestDistributionCtx = document.getElementById('requestDistributionChart').getContext('2d');
const totalApproved = <?php echo array_sum(array_column($employeePerformance, 'approved_requests')); ?>;
const totalPending = <?php echo array_sum(array_column($employeePerformance, 'pending_requests')); ?>;
const totalRejected = <?php echo array_sum(array_column($employeePerformance, 'rejected_requests')); ?>;

new Chart(requestDistributionCtx, {
    type: 'doughnut',
    data: {
        labels: ['Approved', 'Pending', 'Rejected'],
        datasets: [{
            data: [totalApproved, totalPending, totalRejected],
            backgroundColor: [
                '#10B981',
                '#F59E0B',
                '#EF4444'
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
</script>

