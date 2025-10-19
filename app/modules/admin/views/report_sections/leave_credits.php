<?php
$leaveCredits = $reportData['leave_credits'] ?? [];
$systemStats = $reportData['system_stats'] ?? [];
?>

<div class="space-y-6">
    <!-- Leave Credits Summary -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-2xl p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-blue-100 text-sm">Total Employees</p>
                    <p class="text-3xl font-bold"><?php echo count($leaveCredits); ?></p>
                    <p class="text-blue-100 text-xs mt-1">with leave credits</p>
                </div>
                <i class="fas fa-users text-4xl opacity-75"></i>
            </div>
        </div>
        
        <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-2xl p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-green-100 text-sm">Total Vacation Days</p>
                    <p class="text-3xl font-bold"><?php echo array_sum(array_column($leaveCredits, 'vacation_leave_balance')); ?></p>
                    <p class="text-green-100 text-xs mt-1">available</p>
                </div>
                <i class="fas fa-umbrella-beach text-4xl opacity-75"></i>
            </div>
        </div>
        
        <div class="bg-gradient-to-r from-red-500 to-red-600 rounded-2xl p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-red-100 text-sm">Total Sick Days</p>
                    <p class="text-3xl font-bold"><?php echo array_sum(array_column($leaveCredits, 'sick_leave_balance')); ?></p>
                    <p class="text-red-100 text-xs mt-1">available</p>
                </div>
                <i class="fas fa-thermometer-half text-4xl opacity-75"></i>
            </div>
        </div>
        
        <div class="bg-gradient-to-r from-purple-500 to-purple-600 rounded-2xl p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-purple-100 text-sm">Total Special Privilege</p>
                    <p class="text-3xl font-bold"><?php echo array_sum(array_column($leaveCredits, 'special_leave_privilege_balance')); ?></p>
                    <p class="text-purple-100 text-xs mt-1">available</p>
                </div>
                <i class="fas fa-star text-4xl opacity-75"></i>
            </div>
        </div>
    </div>

    <!-- Leave Credits Table -->
    <div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-700 bg-slate-700/30">
            <h3 class="text-xl font-semibold text-white flex items-center">
                <i class="fas fa-coins text-yellow-400 mr-3"></i>Employee Leave Credits
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-600">
                <thead class="bg-slate-600">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-300 uppercase tracking-wider">Employee</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-300 uppercase tracking-wider">Department</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-300 uppercase tracking-wider">Vacation</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-300 uppercase tracking-wider">Sick</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-300 uppercase tracking-wider">Special</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-300 uppercase tracking-wider">Maternity</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-300 uppercase tracking-wider">Paternity</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-300 uppercase tracking-wider">Terminal</th>
                    </tr>
                </thead>
                <tbody class="bg-slate-800 divide-y divide-slate-600">
                    <?php foreach ($leaveCredits as $credit): ?>
                    <tr class="hover:bg-slate-700/50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10">
                                    <div class="h-10 w-10 rounded-full bg-gradient-to-r from-blue-500 to-purple-600 flex items-center justify-center">
                                        <span class="text-white font-semibold text-sm">
                                            <?php echo strtoupper(substr($credit['name'], 0, 2)); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-white"><?php echo htmlspecialchars($credit['name']); ?></div>
                                    <div class="text-sm text-slate-400"><?php echo htmlspecialchars($credit['position']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-300">
                            <?php echo htmlspecialchars($credit['department']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $credit['vacation_leave_balance'] > 10 ? 'bg-green-100 text-green-800' : ($credit['vacation_leave_balance'] > 5 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); ?>">
                                <?php echo $credit['vacation_leave_balance']; ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $credit['sick_leave_balance'] > 10 ? 'bg-green-100 text-green-800' : ($credit['sick_leave_balance'] > 5 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); ?>">
                                <?php echo $credit['sick_leave_balance']; ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $credit['special_leave_privilege_balance'] > 2 ? 'bg-green-100 text-green-800' : ($credit['special_leave_privilege_balance'] > 1 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); ?>">
                                <?php echo $credit['special_leave_privilege_balance']; ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $credit['maternity_leave_balance'] > 50 ? 'bg-green-100 text-green-800' : ($credit['maternity_leave_balance'] > 25 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); ?>">
                                <?php echo $credit['maternity_leave_balance']; ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $credit['paternity_leave_balance'] > 5 ? 'bg-green-100 text-green-800' : ($credit['paternity_leave_balance'] > 3 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); ?>">
                                <?php echo $credit['paternity_leave_balance']; ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $credit['terminal_leave_balance'] > 50 ? 'bg-green-100 text-green-800' : ($credit['terminal_leave_balance'] > 25 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); ?>">
                                <?php echo $credit['terminal_leave_balance']; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Leave Credits Analysis -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Department-wise Credits -->
        <div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-700 bg-slate-700/30">
                <h3 class="text-xl font-semibold text-white flex items-center">
                    <i class="fas fa-building text-blue-400 mr-3"></i>Department-wise Credits
                </h3>
            </div>
            <div class="p-6">
                <?php
                $deptCredits = [];
                foreach ($leaveCredits as $credit) {
                    $dept = $credit['department'];
                    if (!isset($deptCredits[$dept])) {
                        $deptCredits[$dept] = [
                            'vacation' => 0,
                            'sick' => 0,
                            'special' => 0,
                            'count' => 0
                        ];
                    }
                    $deptCredits[$dept]['vacation'] += $credit['vacation_leave_balance'];
                    $deptCredits[$dept]['sick'] += $credit['sick_leave_balance'];
                    $deptCredits[$dept]['special'] += $credit['special_leave_privilege_balance'];
                    $deptCredits[$dept]['count']++;
                }
                ?>
                <div class="space-y-4">
                    <?php foreach ($deptCredits as $dept => $credits): ?>
                    <div class="p-4 bg-slate-700/50 rounded-lg">
                        <h4 class="font-semibold text-white mb-2"><?php echo htmlspecialchars($dept); ?></h4>
                        <div class="grid grid-cols-3 gap-4 text-sm">
                            <div class="text-center">
                                <p class="text-slate-400">Vacation</p>
                                <p class="text-white font-semibold"><?php echo $credits['vacation']; ?></p>
                            </div>
                            <div class="text-center">
                                <p class="text-slate-400">Sick</p>
                                <p class="text-white font-semibold"><?php echo $credits['sick']; ?></p>
                            </div>
                            <div class="text-center">
                                <p class="text-slate-400">Special</p>
                                <p class="text-white font-semibold"><?php echo $credits['special']; ?></p>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Credit Utilization Insights -->
        <div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-700 bg-slate-700/30">
                <h3 class="text-xl font-semibold text-white flex items-center">
                    <i class="fas fa-chart-pie text-green-400 mr-3"></i>Credit Utilization Insights
                </h3>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    <div class="p-4 bg-slate-700/50 rounded-lg">
                        <h4 class="font-semibold text-white mb-2">Low Credit Alerts</h4>
                        <p class="text-slate-300 text-sm">
                            <?php
                            $lowCredits = 0;
                            foreach ($leaveCredits as $credit) {
                                if ($credit['vacation_leave_balance'] < 5 || $credit['sick_leave_balance'] < 5) {
                                    $lowCredits++;
                                }
                            }
                            echo $lowCredits . ' employees have low leave credits';
                            ?>
                        </p>
                    </div>
                    
                    <div class="p-4 bg-slate-700/50 rounded-lg">
                        <h4 class="font-semibold text-white mb-2">High Credit Holders</h4>
                        <p class="text-slate-300 text-sm">
                            <?php
                            $highCredits = 0;
                            foreach ($leaveCredits as $credit) {
                                if ($credit['vacation_leave_balance'] > 20 || $credit['sick_leave_balance'] > 20) {
                                    $highCredits++;
                                }
                            }
                            echo $highCredits . ' employees have high leave credits';
                            ?>
                        </p>
                    </div>
                    
                    <div class="p-4 bg-slate-700/50 rounded-lg">
                        <h4 class="font-semibold text-white mb-2">Average Credits</h4>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <p class="text-slate-400">Vacation</p>
                                <p class="text-white font-semibold"><?php echo count($leaveCredits) > 0 ? round(array_sum(array_column($leaveCredits, 'vacation_leave_balance')) / count($leaveCredits), 1) : 0; ?></p>
                            </div>
                            <div>
                                <p class="text-slate-400">Sick</p>
                                <p class="text-white font-semibold"><?php echo count($leaveCredits) > 0 ? round(array_sum(array_column($leaveCredits, 'sick_leave_balance')) / count($leaveCredits), 1) : 0; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
