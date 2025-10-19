<?php
$dtrData = $reportData['dtr_data'] ?? [];
$attendanceSummary = $reportData['attendance_summary'] ?? [];
?>

<!-- Attendance Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="metric-card rounded-2xl p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-400 text-sm font-medium">Total Days</p>
                <p class="text-3xl font-bold text-white"><?php echo $attendanceSummary['total_days'] ?? 0; ?></p>
                <p class="text-slate-400 text-xs mt-1">in period</p>
            </div>
            <div class="w-12 h-12 bg-blue-500/20 rounded-xl flex items-center justify-center">
                <i class="fas fa-calendar text-blue-400 text-xl"></i>
            </div>
        </div>
    </div>
    
    <div class="metric-card rounded-2xl p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-400 text-sm font-medium">Complete Days</p>
                <p class="text-3xl font-bold text-white"><?php echo $attendanceSummary['complete_days'] ?? 0; ?></p>
                <p class="text-green-400 text-xs mt-1"><?php echo $attendanceSummary['total_days'] > 0 ? round(($attendanceSummary['complete_days'] / $attendanceSummary['total_days']) * 100, 1) : 0; ?>%</p>
            </div>
            <div class="w-12 h-12 bg-green-500/20 rounded-xl flex items-center justify-center">
                <i class="fas fa-check-circle text-green-400 text-xl"></i>
            </div>
        </div>
    </div>
    
    <div class="metric-card rounded-2xl p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-400 text-sm font-medium">Total Hours</p>
                <p class="text-3xl font-bold text-white"><?php echo number_format($attendanceSummary['total_hours'] ?? 0, 1); ?></p>
                <p class="text-slate-400 text-xs mt-1">hours worked</p>
            </div>
            <div class="w-12 h-12 bg-purple-500/20 rounded-xl flex items-center justify-center">
                <i class="fas fa-clock text-purple-400 text-xl"></i>
            </div>
        </div>
    </div>
    
    <div class="metric-card rounded-2xl p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-400 text-sm font-medium">Avg Hours/Day</p>
                <p class="text-3xl font-bold text-white"><?php echo number_format($attendanceSummary['avg_hours_per_day'] ?? 0, 1); ?></p>
                <p class="text-slate-400 text-xs mt-1">per day</p>
            </div>
            <div class="w-12 h-12 bg-orange-500/20 rounded-xl flex items-center justify-center">
                <i class="fas fa-chart-line text-orange-400 text-xl"></i>
            </div>
        </div>
    </div>
</div>

<!-- Attendance Breakdown -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-700 bg-slate-700/30">
            <h3 class="text-lg font-semibold text-white flex items-center">
                <i class="fas fa-check-circle text-green-400 mr-2"></i>Complete Days
            </h3>
        </div>
        <div class="p-6">
            <div class="text-center">
                <div class="text-4xl font-bold text-green-400 mb-2"><?php echo $attendanceSummary['complete_days'] ?? 0; ?></div>
                <p class="text-slate-400">Full day attendance</p>
            </div>
        </div>
    </div>
    
    <div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-700 bg-slate-700/30">
            <h3 class="text-lg font-semibold text-white flex items-center">
                <i class="fas fa-clock text-yellow-400 mr-2"></i>Half Days
            </h3>
        </div>
        <div class="p-6">
            <div class="text-center">
                <div class="text-4xl font-bold text-yellow-400 mb-2"><?php echo $attendanceSummary['half_days'] ?? 0; ?></div>
                <p class="text-slate-400">Partial attendance</p>
            </div>
        </div>
    </div>
    
    <div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-700 bg-slate-700/30">
            <h3 class="text-lg font-semibold text-white flex items-center">
                <i class="fas fa-times-circle text-red-400 mr-2"></i>Absent Days
            </h3>
        </div>
        <div class="p-6">
            <div class="text-center">
                <div class="text-4xl font-bold text-red-400 mb-2"><?php echo $attendanceSummary['absent_days'] ?? 0; ?></div>
                <p class="text-slate-400">No attendance</p>
            </div>
        </div>
    </div>
</div>

<!-- DTR Data Table -->
<div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-700 bg-slate-700/30">
        <h3 class="text-lg font-semibold text-white flex items-center">
            <i class="fas fa-table text-blue-400 mr-2"></i>Daily Time Record Details
        </h3>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-600">
            <thead class="bg-slate-700">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-300 uppercase">Employee</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-300 uppercase">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-300 uppercase">Morning In</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-300 uppercase">Morning Out</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-300 uppercase">Afternoon In</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-300 uppercase">Afternoon Out</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-300 uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-300 uppercase">Total Hours</th>
                </tr>
            </thead>
            <tbody class="bg-slate-800 divide-y divide-slate-600">
                <?php foreach ($dtrData as $dtr): ?>
                <tr class="hover:bg-slate-700/50">
                    <td class="px-4 py-3 text-sm font-medium text-white"><?php echo htmlspecialchars($dtr['employee_name']); ?></td>
                    <td class="px-4 py-3 text-sm text-slate-300"><?php echo date('M d, Y', strtotime($dtr['date'])); ?></td>
                    <td class="px-4 py-3 text-sm text-slate-300">
                        <?php echo $dtr['morning_time_in'] ? date('H:i', strtotime($dtr['morning_time_in'])) : '-'; ?>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-300">
                        <?php echo $dtr['morning_time_out'] ? date('H:i', strtotime($dtr['morning_time_out'])) : '-'; ?>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-300">
                        <?php echo $dtr['afternoon_time_in'] ? date('H:i', strtotime($dtr['afternoon_time_in'])) : '-'; ?>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-300">
                        <?php echo $dtr['afternoon_time_out'] ? date('H:i', strtotime($dtr['afternoon_time_out'])) : '-'; ?>
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <?php
                        $status = $dtr['attendance_status'];
                        $statusClass = '';
                        switch ($status) {
                            case 'Complete':
                                $statusClass = 'bg-green-500/20 text-green-400';
                                break;
                            case 'Half Day (Morning)':
                            case 'Half Day (Afternoon)':
                                $statusClass = 'bg-yellow-500/20 text-yellow-400';
                                break;
                            case 'Incomplete':
                                $statusClass = 'bg-orange-500/20 text-orange-400';
                                break;
                            case 'Absent':
                                $statusClass = 'bg-red-500/20 text-red-400';
                                break;
                        }
                        ?>
                        <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $statusClass; ?>">
                            <?php echo $status; ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-300"><?php echo number_format($dtr['total_hours'], 2); ?> hrs</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (empty($dtrData)): ?>
<div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden">
    <div class="p-12 text-center">
        <i class="fas fa-clock text-6xl text-slate-500 mb-4"></i>
        <h3 class="text-xl font-semibold text-slate-400 mb-2">No Attendance Data</h3>
        <p class="text-slate-500">No DTR records found for the selected period and filters.</p>
    </div>
</div>
<?php endif; ?>

