<?php
$utilization = $reportData['utilization_metrics'] ?? [];
$departmentStats = $reportData['department_stats'] ?? [];
$systemStats = $reportData['system_stats'] ?? [];
?>

<div class="space-y-6">
    <!-- Utilization Metrics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-2xl p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-blue-100 text-sm">Total Employees</p>
                    <p class="text-3xl font-bold"><?php echo $utilization['total_employees'] ?? 0; ?></p>
                    <p class="text-blue-100 text-xs mt-1">in system</p>
                </div>
                <i class="fas fa-users text-4xl opacity-75"></i>
            </div>
        </div>
        
        <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-2xl p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-green-100 text-sm">Active Employees</p>
                    <p class="text-3xl font-bold"><?php echo $utilization['active_employees'] ?? 0; ?></p>
                    <p class="text-green-100 text-xs mt-1">using system</p>
                </div>
                <i class="fas fa-user-check text-4xl opacity-75"></i>
            </div>
        </div>
        
        <div class="bg-gradient-to-r from-purple-500 to-purple-600 rounded-2xl p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-purple-100 text-sm">Participation Rate</p>
                    <p class="text-3xl font-bold"><?php echo $utilization['employee_participation_rate'] ?? 0; ?>%</p>
                    <p class="text-purple-100 text-xs mt-1">engagement</p>
                </div>
                <i class="fas fa-chart-pie text-4xl opacity-75"></i>
            </div>
        </div>
        
        <div class="bg-gradient-to-r from-orange-500 to-orange-600 rounded-2xl p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-orange-100 text-sm">Total Requests</p>
                    <p class="text-3xl font-bold"><?php echo $utilization['total_requests'] ?? 0; ?></p>
                    <p class="text-orange-100 text-xs mt-1">submitted</p>
                </div>
                <i class="fas fa-list text-4xl opacity-75"></i>
            </div>
        </div>
    </div>

    <!-- System Utilization Analysis -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Participation Analysis -->
        <div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-700 bg-slate-700/30">
                <h3 class="text-xl font-semibold text-white flex items-center">
                    <i class="fas fa-chart-bar text-blue-400 mr-3"></i>System Participation
                </h3>
            </div>
            <div class="p-6">
                <div class="space-y-6">
                    <!-- Participation Rate -->
                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-slate-300">Employee Participation</span>
                            <span class="text-white font-semibold"><?php echo $utilization['employee_participation_rate'] ?? 0; ?>%</span>
                        </div>
                        <div class="w-full bg-slate-700 rounded-full h-3">
                            <div class="bg-gradient-to-r from-blue-500 to-purple-600 h-3 rounded-full transition-all duration-500" 
                                 style="width: <?php echo $utilization['employee_participation_rate'] ?? 0; ?>%"></div>
                        </div>
                    </div>
                    
                    <!-- Usage Statistics -->
                    <div class="space-y-4">
                        <div class="flex justify-between items-center p-3 bg-slate-700/50 rounded-lg">
                            <span class="text-slate-300">Total Employees</span>
                            <span class="text-white font-semibold"><?php echo $utilization['total_employees'] ?? 0; ?></span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-slate-700/50 rounded-lg">
                            <span class="text-slate-300">Active Users</span>
                            <span class="text-green-400 font-semibold"><?php echo $utilization['active_employees'] ?? 0; ?></span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-slate-700/50 rounded-lg">
                            <span class="text-slate-300">Inactive Users</span>
                            <span class="text-red-400 font-semibold"><?php echo ($utilization['total_employees'] ?? 0) - ($utilization['active_employees'] ?? 0); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Usage Patterns -->
        <div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-700 bg-slate-700/30">
                <h3 class="text-xl font-semibold text-white flex items-center">
                    <i class="fas fa-chart-line text-green-400 mr-3"></i>Usage Patterns
                </h3>
            </div>
            <div class="p-6">
                <div class="space-y-6">
                    <div class="text-center">
                        <div class="text-3xl font-bold text-white mb-2"><?php echo $utilization['total_requests'] ?? 0; ?></div>
                        <div class="text-slate-400">Total Requests Submitted</div>
                    </div>
                    
                    <div class="text-center">
                        <div class="text-3xl font-bold text-white mb-2"><?php echo $utilization['avg_days_per_request'] ?? 0; ?></div>
                        <div class="text-slate-400">Average Days per Request</div>
                    </div>
                    
                    <div class="text-center">
                        <div class="text-3xl font-bold text-white mb-2"><?php echo $utilization['total_days_used'] ?? 0; ?></div>
                        <div class="text-slate-400">Total Days Used</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Department Utilization -->
    <div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-700 bg-slate-700/30">
            <h3 class="text-xl font-semibold text-white flex items-center">
                <i class="fas fa-building text-purple-400 mr-3"></i>Department Utilization
            </h3>
        </div>
        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-600">
                    <thead class="bg-slate-600">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-300 uppercase tracking-wider">Department</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-300 uppercase tracking-wider">Total Employees</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-300 uppercase tracking-wider">Total Requests</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-300 uppercase tracking-wider">Approved</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-300 uppercase tracking-wider">Rejected</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-300 uppercase tracking-wider">Pending</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-300 uppercase tracking-wider">Total Days</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-300 uppercase tracking-wider">Avg Days/Request</th>
                        </tr>
                    </thead>
                    <tbody class="bg-slate-800 divide-y divide-slate-600">
                        <?php foreach ($departmentStats as $dept): ?>
                        <tr class="hover:bg-slate-700/50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-white">
                                <?php echo htmlspecialchars($dept['department']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-300">
                                <?php echo $dept['total_employees']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-300">
                                <?php echo $dept['total_requests']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-green-400">
                                <?php echo $dept['approved_requests']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-red-400">
                                <?php echo $dept['rejected_requests']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-yellow-400">
                                <?php echo $dept['pending_requests']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-300">
                                <?php echo $dept['total_days_requested']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-300">
                                <?php echo round($dept['avg_days_per_request'], 1); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Utilization Insights -->
    <div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-700 bg-slate-700/30">
            <h3 class="text-xl font-semibold text-white flex items-center">
                <i class="fas fa-lightbulb text-yellow-400 mr-3"></i>Utilization Insights
            </h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- High Utilization Departments -->
                <div class="p-4 bg-slate-700/50 rounded-lg">
                    <h4 class="font-semibold text-white mb-2">High Utilization</h4>
                    <p class="text-slate-300 text-sm">
                        <?php
                        $highUtilDepts = array_filter($departmentStats, function($dept) {
                            return $dept['total_requests'] > 10;
                        });
                        echo count($highUtilDepts) . ' departments with high activity';
                        ?>
                    </p>
                </div>
                
                <!-- Low Utilization Departments -->
                <div class="p-4 bg-slate-700/50 rounded-lg">
                    <h4 class="font-semibold text-white mb-2">Low Utilization</h4>
                    <p class="text-slate-300 text-sm">
                        <?php
                        $lowUtilDepts = array_filter($departmentStats, function($dept) {
                            return $dept['total_requests'] <= 5;
                        });
                        echo count($lowUtilDepts) . ' departments with low activity';
                        ?>
                    </p>
                </div>
                
                <!-- System Health -->
                <div class="p-4 bg-slate-700/50 rounded-lg">
                    <h4 class="font-semibold text-white mb-2">System Health</h4>
                    <p class="text-slate-300 text-sm">
                        <?php
                        $participationRate = $utilization['employee_participation_rate'] ?? 0;
                        if ($participationRate > 80) {
                            echo 'Excellent participation rate';
                        } elseif ($participationRate > 60) {
                            echo 'Good participation rate';
                        } elseif ($participationRate > 40) {
                            echo 'Moderate participation rate';
                        } else {
                            echo 'Low participation rate';
                        }
                        ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>








