<?php
session_start();
require_once '../../../../config/database.php';
require_once '../../../../config/leave_types.php';

// Check if user is logged in and is an admin or manager (department head)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin','manager','director'])) {
    header('Location: ../../../auth/views/login.php');
    exit();
}

// Get admin details
$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

// Admin interface is read-only - no bulk actions needed
// Handle bulk actions (DISABLED - Admin is read-only)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Admin is read-only - no actions allowed
    $_SESSION['error'] = 'Admin interface is read-only. Approval/rejection is handled by Department Heads and Directors.';
    header('Location: leave_management.php');
    exit();
}

if (false) { // Disabled bulk actions
    if (isset($_POST['bulk_action']) && isset($_POST['selected_requests'])) {
        $action = $_POST['bulk_action'];
        $selected_ids = $_POST['selected_requests'];
        
        if (!empty($selected_ids)) {
            $placeholders = str_repeat('?,', count($selected_ids) - 1) . '?';
            
            try {
                $pdo->beginTransaction();
                
                // If approving, ensure balances are sufficient and deduct
                if ($action === 'approved') {
                    // Fetch requests
                    $stmt = $pdo->prepare("SELECT lr.*, e.id as emp_id FROM leave_requests lr JOIN employees e ON lr.employee_id = e.id WHERE lr.id IN ($placeholders)");
                    $stmt->execute($selected_ids);
                    $toApprove = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($toApprove as $req) {
                        $start = new DateTime($req['start_date']);
                        $end = new DateTime($req['end_date']);
                        $days = $start->diff($end)->days + 1;
                        $leave_type = strtolower(trim($req['leave_type']));
                        $balance_field = $leave_type . '_leave_balance';
                        
                        // Check if balance field exists and has sufficient balance
                        $stmt = $pdo->prepare("SELECT {$balance_field} FROM employees WHERE id = ?");
                        $stmt->execute([$req['emp_id']]);
                        $current_balance = $stmt->fetchColumn();
                        
                        if ($current_balance < $days) {
                            throw new PDOException("Insufficient leave balance for employee ID {$req['emp_id']}. Available: {$current_balance} days, Required: {$days} days");
                        }
                        
                        // Deduct leave balance
                        $stmt = $pdo->prepare("UPDATE employees SET {$balance_field} = {$balance_field} - ? WHERE id = ?");
                        $stmt->execute([$days, $req['emp_id']]);
                    }
                }

                // Update status for selected requests
                $stmt = $pdo->prepare("UPDATE leave_requests SET status = ? WHERE id IN ($placeholders)");
                $params = array_merge([$action], $selected_ids);
                $stmt->execute($params);
                
                // Send email notifications
                $stmt = $pdo->prepare("
                    SELECT lr.*, e.name as employee_name, e.email as employee_email 
                    FROM leave_requests lr 
                    JOIN employees e ON lr.employee_id = e.id 
                    WHERE lr.id IN ($placeholders)
                ");
                $stmt->execute($selected_ids);
                $requests = $stmt->fetchAll();
                
                require_once '../../../../app/core/services/EmailService.php';
                $emailService = new EmailService();
                foreach ($requests as $request) {
                    $emailService->sendLeaveStatusNotification(
                        $request['employee_email'],
                        $request['employee_name'],
                        $action,
                        $request['start_date'],
                        $request['end_date'],
                        $request['leave_type'] ?? null,
                        null,
                        null,
                        null,
                        $request['original_leave_type'] ?? null
                    );
                }
                
                $pdo->commit();
                $_SESSION['success'] = "Successfully updated " . count($selected_ids) . " leave request(s) to " . ucfirst($action);
            } catch (PDOException $e) {
                $pdo->rollBack();
                $_SESSION['error'] = "Error updating leave requests: " . $e->getMessage();
            }
        }
    }
}

// Function to send email notification
function sendLeaveStatusEmail($employee_email, $employee_name, $status, $start_date, $end_date) {
    if (!filter_var($employee_email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $to = $employee_email;
    $subject = "Leave Request Update - ELMS";
    
    $message = "
    <html>
    <head>
        <title>Leave Request Update</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #4CAF50; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background-color: #f9f9f9; }
            .details { background-color: #fff; padding: 15px; border-radius: 5px; margin: 20px 0; border: 1px solid #ddd; }
            .status { font-weight: bold; color: #4CAF50; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 0.9em; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Leave Request Update</h2>
            </div>
            <div class='content'>
                <p>Dear {$employee_name},</p>
                <p>Your leave request has been <span class='status'>{$status}</span>.</p>
                <div class='details'>
                    <h3>Leave Details:</h3>
                    <p><strong>Start Date:</strong> {$start_date}</p>
                    <p><strong>End Date:</strong> {$end_date}</p>
                </div>
                <p>If you have any questions, please contact your supervisor or the HR department.</p>
            </div>
            <div class='footer'>
                <p>This is an automated message from the E-Learning Management System (ELMS).</p>
                <p>Please do not reply to this email.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: ELMS <noreply@elms.com>" . "\r\n";
    
    return mail($to, $subject, $message, $headers);
}

// Admin interface is read-only - no approval/rejection functionality
if (isset($_POST['update_status'])) {
    $_SESSION['error'] = 'Admin interface is read-only. Leave approvals are handled by Department Heads and Directors.';
    header('Location: leave_management.php');
    exit();
}

// Build query with filters
$where_conditions = [];
$params = [];

if (isset($_GET['status']) && $_GET['status'] !== '') {
    $where_conditions[] = "lr.status = ?";
    $params[] = $_GET['status'];
}

if (isset($_GET['employee']) && $_GET['employee'] !== '') {
    $where_conditions[] = "e.name LIKE ?";
    $params[] = '%' . $_GET['employee'] . '%';
}

if (isset($_GET['leave_type']) && $_GET['leave_type'] !== '') {
    $where_conditions[] = "lr.leave_type = ?";
    $params[] = $_GET['leave_type'];
}

// Remove the base conditions to show all requests
// $where_conditions[] = "(lr.dept_head_approval IS NOT NULL AND lr.dept_head_approval != 'pending')";
// $where_conditions[] = "(lr.director_approval IS NOT NULL AND lr.director_approval != 'pending')";

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Fetch leave requests with filters
$initial_limit = 10; // Show only 10 initially

// Get total count of leave requests
try {
    $count_query = "
        SELECT COUNT(*) as total
        FROM leave_requests lr 
        JOIN employees e ON lr.employee_id = e.id 
        LEFT JOIN employees dept_approver ON lr.dept_head_approved_by = dept_approver.id
        LEFT JOIN employees director_approver ON lr.director_approved_by = director_approver.id
        LEFT JOIN employees admin_approver ON lr.admin_approved_by = admin_approver.id
        $where_clause
    ";
    $stmt = $pdo->prepare($count_query);
    $stmt->execute($params);
    $total_requests = $stmt->fetch()['total'];
} catch(PDOException $e) {
    $total_requests = 0;
}

// Fetch initial leave requests (limited)
try {
    $query = "
        SELECT lr.*, e.name as employee_name, e.email as employee_email, e.department,
               dept_approver.name as dept_head_name, director_approver.name as director_name, admin_approver.name as admin_name,
               CASE 
                   WHEN lr.approved_days IS NOT NULL AND lr.approved_days > 0 
                   THEN lr.approved_days
                   ELSE DATEDIFF(lr.end_date, lr.start_date) + 1 
               END as actual_days_approved
        FROM leave_requests lr 
        JOIN employees e ON lr.employee_id = e.id 
        LEFT JOIN employees dept_approver ON lr.dept_head_approved_by = dept_approver.id
        LEFT JOIN employees director_approver ON lr.director_approved_by = director_approver.id
        LEFT JOIN employees admin_approver ON lr.admin_approved_by = admin_approver.id
        $where_clause
        ORDER BY lr.created_at DESC
        LIMIT " . intval($initial_limit) . "
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $leave_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error = "Error: " . $e->getMessage();
}

// Get leave types from centralized configuration
$leaveTypes = getLeaveTypes();
$leave_types = array_keys($leaveTypes);

// Get all employees for filter
$stmt = $pdo->query("SELECT name FROM employees ORDER BY name");
$employees = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Management - ELMS</title>
    <!-- OFFLINE Tailwind CSS - No internet required! -->
    <link rel="stylesheet" href="../../../../assets/css/tailwind.css">
        <!-- Font Awesome Local - No internet required! -->
    <link rel="stylesheet" href="../../../../assets/libs/fontawesome/css/all.min.css">
    <!-- Font Awesome Local - No internet required! -->
    
    <link rel="stylesheet" href="../../../../assets/css/style.css">
    <link rel="stylesheet" href="../../../../assets/css/dark-theme.css">
    <link rel="stylesheet" href="../../../../assets/css/admin_style.css">
    
</head>
<body class="bg-slate-900 text-white">
    <?php include '../../../../includes/unified_navbar.php'; ?>

    <div class="flex">
        <!-- Left Sidebar -->
        <aside class="fixed left-0 top-16 h-screen w-64 bg-slate-900 border-r border-slate-800 overflow-y-auto z-40">
            <nav class="p-4 space-y-2">
                <!-- Active Navigation Item (Dashboard) -->
                <a href="dashboard.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                    <i class="fas fa-tachometer-alt w-5"></i>
                    <span>Dashboard</span>
                </a>
                
                <!-- Section Headers -->
                <div class="space-y-1">
                    <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider px-4 py-2">Management</h3>
                    
                    <!-- Navigation Items -->
                    <a href="manage_user.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                        <i class="fas fa-users-cog w-5"></i>
                        <span>Manage Users</span>
                    </a>
                    
                    <a href="leave_management.php" class="flex items-center space-x-3 px-4 py-3 text-white bg-blue-500/20 rounded-lg border border-blue-500/30">
                        <i class="fas fa-calendar-check w-5"></i>
                        <span>Leave Management</span>
                        <span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full" id="pendingLeaveBadge" style="display: none;">0</span>
                    </a>
                    
                    <a href="leave_alerts.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                        <i class="fas fa-bell w-5"></i>
                        <span>Leave Alerts</span>
                    </a>
                </div>
                
                <div class="space-y-1">
                    <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider px-4 py-2">Reports</h3>
                    
                    <a href="calendar.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                        <i class="fas fa-calendar w-5"></i>
                        <span>Leave Chart</span>
                    </a>
                    
                    <a href="reports.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                        <i class="fas fa-file-alt w-5"></i>
                        <span>Reports</span>
                    </a>
                </div>
                
            </nav>
        </aside>

    <!-- Main Content -->
        <main class="flex-1 ml-64 p-6 pt-24">
            <div class="max-w-7xl mx-auto">

                <!-- Page Header -->
                <div class="mb-8">
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 bg-gradient-to-r from-primary to-accent rounded-2xl flex items-center justify-center">
                            <i class="fas fa-calendar-check text-2xl text-white"></i>
                        </div>
                        <div>
                            <h1 class="text-3xl font-bold text-white mb-2">Leave Management</h1>
                            <p class="text-slate-400">Review and manage all leave requests across the organization</p>
                    </div>
                </div>
            </div>

                <!-- Success Message -->
            <?php if (isset($_SESSION['success'])): ?>
                    <div class="bg-green-500/20 border border-green-500/30 text-green-400 p-4 rounded-xl mb-6 flex items-center">
                        <i class="fas fa-check-circle mr-3"></i>
                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

                <!-- Error Message -->
            <?php if (isset($_SESSION['error'])): ?>
                    <div class="bg-red-500/20 border border-red-500/30 text-red-400 p-4 rounded-xl mb-6 flex items-center">
                        <i class="fas fa-exclamation-circle mr-3"></i>
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <!-- Filters -->
                <div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden mb-8">
                    <div class="px-6 py-4 border-b border-slate-700 bg-slate-700/30">
                        <h3 class="text-xl font-semibold text-white flex items-center">
                            <i class="fas fa-filter text-primary mr-3"></i>Filters
                        </h3>
                        </div>
                    <div class="p-6">
                        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-6">
                            <div>
                                <label for="status" class="block text-sm font-semibold text-slate-300 mb-2">Status</label>
                                <select name="status" id="status" class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        <option value="">All Status</option>
                                        <option value="pending" <?php echo (isset($_GET['status']) && $_GET['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                        <option value="approved" <?php echo (isset($_GET['status']) && $_GET['status'] == 'approved') ? 'selected' : ''; ?>>Approved</option>
                                        <option value="rejected" <?php echo (isset($_GET['status']) && $_GET['status'] == 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                                    </select>
                                </div>
                            <div>
                                <label for="employee" class="block text-sm font-semibold text-slate-300 mb-2">Employee</label>
                                <input type="text" name="employee" id="employee" 
                                           value="<?php echo isset($_GET['employee']) ? htmlspecialchars($_GET['employee']) : ''; ?>" 
                                       placeholder="Search by employee name"
                                       class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                </div>
                            <div>
                                <label for="leave_type" class="block text-sm font-semibold text-slate-300 mb-2">Leave Type</label>
                                <select name="leave_type" id="leave_type" class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        <option value="">All Types</option>
                                        <?php foreach ($leave_types as $type): ?>
                                            <option value="<?php echo htmlspecialchars($type); ?>" 
                                                    <?php echo (isset($_GET['leave_type']) && $_GET['leave_type'] == $type) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($leaveTypes[$type]['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <div class="flex items-end">
                                <button type="submit" class="w-full bg-primary hover:bg-primary/80 text-white px-4 py-3 rounded-xl transition-colors flex items-center justify-center">
                                    <i class="fas fa-search mr-2"></i>Filter
                                        </button>
                                </div>
                            </form>
                </div>
            </div>

            <!-- Leave Requests Table -->
                <div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden mb-8">
                    <div class="px-6 py-4 border-b border-slate-700 bg-slate-700/30">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-xl font-semibold text-white flex items-center">
                                <i class="fas fa-list text-primary mr-3"></i>Leave Requests
                            </h3>
                            <div class="text-slate-400 text-sm">
                                <i class="fas fa-info-circle mr-2"></i>
                                Admin View - Read Only Access. You can view all leave request details and print approved leave forms. Approval/rejection is handled by Department Heads and Directors.
                            </div>
                            </div>
                        <div class="bg-blue-500/20 border border-blue-500/30 rounded-xl p-4">
                            <div class="flex items-center">
                                <i class="fas fa-info-circle text-blue-400 mr-3"></i>
                                <div>
                                    <p class="text-white font-semibold">All leave requests are visible here.</p>
                                    <p class="text-slate-300 text-sm">You can only take action (approve/reject) on requests where both the Department Head and Director have already made their decisions.</p>
                            </div>
                        </div>
                        </div>
                    </div>
                    <div class="p-6">
                            <form id="bulkForm" method="POST">
                                <input type="hidden" name="bulk_action" id="bulkAction">
                            <div class="overflow-x-auto">
                                <table class="w-full">
                                        <thead>
                                        <tr class="border-b border-slate-700">
                                            <th class="text-left py-3 px-4 text-sm font-semibold text-slate-300">Employee</th>
                                            <th class="text-left py-3 px-4 text-sm font-semibold text-slate-300">Department</th>
                                            <th class="text-left py-3 px-4 text-sm font-semibold text-slate-300">Leave Type</th>
                                            <th class="text-left py-3 px-4 text-sm font-semibold text-slate-300">Start Date</th>
                                            <th class="text-left py-3 px-4 text-sm font-semibold text-slate-300">End Date</th>
                                            <th class="text-left py-3 px-4 text-sm font-semibold text-slate-300">Days</th>
                                            <th class="text-left py-3 px-4 text-sm font-semibold text-slate-300">Reason</th>
                                            <th class="text-left py-3 px-4 text-sm font-semibold text-slate-300">Dept Head</th>
                                            <th class="text-left py-3 px-4 text-sm font-semibold text-slate-300">Director</th>
                                            <th class="text-left py-3 px-4 text-sm font-semibold text-slate-300">Final Status</th>
                                            <th class="text-left py-3 px-4 text-sm font-semibold text-slate-300">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($leave_requests)): ?>
                                                <tr>
                                                <td colspan="11" class="text-center py-12">
                                                    <div class="w-16 h-16 bg-slate-700/50 rounded-full flex items-center justify-center mx-auto mb-4">
                                                        <i class="fas fa-info-circle text-slate-400 text-2xl"></i>
                                                        </div>
                                                    <h4 class="text-lg font-semibold text-white mb-2">No Leave Requests Found</h4>
                                                    <p class="text-slate-400">No leave requests match your current filter criteria.<br>
                                                    Try adjusting your filters or check back later.</p>
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                            <?php foreach ($leave_requests as $request): ?>
                                            <tr data-request-id="<?php echo $request['id']; ?>" class="border-b border-slate-700/30 hover:bg-slate-700/30 transition-colors">
                                                <td class="py-4 px-4">
                                                    <div class="font-semibold text-white"><?php echo htmlspecialchars($request['employee_name']); ?></div>
                                                </td>
                                                <td class="py-4 px-4 text-slate-300"><?php echo htmlspecialchars($request['department']); ?></td>
                                                <td class="py-4 px-4">
                                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-primary/20 text-primary border border-primary/30">
                                                        <?php echo getLeaveTypeDisplayName($request['leave_type'], $request['original_leave_type'] ?? null, $leaveTypes); ?>
                                                    </span>
                                                </td>
                                                <td class="py-4 px-4 text-slate-300"><?php echo date('M d, Y', strtotime($request['start_date'])); ?></td>
                                                <td class="py-4 px-4 text-slate-300">
                                                    <?php 
                                                    // Use approved end date if available, otherwise original end date
                                                    if ($request['status'] === 'approved' && $request['approved_days'] && $request['approved_days'] != $request['days_requested']) {
                                                        $approved_end_date = date('Y-m-d', strtotime($request['start_date'] . ' +' . ($request['approved_days'] - 1) . ' days'));
                                                        echo date('M d, Y', strtotime($approved_end_date));
                                                    } else {
                                                        echo date('M d, Y', strtotime($request['end_date']));
                                                    }
                                                    ?>
                                                </td>
                                                <td class="py-4 px-4">
                                                    <span class="inline-flex items-center justify-center w-8 h-8 bg-slate-700 rounded-full text-sm font-semibold text-white">
                                                    <?php 
                                                    // Use approved days if available, otherwise calculate from dates
                                                    if ($request['status'] === 'approved' && $request['approved_days'] && $request['approved_days'] > 0) {
                                                        echo $request['approved_days'];
                                                    } else {
                                                        $start = new DateTime($request['start_date']);
                                                        $end = new DateTime($request['end_date']);
                                                        $days = $start->diff($end)->days + 1;
                                                        echo $days;
                                                    }
                                                    ?>
                                                    </span>
                                                </td>
                                                <td class="py-4 px-4">
                                                    <span class="text-slate-300 max-w-[150px] truncate block" 
                                                          title="<?php echo htmlspecialchars($request['reason']); ?>">
                                                        <?php echo htmlspecialchars($request['reason']); ?>
                                                    </span>
                                                </td>
                                                <!-- Department Head Approval -->
                                                <td class="py-4 px-4">
                                                    <?php 
                                                    $dept_status = $request['dept_head_approval'] ?? 'pending';
                                                    $dept_color = $dept_status == 'approved' ? 'green' : ($dept_status == 'rejected' ? 'red' : 'yellow');
                                                    ?>
                                                    <div class="flex flex-col gap-1">
                                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-<?php echo $dept_color; ?>-500/20 text-<?php echo $dept_color; ?>-400 border border-<?php echo $dept_color; ?>-500/30" data-dept-status="<?php echo ucfirst($dept_status); ?>">
                                                        <?php echo ucfirst($dept_status); ?>
                                                    </span>
                                                    </div>
                                                </td>
                                                <!-- Director Approval -->
                                                <td class="py-4 px-4">
                                                    <?php 
                                                    $director_status = $request['director_approval'] ?? 'pending';
                                                    $director_color = $director_status == 'approved' ? 'green' : ($director_status == 'rejected' ? 'red' : 'yellow');
                                                    ?>
                                                    <div class="flex flex-col gap-1">
                                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-<?php echo $director_color; ?>-500/20 text-<?php echo $director_color; ?>-400 border border-<?php echo $director_color; ?>-500/30" data-director-status="<?php echo ucfirst($director_status); ?>">
                                                        <?php echo ucfirst($director_status); ?>
                                                    </span>
                                                    </div>
                                                </td>
                                                <!-- Final Status -->
                                                <td class="py-4 px-4">
                                                    <div class="flex items-center gap-2">
                                                        <?php 
                                                        $status_color = $request['status'] == 'approved' ? 'green' : 
                                                                        ($request['status'] == 'pending' ? 'yellow' : 'red'); 
                                                        ?>
                                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-<?php echo $status_color; ?>-500/20 text-<?php echo $status_color; ?>-400 border border-<?php echo $status_color; ?>-500/30" data-final-status="<?php echo ucfirst($request['status']); ?>">
                                                            <?php echo ucfirst($request['status']); ?>
                                                        </span>
                                                        <button type="button" class="p-1 text-slate-400 hover:text-white transition-colors" 
                                                                onclick="showStatusInfo(<?php echo $request['id']; ?>)"
                                                                title="View Status Details">
                                                            <i class="fas fa-info-circle text-sm"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                                <!-- Actions -->
                                                <td class="py-4 px-4">
                                                    <?php 
                                                    // Check approval status at each level
                                                    $dept_status = $request['dept_head_approval'] ?? 'pending';
                                                    $director_status = $request['director_approval'] ?? 'pending';
                                                    
                                                    $dept_approved = $dept_status == 'approved';
                                                    $director_approved = $director_status == 'approved';
                                                    $both_approved = $dept_approved && $director_approved;
                                                    $any_rejected = $dept_status == 'rejected' || $director_status == 'rejected';
                                                    ?>
                                                    
                                                    <div class="flex flex-col gap-2">
                                                    <?php if ($both_approved): ?>
                                                        <!-- Print functionality for approved requests -->
                                                        <a href="print_leave_request.php?id=<?php echo $request['id']; ?>" target="_blank" class="inline-flex items-center px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-lg transition-colors">
                                                            <i class="fas fa-print mr-1"></i> Print
                                                        </a>
                                                    <?php elseif ($any_rejected): ?>
                                                        <div class="text-center">
                                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-500/20 text-red-400 border border-red-500/30">Rejected</span>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="text-center">
                                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-500/20 text-yellow-400 border border-yellow-500/30">Waiting</span>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                        <button type="button" class="inline-flex items-center px-3 py-1 bg-primary hover:bg-primary/80 text-white text-xs font-medium rounded-lg transition-colors" 
                                                            onclick="viewRequestDetails(<?php echo $request['id']; ?>)"
                                                            title="View Details">
                                                            <i class="fas fa-eye mr-1"></i>View
                                                    </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                            <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <?php if ($total_requests > $initial_limit): ?>
                                <div class="text-center mt-6 px-6 pb-6">
                                    <button type="button" id="loadMoreBtn" onclick="handleViewMoreClick()" class="bg-primary/20 hover:bg-primary/30 text-primary border border-primary/30 font-semibold py-3 px-6 rounded-xl transition-colors">
                                        <i class="fas fa-plus mr-2"></i>View More (<?php echo $total_requests - $initial_limit; ?> more)
                                    </button>
                                    <button type="button" id="showLessBtn" onclick="handleShowLessClick()" class="hidden bg-slate-600/20 hover:bg-slate-600/30 text-slate-400 border border-slate-600/30 font-semibold py-3 px-6 rounded-xl transition-colors ml-4">
                                        <i class="fas fa-minus mr-2"></i>Show Less
                                    </button>
                                </div>
                                <!-- Hidden container for additional requests -->
                                <div id="additionalRequests" class="hidden mt-6"></div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Request Details Modal -->
    <div id="requestDetailsModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
        <div class="bg-slate-800 rounded-2xl border border-slate-700 max-w-4xl w-full max-h-[90vh] overflow-y-auto">
            <div class="px-6 py-4 border-b border-slate-700 bg-slate-700/30">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-semibold text-white">Leave Request Details</h3>
                    <button type="button" class="text-slate-400 hover:text-white transition-colors" onclick="closeRequestModal()">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
            </div>
            <div class="p-6" id="requestDetailsContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>

    <script>
        // Pass leave types data to JavaScript
        window.leaveTypes = <?php echo json_encode($leaveTypes); ?>;
        
        // Helper function to get leave type display name in JavaScript
        function getLeaveTypeDisplayNameJS(leaveType, originalLeaveType = null) {
            const leaveTypes = window.leaveTypes;
            if (!leaveTypes) return leaveType;
            
            // Check if leave is without pay
            let isWithoutPay = false;
            
            // If leave_type is explicitly 'without_pay', it's without pay
            if (leaveType === 'without_pay') {
                isWithoutPay = true;
            }
            // If original_leave_type exists and current type is 'without_pay' or empty, it was converted to without pay
            else if (originalLeaveType && (leaveType === 'without_pay' || !leaveType)) {
                isWithoutPay = true;
            }
            // Check if the current leave type is inherently without pay
            else if (leaveTypes[leaveType] && leaveTypes[leaveType].without_pay) {
                isWithoutPay = true;
            }
            // Check if the original leave type was inherently without pay
            else if (originalLeaveType && leaveTypes[originalLeaveType] && leaveTypes[originalLeaveType].without_pay) {
                isWithoutPay = true;
            }
            
            // Determine the base leave type to display
            let baseType = null;
            if (originalLeaveType && (leaveType === 'without_pay' || !leaveType)) {
                // Use original type if it was converted to without pay
                baseType = originalLeaveType;
            } else {
                // Use current type
                baseType = leaveType;
            }
            
            // Get the display name
            if (leaveTypes[baseType]) {
                const leaveTypeConfig = leaveTypes[baseType];
                
                if (isWithoutPay) {
                    // Show name with without pay indicator
                    if (leaveTypeConfig.name_with_note) {
                        return leaveTypeConfig.name_with_note;
                    } else {
                        return leaveTypeConfig.name + ' (Without Pay)';
                    }
                } else {
                    // Show regular name
                    return leaveTypeConfig.name;
                }
            } else {
                // Fallback for unknown types
                const displayName = baseType.charAt(0).toUpperCase() + baseType.slice(1).replace(/_/g, ' ');
                return isWithoutPay ? displayName + ' (Without Pay)' : displayName;
            }
        }
        
        // User dropdown toggle function
        function toggleUserDropdown() {
            const dropdown = document.getElementById('userDropdown');
            const notificationDropdown = document.getElementById('notificationDropdown');
            
            if (dropdown) {
                dropdown.classList.toggle('hidden');
                
                // Ensure dropdown is properly positioned and isolated
                if (!dropdown.classList.contains('hidden')) {
                    dropdown.style.position = 'absolute';
                    dropdown.style.zIndex = '1000';
                    dropdown.style.isolation = 'isolate';
                    
                    // Remove any misplaced elements that might have appeared
                    const misplacedInputs = dropdown.querySelectorAll('input');
                    misplacedInputs.forEach(input => {
                        input.remove();
                    });
                }
                
                // Close notification dropdown when opening user dropdown
                if (notificationDropdown) {
                    notificationDropdown.classList.add('hidden');
                }
            }
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const userDropdown = document.getElementById('userDropdown');
            const userButton = event.target.closest('[onclick="toggleUserDropdown()"]');
            
            if (userDropdown && !userDropdown.contains(event.target) && !userButton) {
                userDropdown.classList.add('hidden');
            }
        });

        // Select all functionality
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.request-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        // Bulk action functionality
        function bulkAction(action) {
            const selectedCheckboxes = document.querySelectorAll('.request-checkbox:checked');
            if (selectedCheckboxes.length === 0) {
                alert('Please select at least one leave request.');
                return;
            }

            if (confirm('Are you sure you want to ' + action + ' the selected leave requests?')) {
                document.getElementById('bulkAction').value = action;
                document.getElementById('bulkForm').submit();
            }
        }

        // Individual status update
        function updateRequestStatus(leaveId, status) {
            if (confirm('Are you sure you want to ' + status + ' this leave request?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="update_status" value="1">
                    <input type="hidden" name="leave_id" value="${leaveId}">
                    <input type="hidden" name="status" value="${status}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // View request details
        function viewRequestDetails(leaveId) {
            
            // Show loading state
            const modal = document.getElementById('requestDetailsModal');
            const content = document.getElementById('requestDetailsContent');
            
            if (!modal || !content) {
                console.error('Modal elements not found');
                alert('Error: Modal elements not found. Please refresh the page.');
                return;
            }
            
            // Show modal with loading state
            modal.classList.remove('hidden');
            content.innerHTML = `
                <div class="flex items-center justify-center py-12">
                    <div class="text-center">
                        <div class="w-16 h-16 bg-slate-700/50 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-spinner fa-spin text-primary text-2xl"></i>
                        </div>
                        <h4 class="text-lg font-semibold text-white mb-2">Loading Details</h4>
                        <p class="text-slate-400">Please wait while we fetch the leave request details...</p>
                    </div>
                </div>
            `;
            
            // Fetch leave request details with fallback
            const apiUrl = `../api/get_leave_request_details.php?id=${leaveId}`;
            
            fetch(apiUrl)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        displayLeaveRequestDetails(data.leave_request);
                    } else {
                        content.innerHTML = `
                            <div class="text-center py-12">
                                <div class="w-16 h-16 bg-red-500/20 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <i class="fas fa-exclamation-triangle text-red-400 text-2xl"></i>
                                </div>
                                <h4 class="text-lg font-semibold text-white mb-2">Error Loading Details</h4>
                                <p class="text-slate-400">${data.message || 'Failed to load leave request details.'}</p>
                                <button onclick="closeRequestModal()" class="mt-4 px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg transition-colors">
                                    Close
                                </button>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error fetching leave request details:', error);
                    content.innerHTML = `
                        <div class="text-center py-12">
                            <div class="w-16 h-16 bg-red-500/20 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-exclamation-triangle text-red-400 text-2xl"></i>
                            </div>
                            <h4 class="text-lg font-semibold text-white mb-2">Network Error</h4>
                            <p class="text-slate-400">Failed to connect to the server. Error: ${error.message}</p>
                            <p class="text-slate-400 text-sm mt-2">Please check the browser console for more details.</p>
                            <button onclick="closeRequestModal()" class="mt-4 px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg transition-colors">
                                Close
                            </button>
                        </div>
                    `;
                });
        }

        // Display leave request details in modal
        function displayLeaveRequestDetails(leaveRequest) {
            const content = document.getElementById('requestDetailsContent');
            
            // Format dates
            const startDate = new Date(leaveRequest.start_date).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            const endDate = (() => {
                // Use approved end date if available, otherwise original end date
                if (leaveRequest.status === 'approved' && leaveRequest.approved_days && leaveRequest.approved_days !== leaveRequest.days_requested) {
                    const startDate = new Date(leaveRequest.start_date);
                    const approvedEndDate = new Date(startDate);
                    approvedEndDate.setDate(startDate.getDate() + (leaveRequest.approved_days - 1));
                    return approvedEndDate.toLocaleDateString('en-US', {
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric'
                    });
                } else {
                    return new Date(leaveRequest.end_date).toLocaleDateString('en-US', {
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric'
                    });
                }
            })();
            const createdDate = new Date(leaveRequest.created_at).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            
            // Calculate days - use approved days if available
            const days = (() => {
                if (leaveRequest.status === 'approved' && leaveRequest.approved_days && leaveRequest.approved_days > 0) {
                    return leaveRequest.approved_days;
                } else {
                    const start = new Date(leaveRequest.start_date);
                    const end = new Date(leaveRequest.end_date);
                    return Math.ceil((end - start) / (1000 * 60 * 60 * 24)) + 1;
                }
            })();
            
            // Status colors and styling
            const getStatusBadge = (status) => {
                const colorMap = {
                    'approved': 'bg-green-500/20 text-green-400 border-green-500/30',
                    'rejected': 'bg-red-500/20 text-red-400 border-red-500/30',
                    'pending': 'bg-yellow-500/20 text-yellow-400 border-yellow-500/30'
                };
                const color = colorMap[status] || 'bg-slate-500/20 text-slate-400 border-slate-500/30';
                return `<span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ${color} border">${status.charAt(0).toUpperCase() + status.slice(1)}</span>`;
            };
            
            content.innerHTML = `
                <div class="space-y-6">
                    <!-- Employee Information -->
                    <div class="bg-slate-700/30 rounded-xl p-6 border border-slate-600/30">
                        <h4 class="text-lg font-semibold text-white mb-4 flex items-center">
                            <i class="fas fa-user text-primary mr-3"></i>Employee Information
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="text-sm font-medium text-slate-400">Name</label>
                                <p class="text-white font-semibold">${leaveRequest.employee_name}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-400">Position</label>
                                <p class="text-white">${leaveRequest.position || 'N/A'}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-400">Department</label>
                                <p class="text-white">${leaveRequest.department || 'N/A'}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-400">Email</label>
                                <p class="text-white">${leaveRequest.employee_email || 'N/A'}</p>
                            </div>
                            ${leaveRequest.employee_contact ? `
                            <div>
                                <label class="text-sm font-medium text-slate-400">Contact</label>
                                <p class="text-white">${leaveRequest.employee_contact}</p>
                            </div>
                            ` : ''}
                            <div>
                                <label class="text-sm font-medium text-slate-400">Request ID</label>
                                <p class="text-white font-mono">#${leaveRequest.id}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Leave Details -->
                    <div class="bg-slate-700/30 rounded-xl p-6 border border-slate-600/30">
                        <h4 class="text-lg font-semibold text-white mb-4 flex items-center">
                            <i class="fas fa-calendar-alt text-primary mr-3"></i>Leave Details
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                            <label class="text-sm font-medium text-slate-400">Leave Type</label>
                                            <p class="text-white">${leaveRequest.leave_type}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-400">Duration</label>
                                <p class="text-white">${days} day${days !== 1 ? 's' : ''}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-400">Start Date</label>
                                <p class="text-white">${startDate}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-400">End Date</label>
                                <p class="text-white">${endDate}</p>
                            </div>
                            <div class="md:col-span-2">
                                <label class="text-sm font-medium text-slate-400">Reason</label>
                                <p class="text-white bg-slate-800/50 rounded-lg p-3 mt-1">${leaveRequest.reason || 'No reason provided'}</p>
                            </div>
                        </div>
                    </div>

                    ${(leaveRequest.is_late == 1 || leaveRequest.is_late === '1' || leaveRequest.is_late === true) && leaveRequest.late_justification ? `
                    <!-- Late Application Details -->
                    <div class="bg-yellow-500/20 border border-yellow-500/30 rounded-xl p-6">
                        <h4 class="text-lg font-semibold text-yellow-400 mb-4 flex items-center">
                            <i class="fas fa-exclamation-triangle mr-3"></i>Late Application Details
                        </h4>
                        <div class="space-y-3">
                            <div>
                                <label class="text-sm font-medium text-yellow-300">Late Justification</label>
                                <p class="text-white bg-slate-800/50 rounded-lg p-3 mt-1">${leaveRequest.late_justification}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-yellow-300">Application Type</label>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-500/20 text-yellow-400 border border-yellow-500/30">
                                    Late Application
                                </span>
                            </div>
                        </div>
                    </div>
                    ` : ''}

                    ${leaveRequest.location_type ? `
                    <!-- Location Details -->
                    <div class="bg-slate-700/30 rounded-xl p-6 border border-slate-600/30">
                        <h4 class="text-lg font-semibold text-white mb-4 flex items-center">
                            <i class="fas fa-map-marker-alt text-primary mr-3"></i>Location Details
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="text-sm font-medium text-slate-400">Location Type</label>
                                <p class="text-white">${leaveRequest.location_type ? leaveRequest.location_type.charAt(0).toUpperCase() + leaveRequest.location_type.slice(1) : 'N/A'}</p>
                            </div>
                            ${leaveRequest.location_specify ? `
                            <div>
                                <label class="text-sm font-medium text-slate-400">Specific Location</label>
                                <p class="text-white">${leaveRequest.location_specify}</p>
                            </div>
                            ` : ''}
                        </div>
                    </div>
                    ` : ''}

                    ${leaveRequest.medical_condition || leaveRequest.medical_certificate_path ? `
                    <!-- Medical Details -->
                    <div class="bg-slate-700/30 rounded-xl p-6 border border-slate-600/30">
                        <h4 class="text-lg font-semibold text-white mb-4 flex items-center">
                            <i class="fas fa-medkit text-primary mr-3"></i>Medical Details
                        </h4>
                        <div class="space-y-3">
                            ${leaveRequest.medical_condition ? `
                            <div>
                                <label class="text-sm font-medium text-slate-400">Medical Condition</label>
                                <p class="text-white">${leaveRequest.medical_condition}</p>
                            </div>
                            ` : ''}
                            ${leaveRequest.illness_specify ? `
                            <div>
                                <label class="text-sm font-medium text-slate-400">Illness Specification</label>
                                <p class="text-white">${leaveRequest.illness_specify}</p>
                            </div>
                            ` : ''}
                            ${leaveRequest.medical_certificate_path ? `
                            <div>
                                <label class="text-sm font-medium text-slate-400">Medical Certificate</label>
                                <div class="flex items-center space-x-3 mt-2">
                                    <i class="fas fa-file-medical text-green-400"></i>
                                    <a href="../../api/view_medical_certificate.php?file=${encodeURIComponent(leaveRequest.medical_certificate_path)}" 
                                       target="_blank" 
                                       class="text-blue-400 hover:text-blue-300 underline">
                                        View Medical Certificate
                                    </a>
                                </div>
                            </div>
                            ` : ''}
                        </div>
                    </div>
                    ` : ''}

                    ${leaveRequest.special_women_condition ? `
                    <!-- Special Women Condition -->
                    <div class="bg-pink-500/20 border border-pink-500/30 rounded-xl p-6">
                        <h4 class="text-lg font-semibold text-pink-400 mb-4 flex items-center">
                            <i class="fas fa-female mr-3"></i>Special Women Condition
                        </h4>
                        <div>
                            <label class="text-sm font-medium text-pink-300">Condition</label>
                            <p class="text-white">${leaveRequest.special_women_condition}</p>
                        </div>
                    </div>
                    ` : ''}

                    ${leaveRequest.study_type ? `
                    <!-- Study Details -->
                    <div class="bg-blue-500/20 border border-blue-500/30 rounded-xl p-6">
                        <h4 class="text-lg font-semibold text-blue-400 mb-4 flex items-center">
                            <i class="fas fa-graduation-cap mr-3"></i>Study Details
                        </h4>
                        <div>
                            <label class="text-sm font-medium text-blue-300">Study Type</label>
                            <p class="text-white">${leaveRequest.study_type}</p>
                        </div>
                    </div>
                    ` : ''}

                    <!-- Approval Status -->
                    <div class="bg-slate-700/30 rounded-xl p-6 border border-slate-600/30">
                        <h4 class="text-lg font-semibold text-white mb-4 flex items-center">
                            <i class="fas fa-clipboard-check text-primary mr-3"></i>Approval Status
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="text-center">
                                <label class="text-sm font-medium text-slate-400 mb-2 block">Department Head</label>
                                <div class="mb-2">${getStatusBadge(leaveRequest.dept_head_approval || 'pending')}</div>
                                ${leaveRequest.dept_head_name ? `<p class="text-xs text-slate-400">by ${leaveRequest.dept_head_name}</p>` : ''}
                                ${leaveRequest.dept_head_approved_at ? `<p class="text-xs text-slate-400">${new Date(leaveRequest.dept_head_approved_at).toLocaleDateString()}</p>` : ''}
                                ${leaveRequest.dept_head_rejection_reason ? `<p class="text-xs text-red-400 mt-1">${leaveRequest.dept_head_rejection_reason}</p>` : ''}
                            </div>
                            <div class="text-center">
                                <label class="text-sm font-medium text-slate-400 mb-2 block">Director</label>
                                <div class="mb-2">${getStatusBadge(leaveRequest.director_approval || 'pending')}</div>
                                ${leaveRequest.director_name ? `<p class="text-xs text-slate-400">by ${leaveRequest.director_name}</p>` : ''}
                                ${leaveRequest.director_approved_at ? `<p class="text-xs text-slate-400">${new Date(leaveRequest.director_approved_at).toLocaleDateString()}</p>` : ''}
                                ${leaveRequest.director_rejection_reason ? `<p class="text-xs text-red-400 mt-1">${leaveRequest.director_rejection_reason}</p>` : ''}
                            </div>
                        </div>
                        <div class="mt-6 pt-4 border-t border-slate-600/30">
                            <div class="text-center">
                                <label class="text-sm font-medium text-slate-400 mb-2 block">Final Status</label>
                                <div class="mb-2">${getStatusBadge(leaveRequest.status || 'pending')}</div>
                                <p class="text-xs text-slate-400">Based on Department Head and Director approvals</p>
                            </div>
                        </div>
                    </div>

                    <!-- Request Information -->
                    <div class="bg-slate-700/30 rounded-xl p-6 border border-slate-600/30">
                        <h4 class="text-lg font-semibold text-white mb-4 flex items-center">
                            <i class="fas fa-info-circle text-primary mr-3"></i>Request Information
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="text-sm font-medium text-slate-400">Submitted On</label>
                                <p class="text-white">${createdDate}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-400">Final Status</label>
                                <p class="text-white">${leaveRequest.status ? leaveRequest.status.charAt(0).toUpperCase() + leaveRequest.status.slice(1) : 'Pending'}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex justify-end space-x-3">
                        <button onclick="closeRequestModal()" class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg transition-colors">
                            Close
                        </button>
                        ${(leaveRequest.dept_head_approval === 'approved' && leaveRequest.director_approval === 'approved') ? 
                            `<a href="print_leave_request.php?id=${leaveRequest.id}" target="_blank" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                                <i class="fas fa-print mr-2"></i>Print
                            </a>` : ''
                        }
                    </div>
                </div>
            `;
        }

        // Show status information modal
        function showStatusInfo(leaveId) {
            // Find the request data from the table
            const row = document.querySelector(`tr[data-request-id="${leaveId}"]`);
            if (!row) return;

            // Extract data from the row
            const deptStatus = row.querySelector('[data-dept-status]')?.textContent.trim() || 'Pending';
            const directorStatus = row.querySelector('[data-director-status]')?.textContent.trim() || 'Pending';
            const finalStatus = row.querySelector('[data-final-status]')?.textContent.trim() || 'Pending';

            // Create modal HTML
            const modalHtml = `
                <div class="modal fade" id="statusInfoModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="fas fa-info-circle me-2"></i>Approval Status Details
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="text-primary mb-3">
                                            <i class="fas fa-user-tie me-2"></i>Department Head
                                        </h6>
                                        <p><strong>Status:</strong> 
                                            <span class="badge bg-${deptStatus === 'Approved' ? 'success' : deptStatus === 'Rejected' ? 'danger' : 'warning'}">
                                                ${deptStatus}
                                            </span>
                                        </p>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="text-primary mb-3">
                                            <i class="fas fa-user-tie me-2"></i>Director
                                        </h6>
                                        <p><strong>Status:</strong> 
                                            <span class="badge bg-${directorStatus === 'Approved' ? 'success' : directorStatus === 'Rejected' ? 'danger' : 'warning'}">
                                                ${directorStatus}
                                            </span>
                                        </p>
                                    </div>
                                </div>
                                <hr>
                                <div class="row">
                                    <div class="col-md-12">
                                        <h6 class="text-success mb-3">
                                            <i class="fas fa-flag-checkered me-2"></i>Final Status
                                        </h6>
                                        <p><strong>Result:</strong> 
                                            <span class="badge bg-${finalStatus === 'Approved' ? 'success' : finalStatus === 'Rejected' ? 'danger' : 'warning'}">
                                                ${finalStatus}
                                            </span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Remove existing modal if any
            const existingModal = document.getElementById('statusInfoModal');
            if (existingModal) {
                existingModal.remove();
            }

            // Add modal to body
            document.body.insertAdjacentHTML('beforeend', modalHtml);

            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('statusInfoModal'));
            modal.show();
        }

        // Close request modal
        function closeRequestModal() {
            const modal = document.getElementById('requestDetailsModal');
            if (modal) {
                modal.classList.add('hidden');
            }
        }


        // Show status info modal
        function showStatusInfo(leaveId) {
            // This would typically fetch data via AJAX
            // For now, just show an alert
            alert('Status information for leave request #' + leaveId);
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

        // View More button handler - loads actual data
        async function handleViewMoreClick() {
            const loadMoreBtn = document.getElementById('loadMoreBtn');
            const tableBody = document.querySelector('tbody');
            
            if (!loadMoreBtn || !tableBody) {
                console.error('Missing elements:', { loadMoreBtn, tableBody });
                return;
            }
            
            console.log('View More clicked, starting load...');
            loadMoreBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Loading...';
            loadMoreBtn.disabled = true;
            
            try {
                // Get current offset (number of rows currently displayed)
                const currentRows = tableBody.querySelectorAll('tr').length;
                console.log('Current rows:', currentRows);
                
                // Build query parameters for filters
                const urlParams = new URLSearchParams(window.location.search);
                const status = urlParams.get('status') || '';
                const employee = urlParams.get('employee') || '';
                const leave_type = urlParams.get('leave_type') || '';
                
                // Call API to get more requests
                const apiUrl = `../api/get_more_requests.php?offset=${currentRows}&status=${status}&employee=${employee}&leave_type=${leave_type}`;
                console.log('API URL:', apiUrl);
                
                const response = await fetch(apiUrl);
                console.log('Response status:', response.status);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                console.log('Response data:', data);
                
                if (data.success && data.html) {
                    // Append new rows to the table
                    tableBody.insertAdjacentHTML('beforeend', data.html);
                    
                    // Show Show Less button
                    const showLessBtn = document.getElementById('showLessBtn');
                    if (showLessBtn) {
                        showLessBtn.classList.remove('hidden');
                    }
                    
                    // Update button text or hide if no more data
                    if (!data.hasMore || data.count < 15) {
                        loadMoreBtn.style.display = 'none';
                    } else {
                        loadMoreBtn.innerHTML = '<i class="fas fa-plus mr-2"></i>View More (more available)';
                        loadMoreBtn.disabled = false;
                    }
                    
                    console.log('Successfully loaded', data.count, 'more requests');
                } else {
                    console.error('API returned error:', data);
                    loadMoreBtn.innerHTML = '<i class="fas fa-plus mr-2"></i>View More (API Error)';
                    loadMoreBtn.disabled = false;
                }
            } catch (error) {
                console.error('Error loading more requests:', error);
                loadMoreBtn.innerHTML = '<i class="fas fa-plus mr-2"></i>View More (Error)';
                loadMoreBtn.disabled = false;
            }
        }

        // Show Less button handler
        function handleShowLessClick() {
            const tableBody = document.querySelector('tbody');
            const loadMoreBtn = document.getElementById('loadMoreBtn');
            const showLessBtn = document.getElementById('showLessBtn');
            
            if (!tableBody) return;
            
            // Get all rows
            const allRows = tableBody.querySelectorAll('tr');
            const initialLimit = 10; // Show only first 10 rows
            
            // Remove rows beyond the initial limit
            for (let i = allRows.length - 1; i >= initialLimit; i--) {
                if (allRows[i]) {
                    allRows[i].remove();
                }
            }
            
            // Show View More button and hide Show Less button
            if (loadMoreBtn) {
                loadMoreBtn.style.display = 'inline-flex';
                loadMoreBtn.innerHTML = '<i class="fas fa-plus mr-2"></i>View More (<?php echo $total_requests - $initial_limit; ?> more)';
                loadMoreBtn.disabled = false;
            }
            
            if (showLessBtn) {
                showLessBtn.classList.add('hidden');
            }
        }

        // View More and Show Less functionality
        document.addEventListener('DOMContentLoaded', function() {
            const loadMoreBtn = document.getElementById('loadMoreBtn');
            const showLessBtn = document.getElementById('showLessBtn');
            const tableBody = document.querySelector('tbody');
            
            if (loadMoreBtn && showLessBtn && tableBody) {
                let currentOffset = 10;
                let isLoading = false;
                let totalAdditionalRows = 0;
                
                // View More functionality
                loadMoreBtn.addEventListener('click', async function(event) {
                    event.preventDefault();
                    event.stopPropagation();
                    
                    if (isLoading) return;
                    
                    isLoading = true;
                    loadMoreBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Loading...';
                    loadMoreBtn.disabled = true;
                    
                    try {
                        // Build query parameters for filters
                        const urlParams = new URLSearchParams(window.location.search);
                        const status = urlParams.get('status') || '';
                        const employee = urlParams.get('employee') || '';
                        const leave_type = urlParams.get('leave_type') || '';
                        
                        const apiUrl = `../api/get_more_requests.php?offset=${currentOffset}&status=${status}&employee=${employee}&leave_type=${leave_type}`;
                        const response = await fetch(apiUrl);
                        const data = await response.json();
                        
                        if (data.success && data.html) {
                            // Append new rows to the existing table
                            tableBody.insertAdjacentHTML('beforeend', data.html);
                            
                            // Store the count of additional rows added
                            totalAdditionalRows += data.count;
                            
                            // Update offset for next request
                            currentOffset += data.count;
                            
                            // Show the Show Less button
                            showLessBtn.classList.remove('hidden');
                            
                            // Hide the View More button if no more requests
                            if (!data.hasMore || data.count < 15) {
                                loadMoreBtn.style.display = 'none';
                            } else {
                                // Update button text with remaining count
                                const remainingCount = data.hasMore ? 'more' : '0';
                                loadMoreBtn.innerHTML = `<i class="fas fa-plus mr-2"></i>View More (${remainingCount} more)`;
                                loadMoreBtn.disabled = false;
                            }
                        } else {
                            console.error('Failed to load more requests:', data.message);
                            loadMoreBtn.innerHTML = '<i class="fas fa-exclamation-triangle mr-2"></i>Error loading requests';
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        loadMoreBtn.innerHTML = '<i class="fas fa-exclamation-triangle mr-2"></i>Error loading requests';
                    }
                    
                    isLoading = false;
                });
                
                showLessBtn.addEventListener('click', function() {
                    // Remove all additional rows by removing the last N rows
                    const allRows = tableBody.querySelectorAll('tr');
                    const rowsToRemove = totalAdditionalRows;
                    
                    // Remove rows from the end
                    for (let i = allRows.length - 1; i >= allRows.length - rowsToRemove; i--) {
                        if (allRows[i]) {
                            allRows[i].remove();
                        }
                    }
                    
                    // Reset the counter
                    totalAdditionalRows = 0;
                    
                    // Reset offset
                    currentOffset = 10;
                    
                    // Hide Show Less button
                    showLessBtn.classList.add('hidden');
                    
                    // Show View More button again
                    loadMoreBtn.style.display = 'inline-flex';
                    loadMoreBtn.innerHTML = '<i class="fas fa-plus mr-2"></i>View More (<?php echo $total_requests - $initial_limit; ?> more)';
                    loadMoreBtn.disabled = false;
                });
            }
        });

    </script>
</body>
</html> 