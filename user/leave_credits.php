<?php
session_start();
require_once '../config/database.php';
require_once '../includes/LeaveCreditsCalculator.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/index.php');
    exit();
}

// Initialize leave credits calculator and manager
$calculator = new LeaveCreditsCalculator($pdo);
require_once '../includes/LeaveCreditsManager.php';
$creditsManager = new LeaveCreditsManager($pdo);

// Get selected year or current year
$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Update leave credits for current year
$calculator->updateEmployeeLeaveCredits($_SESSION['user_id']);

// Get leave credit summary using the manager
$leaveSummary = $creditsManager->getLeaveCreditsSummary($_SESSION['user_id']);

// Force recalculation if credits are missing or zero
$needsRecalculation = false;
$requiredFields = [
    'vacation_leave_balance', 'sick_leave_balance', 'special_privilege_leave_balance',
    'maternity_leave_balance', 'paternity_leave_balance', 'solo_parent_leave_balance',
    'vawc_leave_balance', 'rehabilitation_leave_balance', 'special_women_leave_balance',
    'special_emergency_leave_balance', 'adoption_leave_balance', 'mandatory_leave_balance'
];

foreach ($requiredFields as $field) {
    if (!isset($leaveSummary[$field]) || $leaveSummary[$field] == 0) {
        $needsRecalculation = true;
        break;
    }
}

if ($needsRecalculation) {
    $calculator->updateEmployeeLeaveCredits($_SESSION['user_id']);
    $leaveSummary = $creditsManager->getLeaveCreditsSummary($_SESSION['user_id']);
}

// Get employee information
$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$employee = $stmt->fetch();

// Get leave usage for selected year with detailed information
$stmt = $pdo->prepare("
    SELECT 
        leave_type,
        start_date,
        end_date,
        DATEDIFF(end_date, start_date) + 1 as days_used,
        COALESCE(days_requested, DATEDIFF(end_date, start_date) + 1) as days_requested,
        status,
        reason,
        is_late,
        late_justification,
        created_at,
        approved_at,
        rejection_reason
    FROM leave_requests 
    WHERE employee_id = ? 
    AND YEAR(start_date) = ? 
    ORDER BY start_date DESC
");
$stmt->execute([$_SESSION['user_id'], $selectedYear]);
$leaveHistory = $stmt->fetchAll();

// Calculate total leave days used this year
$totalUsed = array_sum(array_column($leaveHistory, 'days_used'));

// Calculate leave usage by type
$leaveUsageByType = [];
foreach ($leaveHistory as $leave) {
    $type = $leave['leave_type'];
    if (!isset($leaveUsageByType[$type])) {
        $leaveUsageByType[$type] = [
            'total_days' => 0,
            'approved_days' => 0,
            'pending_days' => 0,
            'rejected_days' => 0,
            'count' => 0
        ];
    }
    
    $leaveUsageByType[$type]['total_days'] += $leave['days_used'];
    $leaveUsageByType[$type]['count']++;
    
    if ($leave['status'] === 'approved') {
        $leaveUsageByType[$type]['approved_days'] += $leave['days_used'];
    } elseif ($leave['status'] === 'pending') {
        $leaveUsageByType[$type]['pending_days'] += $leave['days_used'];
    } elseif ($leave['status'] === 'rejected') {
        $leaveUsageByType[$type]['rejected_days'] += $leave['days_used'];
    }
}

// Get leave type mapping for display
$leaveTypeMapping = [
    'vacation' => 'Vacation Leave',
    'special_privilege' => 'Special Leave Privilege',
    'sick' => 'Sick Leave',
    'study' => 'Study Leave',
    'solo_parent' => 'Solo Parent Leave',
    'vawc' => '10-Day VAWC Leave',
    'rehabilitation' => 'Rehabilitation Privilege',
    'special_women' => 'Special Leave Benefits for Women',
    'special_emergency' => 'Special Emergency Leave (Calamity)',
    'adoption' => 'Adoption Leave',
    'maternity' => 'Maternity Leave',
    'paternity' => 'Paternity Leave',
    'mandatory' => 'Mandatory/Forced Leave',
    'other' => 'Other Purpose'
];

// Get years with leave records
$stmt = $pdo->prepare("
    SELECT DISTINCT YEAR(start_date) as year 
    FROM leave_requests 
    WHERE employee_id = ? 
    ORDER BY year DESC
");
$stmt->execute([$_SESSION['user_id']]);
$availableYears = $stmt->fetchAll(PDO::FETCH_COLUMN);
if (!in_array(date('Y'), $availableYears)) {
    $availableYears[] = date('Y');
}
sort($availableYears, SORT_NUMERIC);
$availableYears = array_reverse($availableYears);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- OFFLINE Tailwind CSS - No internet required! -->
    <link rel="stylesheet" href="../assets/css/tailwind.css">
        <!-- Font Awesome Local - No internet required! -->
    <link rel="stylesheet" href="../assets/libs/fontawesome/css/all.min.css">
    

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ELMS - Leave Credits</title>
    <script>
    </script>
    
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dark-theme.css">
    <script>
    </script>
</head>
<body class="bg-slate-900 text-white min-h-screen">
    <?php include '../includes/unified_navbar.php'; ?>

    <div class="flex">
        <!-- Left Sidebar -->
        <aside class="fixed left-0 top-16 h-[calc(100vh-4rem)] w-64 bg-slate-900 border-r border-slate-800 overflow-y-auto z-40">
            <nav class="p-4 space-y-2">
                <!-- Active Navigation Item (Dashboard) -->
                <a href="dashboard.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                    <i class="fas fa-tachometer-alt w-5"></i>
                    <span>Dashboard</span>
                </a>
                
                <!-- Section Headers -->
                <div class="space-y-1">
                    <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider px-4 py-2">Leave Management</h3>
                    
                    <!-- Navigation Items -->
                <a href="leave_history.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                    <i class="fas fa-history w-5"></i>
                    <span>Leave History</span>
                </a>
                
                <a href="leave_credits.php" class="flex items-center space-x-3 px-4 py-3 text-white bg-blue-500/20 rounded-lg border border-blue-500/30">
                    <i class="fas fa-calculator w-5"></i>
                    <span>Leave Credits</span>
                </a>
                </div>
                
                <div class="space-y-1">
                    <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider px-4 py-2">Reports</h3>
                
                <a href="view_chart.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                    <i class="fas fa-calendar w-5"></i>
                    <span>Leave Chart</span>
                </a>
                </div>
                
                <div class="space-y-1">
                    <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider px-4 py-2">Account</h3>
                    
                    <a href="profile.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                        <i class="fas fa-user w-5"></i>
                        <span>Profile</span>
                    </a>
                </div>
                
            </nav>
        </aside>
        
        <!-- Main Content -->
        <main class="pt-24 flex-1 ml-64 pt-24 px-6 pb-6">
            <div class="max-w-7xl mx-auto">
                <!-- Header -->
                <div class="mb-8">
                    <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 bg-gradient-to-r from-primary to-accent rounded-2xl flex items-center justify-center">
                            <i class="fas fa-calculator text-2xl text-white"></i>
                        </div>
                        <div>
                            <h1 class="text-3xl font-bold text-white mb-2">Leave Credits</h1>
                                <p class="text-slate-400">Civil Service compliant leave credit management</p>
                            </div>
                </div>

            <!-- Year Selector and Refresh -->
                        <div class="flex items-center gap-4">
                            <label for="yearSelect" class="text-slate-300 font-semibold">Year:</label>
                            <select id="yearSelect" class="bg-slate-700 border border-slate-600 rounded-xl px-4 py-2 text-white focus:outline-none focus:ring-2 focus:ring-primary">
                                <?php foreach ($availableYears as $year): ?>
                                    <option value="<?php echo $year; ?>" <?php echo $year == $selectedYear ? 'selected' : ''; ?>>
                                        <?php echo $year; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            
                            <button onclick="refreshPage()" class="bg-primary hover:bg-primary/80 text-white px-4 py-2 rounded-xl transition-colors flex items-center gap-2">
                                <i class="fas fa-sync-alt"></i>
                                Refresh
                            </button>
                            
                            <div class="text-sm text-slate-400">
                                Last updated: <?php echo date('M d, Y H:i'); ?>
                            </div>
                    </div>
                </div>
            </div>

                <!-- Civil Service Information -->
                <div class="bg-blue-500/20 border border-blue-500/30 rounded-2xl p-6 mb-8">
                    <div class="flex items-start gap-4">
                        <i class="fas fa-info-circle text-blue-400 text-2xl mt-1"></i>
                        <div>
                            <h3 class="text-xl font-semibold text-blue-400 mb-2">Civil Service Leave Credits</h3>
                            <p class="text-slate-300 mb-4">Your leave credits are calculated according to Civil Service Commission rules:</p>
                            <ul class="text-slate-300 space-y-1 text-sm">
                                <li>• <strong>Vacation Leave:</strong> 1.25 days per month (15 days annually)</li>
                                <li>• <strong>Sick Leave:</strong> 1.25 days per month (15 days annually)</li>
                                <li>• <strong>Special Privilege:</strong> 3 days annually</li>
                                <li>• <strong>Special Leaves:</strong> As per Civil Service rules and agency policies</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Leave Credits Overview -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                    <?php
                    $leaveTypes = [
                        'vacation' => ['name' => 'Vacation Leave', 'icon' => 'fas fa-umbrella-beach', 'color' => 'from-blue-500 to-cyan-500'],
                        'sick' => ['name' => 'Sick Leave', 'icon' => 'fas fa-thermometer-half', 'color' => 'from-red-500 to-pink-500'],
                        'special_privilege' => ['name' => 'Special Privilege', 'icon' => 'fas fa-star', 'color' => 'from-yellow-500 to-orange-500'],
                        'maternity' => ['name' => 'Maternity Leave', 'icon' => 'fas fa-baby', 'color' => 'from-pink-500 to-rose-500'],
                        'paternity' => ['name' => 'Paternity Leave', 'icon' => 'fas fa-male', 'color' => 'from-blue-600 to-indigo-600'],
                        'solo_parent' => ['name' => 'Solo Parent Leave', 'icon' => 'fas fa-user-friends', 'color' => 'from-purple-500 to-violet-500'],
                        'vawc' => ['name' => 'VAWC Leave', 'icon' => 'fas fa-shield-alt', 'color' => 'from-red-600 to-red-700'],
                        'rehabilitation' => ['name' => 'Rehabilitation Privilege', 'icon' => 'fas fa-heart', 'color' => 'from-green-500 to-emerald-500'],
                        'special_women' => ['name' => 'Special Women Leave', 'icon' => 'fas fa-venus', 'color' => 'from-pink-600 to-rose-600'],
                        'special_emergency' => ['name' => 'Special Emergency Leave', 'icon' => 'fas fa-exclamation-triangle', 'color' => 'from-orange-500 to-red-500'],
                        'adoption' => ['name' => 'Adoption Leave', 'icon' => 'fas fa-hands-helping', 'color' => 'from-teal-500 to-cyan-500'],
                        'mandatory' => ['name' => 'Mandatory Leave', 'icon' => 'fas fa-calendar-times', 'color' => 'from-gray-500 to-slate-500']
                    ];
                    
                    foreach ($leaveTypes as $type => $info):
                        // Get current balance from the manager
                        $fieldName = $type . '_leave_balance';
                        $currentBalance = isset($leaveSummary[$fieldName]) ? $leaveSummary[$fieldName] : 0;
                        
                        // Get usage statistics for this leave type
                        $usage = $leaveUsageByType[$type] ?? [
                            'total_days' => 0,
                            'approved_days' => 0,
                            'pending_days' => 0,
                            'rejected_days' => 0,
                            'count' => 0
                        ];
                        
                        // Calculate total allocated (current balance + used)
                        $totalAllocated = $currentBalance + $usage['approved_days'];
                        $usedPercentage = $totalAllocated > 0 ? ($usage['approved_days'] / $totalAllocated) * 100 : 0;
                        $remainingPercentage = 100 - $usedPercentage;
                    ?>
                    <div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl border border-slate-700/50 p-6 hover:border-slate-600/50 transition-all duration-300">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 bg-gradient-to-r <?php echo $info['color']; ?> rounded-xl flex items-center justify-center">
                                    <i class="<?php echo $info['icon']; ?> text-white text-lg"></i>
                                </div>
                                <div>
                                    <h3 class="text-lg font-semibold text-white"><?php echo $info['name']; ?></h3>
                                    <p class="text-slate-400 text-sm">Civil Service</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-2xl font-bold text-white"><?php echo number_format($currentBalance, 1); ?></div>
                                <div class="text-slate-400 text-sm">remaining</div>
                            </div>
                        </div>
                        
                        <div class="space-y-2">
                            <div class="flex justify-between text-sm">
                                <span class="text-slate-400">Total Allocated:</span>
                                <span class="text-white font-semibold"><?php echo number_format($totalAllocated, 1); ?> days</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-slate-400">Used (Approved):</span>
                                <span class="text-white font-semibold"><?php echo $usage['approved_days']; ?> days</span>
                            </div>
                            <?php if ($usage['pending_days'] > 0): ?>
                            <div class="flex justify-between text-sm">
                                <span class="text-slate-400">Pending:</span>
                                <span class="text-yellow-400 font-semibold"><?php echo $usage['pending_days']; ?> days</span>
                            </div>
                            <?php endif; ?>
                            <?php if ($usage['rejected_days'] > 0): ?>
                            <div class="flex justify-between text-sm">
                                <span class="text-slate-400">Rejected:</span>
                                <span class="text-red-400 font-semibold"><?php echo $usage['rejected_days']; ?> days</span>
                            </div>
                            <?php endif; ?>
                            <div class="w-full bg-slate-700 rounded-full h-2">
                                <div class="bg-gradient-to-r <?php echo $info['color']; ?> h-2 rounded-full transition-all duration-300" 
                                     style="width: <?php echo $remainingPercentage; ?>%"></div>
                            </div>
                            <div class="text-xs text-slate-400 text-center">
                                <?php echo number_format($remainingPercentage, 1); ?>% available
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                                    </div>

                <!-- Leave History for Selected Year -->
                <div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl border border-slate-700/50 overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-700/50 bg-slate-700/30">
                        <h3 class="text-xl font-semibold text-white flex items-center">
                            <i class="fas fa-history text-primary mr-3"></i>
                            Leave Usage for <?php echo $selectedYear; ?>
                        </h3>
                                </div>
                    <div class="p-6">
                        <?php if (!empty($leaveHistory)): ?>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b border-slate-700/50">
                                            <th class="text-left py-3 px-4 text-slate-400">Leave Type</th>
                                            <th class="text-left py-3 px-4 text-slate-400">Start Date</th>
                                            <th class="text-left py-3 px-4 text-slate-400">End Date</th>
                                            <th class="text-left py-3 px-4 text-slate-400">Days</th>
                                            <th class="text-left py-3 px-4 text-slate-400">Status</th>
                                            <th class="text-left py-3 px-4 text-slate-400">Reason</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($leaveHistory as $leave): ?>
                                        <tr class="border-b border-slate-700/50 hover:bg-slate-700/30 transition-colors">
                                            <td class="py-3 px-4 text-white font-semibold">
                                                <div class="flex items-center gap-2">
                                                    <?php 
                                                    $typeInfo = $leaveTypes[$leave['leave_type']] ?? ['name' => ucwords(str_replace('_', ' ', $leave['leave_type'])), 'icon' => 'fas fa-calendar', 'color' => 'from-gray-500 to-slate-500'];
                                                    ?>
                                                    <i class="<?php echo $typeInfo['icon']; ?> text-primary"></i>
                                                    <?php echo $typeInfo['name']; ?>
                                                    <?php if ($leave['is_late']): ?>
                                                        <span class="px-2 py-1 bg-orange-500/20 text-orange-400 text-xs rounded-full">Late</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="py-3 px-4 text-slate-300">
                                                <?php echo date('M d, Y', strtotime($leave['start_date'])); ?>
                                            </td>
                                            <td class="py-3 px-4 text-slate-300">
                                                <?php echo date('M d, Y', strtotime($leave['end_date'])); ?>
                                            </td>
                                            <td class="py-3 px-4 text-slate-300 font-mono">
                                                <div class="flex items-center gap-1">
                                                    <?php echo $leave['days_used']; ?>
                                                    <?php if ($leave['days_requested'] != $leave['days_used']): ?>
                                                        <span class="text-xs text-slate-500">(<?php echo $leave['days_requested']; ?> requested)</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="py-3 px-4">
                                                <span class="px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wide <?php 
                                                    echo $leave['status'] === 'approved' ? 'bg-green-500/20 text-green-400' : 
                                                        ($leave['status'] === 'rejected' ? 'bg-red-500/20 text-red-400' : 
                                                        ($leave['status'] === 'cancelled' ? 'bg-gray-500/20 text-gray-400' : 'bg-yellow-500/20 text-yellow-400')); 
                                                ?>">
                                                    <?php echo ucfirst($leave['status']); ?>
                                                </span>
                                            </td>
                                            <td class="py-3 px-4 text-slate-300">
                                                <div class="max-w-xs">
                                                    <div class="truncate" title="<?php echo htmlspecialchars($leave['reason']); ?>">
                                                        <?php echo htmlspecialchars(substr($leave['reason'], 0, 50)) . (strlen($leave['reason']) > 50 ? '...' : ''); ?>
                                                    </div>
                                                    <?php if ($leave['is_late'] && !empty($leave['late_justification'])): ?>
                                                        <div class="text-xs text-orange-400 mt-1">
                                                            <i class="fas fa-exclamation-triangle mr-1"></i>
                                                            <?php echo htmlspecialchars(substr($leave['late_justification'], 0, 30)) . (strlen($leave['late_justification']) > 30 ? '...' : ''); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="p-4 bg-slate-700/30 rounded-xl">
                                    <div class="flex justify-between items-center">
                                        <span class="text-slate-300">Total Days Used:</span>
                                        <span class="text-2xl font-bold text-white"><?php echo $totalUsed; ?></span>
                                    </div>
                                    <div class="text-sm text-slate-400 mt-1">in <?php echo $selectedYear; ?></div>
                                </div>
                                
                                <div class="p-4 bg-slate-700/30 rounded-xl">
                                    <div class="flex justify-between items-center">
                                        <span class="text-slate-300">Pending Requests:</span>
                                        <span class="text-2xl font-bold text-yellow-400">
                                            <?php echo count(array_filter($leaveHistory, function($leave) { return $leave['status'] === 'pending'; })); ?>
                                        </span>
                                    </div>
                                    <div class="text-sm text-slate-400 mt-1">awaiting approval</div>
                                </div>
                                
                                <div class="p-4 bg-slate-700/30 rounded-xl">
                                    <div class="flex justify-between items-center">
                                        <span class="text-slate-300">Approved Requests:</span>
                                        <span class="text-2xl font-bold text-green-400">
                                            <?php echo count(array_filter($leaveHistory, function($leave) { return $leave['status'] === 'approved'; })); ?>
                                        </span>
                                    </div>
                                    <div class="text-sm text-slate-400 mt-1">successfully processed</div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-12">
                                <i class="fas fa-calendar-times text-4xl text-slate-500 mb-4"></i>
                                <h3 class="text-xl font-semibold text-white mb-2">No Leave Records</h3>
                                <p class="text-slate-400">You haven't used any leave credits in <?php echo $selectedYear; ?>.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // User dropdown toggle function
        function toggleUserDropdown() {
            const dropdown = document.getElementById('userDropdown');
            dropdown.classList.toggle('hidden');
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const userDropdown = document.getElementById('userDropdown');
            const userButton = event.target.closest('[onclick="toggleUserDropdown()"]');
            
            if (userDropdown && !userDropdown.contains(event.target) && !userButton) {
                userDropdown.classList.add('hidden');
            }
        });

        // Year selector functionality
        document.getElementById('yearSelect').addEventListener('change', function() {
            const year = this.value;
            window.location.href = `leave_credits.php?year=${year}`;
        });

        // Refresh page function
        function refreshPage() {
            window.location.reload();
        }

        // Auto-refresh every 30 seconds if there are pending requests
        <?php if (count(array_filter($leaveHistory, function($leave) { return $leave['status'] === 'pending'; })) > 0): ?>
        setTimeout(function() {
            if (confirm('You have pending leave requests. Would you like to refresh to see the latest updates?')) {
                window.location.reload();
            }
        }, 30000);
        <?php endif; ?>
    </script>
</body>
</html> 