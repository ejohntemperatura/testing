<?php
session_start();
require_once '../../../../config/database.php';
require_once '../../../../app/core/services/LeaveCreditsCalculator.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../../auth/views/login.php');
    exit();
}

$calculator = new LeaveCreditsCalculator($pdo);

// Handle CTO earning submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_cto_earning') {
        $employee_id = $_POST['employee_id'];
        $hours_worked = $_POST['hours_worked'];
        $work_type = $_POST['work_type'];
        $description = $_POST['description'];
        $approved_by = $_SESSION['user_id'];
        
        $result = $calculator->addCTOEarnings($employee_id, $hours_worked, $work_type, $description, $approved_by);
        
        if ($result) {
            $_SESSION['success'] = "CTO earning added successfully: {$result} hours";
        } else {
            $_SESSION['error'] = "Failed to add CTO earning";
        }
        
        header('Location: cto_management.php');
        exit();
    }
}

// Get all employees for dropdown
$stmt = $pdo->query("SELECT id, name, email, department FROM employees WHERE role != 'admin' ORDER BY name");
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get CTO earnings history
$stmt = $pdo->query("
    SELECT ce.*, e.name as employee_name, e.department, 
           approver.name as approved_by_name
    FROM cto_earnings ce
    JOIN employees e ON ce.employee_id = e.id
    LEFT JOIN employees approver ON ce.approved_by = approver.id
    ORDER BY ce.created_at DESC
    LIMIT 50
");
$ctoEarnings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get CTO usage history
$stmt = $pdo->query("
    SELECT cu.*, e.name as employee_name, e.department,
           lr.start_date, lr.end_date, lr.leave_type
    FROM cto_usage cu
    JOIN employees e ON cu.employee_id = e.id
    LEFT JOIN leave_requests lr ON cu.leave_request_id = lr.id
    ORDER BY cu.created_at DESC
    LIMIT 50
");
$ctoUsage = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get employees with CTO balances
$stmt = $pdo->query("
    SELECT e.id, e.name, e.department, e.cto_balance
    FROM employees e
    WHERE e.cto_balance > 0
    ORDER BY e.cto_balance DESC
");
$employeesWithCTO = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="../../../../assets/css/tailwind.css">
    <link rel="stylesheet" href="../../../../assets/libs/fontawesome/css/all.min.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ELMS - CTO Management</title>
    <link rel="stylesheet" href="../../../../assets/css/style.css">
    <link rel="stylesheet" href="../../../../assets/css/dark-theme.css">
</head>
<body class="bg-slate-900 text-white min-h-screen">
    <?php include '../../../../includes/unified_navbar.php'; ?>

    <div class="flex">
        <!-- Left Sidebar -->
        <aside class="fixed left-0 top-16 h-[calc(100vh-4rem)] w-64 bg-slate-900 border-r border-slate-800 overflow-y-auto z-40">
            <nav class="p-4 space-y-2">
                <a href="dashboard.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                    <i class="fas fa-tachometer-alt w-5"></i>
                    <span>Dashboard</span>
                </a>
                
                <div class="space-y-1">
                    <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider px-4 py-2">Leave Management</h3>
                    
                    <a href="leave_management.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                        <i class="fas fa-calendar-alt w-5"></i>
                        <span>Leave Management</span>
                    </a>
                    
                    <a href="leave_alerts.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                        <i class="fas fa-bell w-5"></i>
                        <span>Leave Alerts</span>
                    </a>
                    
                    <a href="cto_management.php" class="flex items-center space-x-3 px-4 py-3 text-white bg-purple-500/20 rounded-lg border border-purple-500/30">
                        <i class="fas fa-clock w-5"></i>
                        <span>CTO Management</span>
                    </a>
                </div>
                
                <div class="space-y-1">
                    <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider px-4 py-2">Reports</h3>
                    
                    <a href="reports.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                        <i class="fas fa-chart-bar w-5"></i>
                        <span>Reports</span>
                    </a>
                </div>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <main class="pt-24 flex-1 ml-64 px-6 pb-6">
            <div class="max-w-7xl mx-auto">
                <!-- Header -->
                <div class="mb-8">
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 bg-gradient-to-r from-purple-500 to-pink-500 rounded-2xl flex items-center justify-center">
                            <i class="fas fa-clock text-2xl text-white"></i>
                        </div>
                        <div>
                            <h1 class="text-3xl font-bold text-white mb-2">CTO Management</h1>
                            <p class="text-slate-400">Manage Compensatory Time Off (CTO) earnings and usage</p>
                        </div>
                    </div>
                </div>

                <!-- Success/Error Messages -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="bg-green-500/20 border border-green-500/30 text-green-400 px-4 py-3 rounded-lg mb-6">
                        <i class="fas fa-check-circle mr-2"></i>
                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="bg-red-500/20 border border-red-500/30 text-red-400 px-4 py-3 rounded-lg mb-6">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <!-- CTO Information -->
                <div class="bg-purple-500/20 border border-purple-500/30 rounded-2xl p-6 mb-8">
                    <div class="flex items-start gap-4">
                        <i class="fas fa-info-circle text-purple-400 text-2xl mt-1"></i>
                        <div>
                            <h3 class="text-xl font-semibold text-purple-400 mb-2">Compensatory Time Off (CTO)</h3>
                            <p class="text-slate-300 mb-4">CTO is earned through overtime work, holiday work, and special assignments:</p>
                            <ul class="text-slate-300 space-y-1 text-sm">
                                <li>• <strong>Overtime:</strong> 1:1 ratio (1 hour worked = 1 hour CTO)</li>
                                <li>• <strong>Holiday Work:</strong> 1.5:1 ratio (1 hour worked = 1.5 hours CTO)</li>
                                <li>• <strong>Weekend Work:</strong> 1:1 ratio (1 hour worked = 1 hour CTO)</li>
                                <li>• <strong>Special Assignments:</strong> 1:1 ratio (1 hour worked = 1 hour CTO)</li>
                                <li>• <strong>Maximum Accumulation:</strong> 40 hours</li>
                                <li>• <strong>Expiration:</strong> 6 months from earning date</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Add CTO Earning Form -->
                <div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl border border-slate-700/50 p-6 mb-8">
                    <h3 class="text-xl font-semibold text-white mb-6 flex items-center">
                        <i class="fas fa-plus-circle text-purple-400 mr-3"></i>
                        Add CTO Earning
                    </h3>
                    
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="add_cto_earning">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-300 mb-2">Employee</label>
                                <select name="employee_id" required class="w-full px-4 py-3 bg-slate-700 border border-slate-600 rounded-lg text-white focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                    <option value="">Select Employee</option>
                                    <?php foreach ($employees as $employee): ?>
                                        <option value="<?php echo $employee['id']; ?>">
                                            <?php echo htmlspecialchars($employee['name']); ?> - <?php echo htmlspecialchars($employee['department']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-slate-300 mb-2">Work Type</label>
                                <select name="work_type" required class="w-full px-4 py-3 bg-slate-700 border border-slate-600 rounded-lg text-white focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                    <option value="">Select Work Type</option>
                                    <option value="overtime">Overtime (1:1 ratio)</option>
                                    <option value="holiday">Holiday Work (1.5:1 ratio)</option>
                                    <option value="weekend">Weekend Work (1:1 ratio)</option>
                                    <option value="special_assignment">Special Assignment (1:1 ratio)</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-slate-300 mb-2">Hours Worked</label>
                                <input type="number" name="hours_worked" step="0.5" min="0.5" max="40" required 
                                       class="w-full px-4 py-3 bg-slate-700 border border-slate-600 rounded-lg text-white focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                                       placeholder="Enter hours worked">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-slate-300 mb-2">Description</label>
                                <input type="text" name="description" 
                                       class="w-full px-4 py-3 bg-slate-700 border border-slate-600 rounded-lg text-white focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                                       placeholder="Brief description of work performed">
                            </div>
                        </div>
                        
                        <div class="flex justify-end">
                            <button type="submit" class="px-6 py-3 bg-purple-600 hover:bg-purple-700 text-white font-semibold rounded-lg transition-colors">
                                <i class="fas fa-plus mr-2"></i>
                                Add CTO Earning
                            </button>
                        </div>
                    </form>
                </div>

                <!-- CTO Balances -->
                <div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl border border-slate-700/50 p-6 mb-8">
                    <h3 class="text-xl font-semibold text-white mb-6 flex items-center">
                        <i class="fas fa-wallet text-green-400 mr-3"></i>
                        Current CTO Balances
                    </h3>
                    
                    <?php if (!empty($employeesWithCTO)): ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <?php foreach ($employeesWithCTO as $employee): ?>
                                <div class="bg-slate-700/30 rounded-xl p-4">
                                    <div class="flex items-center justify-between mb-2">
                                        <h4 class="font-semibold text-white"><?php echo htmlspecialchars($employee['name']); ?></h4>
                                        <span class="text-green-400 font-bold"><?php echo number_format($employee['cto_balance'], 1); ?>h</span>
                                    </div>
                                    <p class="text-slate-400 text-sm"><?php echo htmlspecialchars($employee['department']); ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <i class="fas fa-wallet text-4xl text-slate-500 mb-4"></i>
                            <p class="text-slate-400">No employees currently have CTO balances</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- CTO Earnings History -->
                <div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl border border-slate-700/50 p-6 mb-8">
                    <h3 class="text-xl font-semibold text-white mb-6 flex items-center">
                        <i class="fas fa-history text-blue-400 mr-3"></i>
                        CTO Earnings History
                    </h3>
                    
                    <?php if (!empty($ctoEarnings)): ?>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-slate-700/50">
                                        <th class="text-left py-3 px-4 text-slate-400">Employee</th>
                                        <th class="text-left py-3 px-4 text-slate-400">Work Type</th>
                                        <th class="text-left py-3 px-4 text-slate-400">Hours Worked</th>
                                        <th class="text-left py-3 px-4 text-slate-400">CTO Earned</th>
                                        <th class="text-left py-3 px-4 text-slate-400">Date</th>
                                        <th class="text-left py-3 px-4 text-slate-400">Status</th>
                                        <th class="text-left py-3 px-4 text-slate-400">Approved By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ctoEarnings as $earning): ?>
                                        <tr class="border-b border-slate-700/50 hover:bg-slate-700/30 transition-colors">
                                            <td class="py-3 px-4 text-white font-semibold">
                                                <?php echo htmlspecialchars($earning['employee_name']); ?>
                                                <div class="text-xs text-slate-400"><?php echo htmlspecialchars($earning['department']); ?></div>
                                            </td>
                                            <td class="py-3 px-4 text-slate-300">
                                                <span class="px-2 py-1 bg-purple-500/20 text-purple-400 text-xs rounded-full">
                                                    <?php echo ucwords(str_replace('_', ' ', $earning['work_type'])); ?>
                                                </span>
                                            </td>
                                            <td class="py-3 px-4 text-slate-300 font-mono">
                                                <?php echo number_format($earning['hours_worked'], 1); ?>h
                                            </td>
                                            <td class="py-3 px-4 text-green-400 font-mono font-semibold">
                                                +<?php echo number_format($earning['cto_earned'], 1); ?>h
                                            </td>
                                            <td class="py-3 px-4 text-slate-300">
                                                <?php echo date('M d, Y', strtotime($earning['earned_date'])); ?>
                                            </td>
                                            <td class="py-3 px-4">
                                                <span class="px-2 py-1 rounded-full text-xs font-semibold <?php 
                                                    echo $earning['status'] === 'approved' ? 'bg-green-500/20 text-green-400' : 
                                                        ($earning['status'] === 'rejected' ? 'bg-red-500/20 text-red-400' : 'bg-yellow-500/20 text-yellow-400'); 
                                                ?>">
                                                    <?php echo ucfirst($earning['status']); ?>
                                                </span>
                                            </td>
                                            <td class="py-3 px-4 text-slate-300">
                                                <?php echo $earning['approved_by_name'] ? htmlspecialchars($earning['approved_by_name']) : 'Pending'; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <i class="fas fa-history text-4xl text-slate-500 mb-4"></i>
                            <p class="text-slate-400">No CTO earnings recorded yet</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- CTO Usage History -->
                <div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl border border-slate-700/50 p-6">
                    <h3 class="text-xl font-semibold text-white mb-6 flex items-center">
                        <i class="fas fa-calendar-check text-orange-400 mr-3"></i>
                        CTO Usage History
                    </h3>
                    
                    <?php if (!empty($ctoUsage)): ?>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-slate-700/50">
                                        <th class="text-left py-3 px-4 text-slate-400">Employee</th>
                                        <th class="text-left py-3 px-4 text-slate-400">Hours Used</th>
                                        <th class="text-left py-3 px-4 text-slate-400">Date</th>
                                        <th class="text-left py-3 px-4 text-slate-400">Leave Period</th>
                                        <th class="text-left py-3 px-4 text-slate-400">Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ctoUsage as $usage): ?>
                                        <tr class="border-b border-slate-700/50 hover:bg-slate-700/30 transition-colors">
                                            <td class="py-3 px-4 text-white font-semibold">
                                                <?php echo htmlspecialchars($usage['employee_name']); ?>
                                                <div class="text-xs text-slate-400"><?php echo htmlspecialchars($usage['department']); ?></div>
                                            </td>
                                            <td class="py-3 px-4 text-red-400 font-mono font-semibold">
                                                -<?php echo number_format($usage['hours_used'], 1); ?>h
                                            </td>
                                            <td class="py-3 px-4 text-slate-300">
                                                <?php echo date('M d, Y', strtotime($usage['used_date'])); ?>
                                            </td>
                                            <td class="py-3 px-4 text-slate-300">
                                                <?php if ($usage['start_date'] && $usage['end_date']): ?>
                                                    <?php echo date('M d', strtotime($usage['start_date'])); ?> - <?php echo date('M d, Y', strtotime($usage['end_date'])); ?>
                                                    <div class="text-xs text-slate-400"><?php echo ucwords(str_replace('_', ' ', $usage['leave_type'])); ?></div>
                                                <?php else: ?>
                                                    <span class="text-slate-500">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-3 px-4 text-slate-300">
                                                <?php echo htmlspecialchars($usage['description']); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <i class="fas fa-calendar-check text-4xl text-slate-500 mb-4"></i>
                            <p class="text-slate-400">No CTO usage recorded yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Auto-refresh every 60 seconds
        setTimeout(function() {
            window.location.reload();
        }, 60000);
    </script>
</body>
</html>
