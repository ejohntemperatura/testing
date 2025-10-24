<?php
$leaveTypeStats = $reportData['leave_type_stats'] ?? [];
$monthlyTrends = $reportData['monthly_trends'] ?? [];
$systemStats = $reportData['system_stats'] ?? [];
?>

<!-- Leave Analysis Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="metric-card rounded-2xl p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-400 text-sm font-medium">Most Used Type</p>
                <p class="text-lg font-bold text-white"><?php echo !empty($leaveTypeStats) ? htmlspecialchars($leaveTypeStats[0]['leave_type']) : 'N/A'; ?></p>
                <p class="text-slate-400 text-xs mt-1"><?php echo !empty($leaveTypeStats) ? $leaveTypeStats[0]['total_requests'] : 0; ?> requests</p>
            </div>
            <div class="w-12 h-12 bg-blue-500/20 rounded-xl flex items-center justify-center">
                <i class="fas fa-star text-blue-400 text-xl"></i>
            </div>
        </div>
    </div>
    
    <div class="metric-card rounded-2xl p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-400 text-sm font-medium">Avg Days/Type</p>
                <p class="text-3xl font-bold text-white"><?php echo !empty($leaveTypeStats) ? number_format(array_sum(array_column($leaveTypeStats, 'avg_days_per_request')) / count($leaveTypeStats), 1) : 0; ?></p>
                <p class="text-slate-400 text-xs mt-1">per leave type</p>
            </div>
            <div class="w-12 h-12 bg-green-500/20 rounded-xl flex items-center justify-center">
                <i class="fas fa-calculator text-green-400 text-xl"></i>
            </div>
        </div>
    </div>
    
    <div class="metric-card rounded-2xl p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-400 text-sm font-medium">Total Leave Types</p>
                <p class="text-3xl font-bold text-white"><?php echo count($leaveTypeStats); ?></p>
                <p class="text-slate-400 text-xs mt-1">used in period</p>
            </div>
            <div class="w-12 h-12 bg-purple-500/20 rounded-xl flex items-center justify-center">
                <i class="fas fa-list text-purple-400 text-xl"></i>
            </div>
        </div>
    </div>
    
    <div class="metric-card rounded-2xl p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-400 text-sm font-medium">Peak Month</p>
                <p class="text-lg font-bold text-white"><?php echo !empty($monthlyTrends) ? date('M Y', strtotime($monthlyTrends[array_search(max(array_column($monthlyTrends, 'total_requests')), array_column($monthlyTrends, 'total_requests'))]['month'] . '-01')) : 'N/A'; ?></p>
                <p class="text-slate-400 text-xs mt-1">highest activity</p>
            </div>
            <div class="w-12 h-12 bg-orange-500/20 rounded-xl flex items-center justify-center">
                <i class="fas fa-chart-line text-orange-400 text-xl"></i>
            </div>
        </div>
    </div>
</div>

<!-- Leave Analysis Charts -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
    <!-- Leave Type Usage Chart -->
    <div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-700 bg-slate-700/30">
            <h3 class="text-lg font-semibold text-white flex items-center">
                <i class="fas fa-chart-pie text-blue-400 mr-2"></i>Leave Type Usage
            </h3>
        </div>
        <div class="p-6">
            <div class="chart-container">
                <canvas id="leaveTypeUsageChart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Monthly Trends Chart -->
    <div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-700 bg-slate-700/30">
            <h3 class="text-lg font-semibold text-white flex items-center">
                <i class="fas fa-chart-line text-green-400 mr-2"></i>Monthly Trends
            </h3>
        </div>
        <div class="p-6">
            <div class="chart-container">
                <canvas id="monthlyTrendsChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Leave Type Analysis Table -->
<div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden mb-8">
    <div class="px-6 py-4 border-b border-slate-700 bg-slate-700/30">
        <h3 class="text-lg font-semibold text-white flex items-center">
            <i class="fas fa-table text-blue-400 mr-2"></i>Detailed Leave Type Analysis
        </h3>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-600">
            <thead class="bg-slate-700">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-300 uppercase">Leave Type</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-300 uppercase">Total Requests</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-300 uppercase">Approved</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-300 uppercase">Pending</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-300 uppercase">Rejected</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-300 uppercase">Total Days</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-300 uppercase">Avg Days</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-300 uppercase">Approval Rate</th>
                </tr>
            </thead>
            <tbody class="bg-slate-800 divide-y divide-slate-600">
                <?php foreach ($leaveTypeStats as $type): ?>
                <?php 
                $approvalRate = $type['total_requests'] > 0 ? round(($type['approved_requests'] / $type['total_requests']) * 100, 1) : 0;
                ?>
                <tr class="hover:bg-slate-700/50">
                    <td class="px-4 py-3 text-sm font-medium text-white"><?php echo htmlspecialchars($type['leave_type']); ?></td>
                    <td class="px-4 py-3 text-sm text-slate-300"><?php echo $type['total_requests']; ?></td>
                    <td class="px-4 py-3 text-sm text-green-400"><?php echo $type['approved_requests']; ?></td>
                    <td class="px-4 py-3 text-sm text-yellow-400"><?php echo $type['pending_requests']; ?></td>
                    <td class="px-4 py-3 text-sm text-red-400"><?php echo $type['rejected_requests']; ?></td>
                    <td class="px-4 py-3 text-sm text-slate-300"><?php echo $type['total_days_requested']; ?></td>
                    <td class="px-4 py-3 text-sm text-slate-300"><?php echo number_format($type['avg_days_per_request'], 1); ?></td>
                    <td class="px-4 py-3 text-sm">
                        <div class="flex items-center">
                            <div class="w-16 bg-slate-600 rounded-full h-2 mr-2">
                                <div class="bg-green-500 h-2 rounded-full" style="width: <?php echo $approvalRate; ?>%"></div>
                            </div>
                            <span class="text-slate-300 text-xs"><?php echo $approvalRate; ?>%</span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Leave Pattern Analysis -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    <!-- Peak Usage Times -->
    <div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-700 bg-slate-700/30">
            <h3 class="text-lg font-semibold text-white flex items-center">
                <i class="fas fa-clock text-purple-400 mr-2"></i>Peak Usage Times
            </h3>
        </div>
        <div class="p-6">
            <div class="space-y-4">
                <?php
                $monthlyData = [];
                foreach ($monthlyTrends as $trend) {
                    $monthlyData[$trend['month']] = $trend['total_requests'];
                }
                arsort($monthlyData);
                $topMonths = array_slice($monthlyData, 0, 3, true);
                ?>
                <?php foreach ($topMonths as $month => $requests): ?>
                <div class="flex items-center justify-between p-3 bg-slate-700/50 rounded-lg">
                    <div>
                        <p class="text-white font-medium"><?php echo date('F Y', strtotime($month . '-01')); ?></p>
                        <p class="text-slate-400 text-sm"><?php echo $requests; ?> requests</p>
                    </div>
                    <div class="text-right">
                        <div class="w-20 bg-slate-600 rounded-full h-2">
                            <div class="bg-purple-500 h-2 rounded-full" style="width: <?php echo ($requests / max($monthlyData)) * 100; ?>%"></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <!-- Leave Type Efficiency -->
    <div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-700 bg-slate-700/30">
            <h3 class="text-lg font-semibold text-white flex items-center">
                <i class="fas fa-chart-bar text-orange-400 mr-2"></i>Leave Type Efficiency
            </h3>
        </div>
        <div class="p-6">
            <div class="space-y-4">
                <?php
                $efficiencyData = [];
                foreach ($leaveTypeStats as $type) {
                    $efficiency = $type['total_requests'] > 0 ? round(($type['approved_requests'] / $type['total_requests']) * 100, 1) : 0;
                    $efficiencyData[$type['leave_type']] = $efficiency;
                }
                arsort($efficiencyData);
                $topEfficient = array_slice($efficiencyData, 0, 3, true);
                ?>
                <?php foreach ($topEfficient as $type => $efficiency): ?>
                <div class="flex items-center justify-between p-3 bg-slate-700/50 rounded-lg">
                    <div>
                        <p class="text-white font-medium"><?php echo htmlspecialchars($type); ?></p>
                        <p class="text-slate-400 text-sm"><?php echo $efficiency; ?>% approval rate</p>
                    </div>
                    <div class="text-right">
                        <div class="w-20 bg-slate-600 rounded-full h-2">
                            <div class="bg-orange-500 h-2 rounded-full" style="width: <?php echo $efficiency; ?>%"></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php if (empty($leaveTypeStats)): ?>
<div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden">
    <div class="p-12 text-center">
        <i class="fas fa-calendar-alt text-6xl text-slate-500 mb-4"></i>
        <h3 class="text-xl font-semibold text-slate-400 mb-2">No Leave Analysis Data</h3>
        <p class="text-slate-500">No leave analysis data found for the selected period and filters.</p>
    </div>
</div>
<?php endif; ?>

<script>
// Leave Type Usage Chart
const leaveTypeUsageCtx = document.getElementById('leaveTypeUsageChart').getContext('2d');
new Chart(leaveTypeUsageCtx, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_column($leaveTypeStats, 'leave_type')); ?>,
        datasets: [{
            data: <?php echo json_encode(array_column($leaveTypeStats, 'total_requests')); ?>,
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

// Monthly Trends Chart
const monthlyTrendsCtx = document.getElementById('monthlyTrendsChart').getContext('2d');
new Chart(monthlyTrendsCtx, {
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









