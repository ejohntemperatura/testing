<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin or manager (department head)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin','manager','director'])) {
    header('Location: ../auth/index.php');
    exit();
}

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
                
                require_once '../includes/EmailService.php';
                $emailService = new EmailService();
                foreach ($requests as $request) {
                    $emailService->sendLeaveStatusNotification(
                        $request['employee_email'],
                        $request['employee_name'],
                        $action,
                        $request['start_date'],
                        $request['end_date'],
                        $request['leave_type'] ?? null
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

// Handle individual status updates
if (isset($_POST['update_status'])) {
    $leave_id = $_POST['leave_id'];
    $status = $_POST['status'];
    
    try {
        $stmt = $pdo->prepare("
            SELECT lr.*, e.name as employee_name, e.email as employee_email 
            FROM leave_requests lr 
            JOIN employees e ON lr.employee_id = e.id 
            WHERE lr.id = ?
        ");
        $stmt->execute([$leave_id]);
        $leave_request = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($leave_request) {
            // Check if both department head and director have approved
            $dept_approved = ($leave_request['dept_head_approval'] ?? 'pending') == 'approved';
            $director_approved = ($leave_request['director_approval'] ?? 'pending') == 'approved';
            $both_approved = $dept_approved && $director_approved;
            $any_rejected = ($leave_request['dept_head_approval'] ?? 'pending') == 'rejected' || 
                           ($leave_request['director_approval'] ?? 'pending') == 'rejected';
            
            if (!$both_approved || $any_rejected) {
                $_SESSION['error'] = "Cannot process: Department head and director must both approve before admin can act.";
                header('Location: leave_management.php');
                exit();
            }
            // If approving, verify and deduct tracked balances
            if ($status === 'approved') {
                $start = new DateTime($leave_request['start_date']);
                $end = new DateTime($leave_request['end_date']);
                $days = $start->diff($end)->days + 1;
                $leave_type = strtolower(trim($leave_request['leave_type']));
                $balance_field = $leave_type . '_leave_balance';
                
                // Check if balance field exists and has sufficient balance
                $stmt = $pdo->prepare("SELECT {$balance_field} FROM employees WHERE id = ?");
                $stmt->execute([$leave_request['employee_id']]);
                $current_balance = $stmt->fetchColumn();
                
                if ($current_balance < $days) {
                    $_SESSION['error'] = "Cannot approve: insufficient leave balance. Available: {$current_balance} days, Required: {$days} days";
                    header('Location: leave_management.php');
                    exit();
                }
                
                // Deduct leave balance
                $stmt = $pdo->prepare("UPDATE employees SET {$balance_field} = {$balance_field} - ? WHERE id = ?");
                $stmt->execute([$days, $leave_request['employee_id']]);
            }

            // Update leave request with admin approval and final status
            $stmt = $pdo->prepare("
                UPDATE leave_requests 
                SET admin_approval = ?, 
                    admin_approved_by = ?, 
                    admin_approved_at = NOW(),
                    status = ?
                WHERE id = ?
            ");
            $stmt->execute([$status, $_SESSION['user_id'], $status, $leave_id]);
            
            require_once '../includes/EmailService.php';
            $emailService = new EmailService();
            $emailService->sendLeaveStatusNotification(
                $leave_request['employee_email'],
                $leave_request['employee_name'],
                $status,
                $leave_request['start_date'],
                $leave_request['end_date'],
                $leave_request['leave_type'] ?? null
            );
            
            $_SESSION['success'] = "Leave request status updated successfully!";
        }
    } catch(PDOException $e) {
        $_SESSION['error'] = "Error updating leave request: " . $e->getMessage();
    }
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
try {
    $query = "
        SELECT lr.*, e.name as employee_name, e.email as employee_email, e.department,
               dept_approver.name as dept_head_name, director_approver.name as director_name, admin_approver.name as admin_name
        FROM leave_requests lr 
        JOIN employees e ON lr.employee_id = e.id 
        LEFT JOIN employees dept_approver ON lr.dept_head_approved_by = dept_approver.id
        LEFT JOIN employees director_approver ON lr.director_approved_by = director_approver.id
        LEFT JOIN employees admin_approver ON lr.admin_approved_by = admin_approver.id
        $where_clause
        ORDER BY lr.created_at DESC
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $leave_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error = "Error: " . $e->getMessage();
}

// Get unique leave types for filter
$stmt = $pdo->query("SELECT DISTINCT leave_type FROM leave_requests");
$leave_types = $stmt->fetchAll(PDO::FETCH_COLUMN);

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
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#0891b2',    // Cyan-600 - Main brand color
                        secondary: '#f97316',  // Orange-500 - Accent/action color
                        accent: '#06b6d4',     // Cyan-500 - Highlight color
                        background: '#0f172a', // Slate-900 - Main background
                        foreground: '#f8fafc', // Slate-50 - Primary text
                        muted: '#64748b'       // Slate-500 - Secondary text
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-900 text-white">
    <!-- Top Navigation Bar -->
    <nav class="bg-slate-800 border-b border-slate-700 fixed top-0 left-0 right-0 z-50 h-16">
        <div class="px-6 py-4 h-full">
            <div class="flex items-center justify-between h-full">
                <!-- Logo and Title -->
                <div class="flex items-center space-x-4">
                    <div class="flex items-center space-x-2">
                        <div class="w-8 h-8 bg-gradient-to-r from-primary to-accent rounded-lg flex items-center justify-center">
                            <i class="fas fa-calendar-check text-white text-sm"></i>
                        </div>
                        <span class="text-xl font-bold text-white">ELMS Admin</span>
                    </div>
                </div>
                
                <!-- User Menu -->
                <div class="flex items-center space-x-4">
                    <a href="../auth/logout.php" class="text-slate-300 hover:text-white transition-colors flex items-center space-x-2">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="flex">
        <!-- Left Sidebar -->
        <aside class="fixed left-0 top-16 h-screen w-64 bg-slate-800 border-r border-slate-700 overflow-y-auto z-40">
            <nav class="p-4 space-y-2">
                <?php 
                    $role = $_SESSION['role'];
                    $panelTitle = $role === 'director' ? 'Director Panel' : ($role === 'manager' ? 'Department Head' : 'Admin Panel');
                    $dashboardLink = $role === 'director' ? 'director_head_dashboard.php' : ($role === 'manager' ? 'department_head_dashboard.php' : 'admin_dashboard.php');
                ?>
                
                <!-- Other Navigation Items -->
                <a href="<?php echo $dashboardLink; ?>" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                    <i class="fas fa-tachometer-alt w-5"></i>
                    <span>Dashboard</span>
                </a>
                
                <a href="manage_user.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                    <i class="fas fa-users-cog w-5"></i>
                    <span>Manage User</span>
                </a>
                
                <!-- Active Navigation Item -->
                <a href="leave_management.php" class="flex items-center space-x-3 px-4 py-3 text-white bg-primary/20 rounded-lg border border-primary/30">
                    <i class="fas fa-calendar-check w-5"></i>
                    <span>Leave Management</span>
                    <span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full" id="pendingLeaveBadge" style="display: none;">0</span>
                </a>
                
                <a href="leave_alerts.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                    <i class="fas fa-bell w-5"></i>
                    <span>Leave Alerts</span>
                </a>
                
                <a href="view_chart.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                    <i class="fas fa-calendar w-5"></i>
                    <span>Leave Chart</span>
                </a>
                
                <a href="reports.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                    <i class="fas fa-file-alt w-5"></i>
                    <span>Reports</span>
                </a>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <main class="flex-1 ml-64 p-6">
            <div class="max-w-7xl mx-auto">

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="row mb-4">
                <div class="col-12">
                    <div style="padding: 1.5rem;">
                        <h2 class="mb-0">
                            <i class="fas fa-calendar-check me-2"></i>
                            Leave Management
                        </h2>
                    </div>
                </div>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php 
                    echo $_SESSION['success'];
                    unset($_SESSION['success']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php 
                    echo $_SESSION['error'];
                    unset($_SESSION['error']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-filter me-2"></i>Filters
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="GET" class="row g-3">
                                <div class="col-md-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" name="status" id="status">
                                        <option value="">All Status</option>
                                        <option value="pending" <?php echo (isset($_GET['status']) && $_GET['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                        <option value="approved" <?php echo (isset($_GET['status']) && $_GET['status'] == 'approved') ? 'selected' : ''; ?>>Approved</option>
                                        <option value="rejected" <?php echo (isset($_GET['status']) && $_GET['status'] == 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="employee" class="form-label">Employee</label>
                                    <input type="text" class="form-control" name="employee" id="employee" 
                                           value="<?php echo isset($_GET['employee']) ? htmlspecialchars($_GET['employee']) : ''; ?>" 
                                           placeholder="Search by employee name">
                                </div>
                                <div class="col-md-3">
                                    <label for="leave_type" class="form-label">Leave Type</label>
                                    <select class="form-select" name="leave_type" id="leave_type">
                                        <option value="">All Types</option>
                                        <?php foreach ($leave_types as $type): ?>
                                            <option value="<?php echo htmlspecialchars($type); ?>" 
                                                    <?php echo (isset($_GET['leave_type']) && $_GET['leave_type'] == $type) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($type); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">&nbsp;</label>
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-search me-2"></i>Filter
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Leave Requests Table -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-list me-2"></i>Leave Requests
                                </h5>
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-success btn-sm" onclick="bulkAction('approved')">
                                        <i class="fas fa-check me-1"></i>Approve Selected
                                    </button>
                                    <button type="button" class="btn btn-danger btn-sm" onclick="bulkAction('rejected')">
                                        <i class="fas fa-times me-1"></i>Reject Selected
                                    </button>
                                </div>
                            </div>
                            <div class="alert alert-info py-2 mb-0">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>All leave requests are visible here.</strong> You can only take action (approve/reject) on requests where both the Department Head and Director have already made their decisions.
                            </div>
                        </div>
                        <div class="card-body">
                            <form id="bulkForm" method="POST">
                                <input type="hidden" name="bulk_action" id="bulkAction">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>
                                                    <input type="checkbox" id="selectAll" class="form-check-input">
                                                </th>
                                                <th>Employee</th>
                                                <th>Department</th>
                                                <th>Leave Type</th>
                                                <th>Start Date</th>
                                                <th>End Date</th>
                                                <th>Days</th>
                                                <th>Reason</th>
                                                <th>Dept Head</th>
                                                <th>Director</th>
                                                <th>Admin</th>
                                                <th>Final Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($leave_requests)): ?>
                                                <tr>
                                                    <td colspan="12" class="text-center py-4">
                                                        <div class="text-muted">
                                                            <i class="fas fa-info-circle fa-2x mb-3"></i>
                                                            <h5>No Leave Requests Found</h5>
                                                            <p>No leave requests match your current filter criteria.<br>
                                                            Try adjusting your filters or check back later.</p>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                            <?php foreach ($leave_requests as $request): ?>
                                            <tr data-request-id="<?php echo $request['id']; ?>">
                                                <td>
                                                    <input type="checkbox" name="selected_requests[]" 
                                                           value="<?php echo $request['id']; ?>" 
                                                           class="form-check-input request-checkbox">
                                                </td>
                                                <td><?php echo htmlspecialchars($request['employee_name']); ?></td>
                                                <td><?php echo htmlspecialchars($request['department']); ?></td>
                                                <td><?php echo htmlspecialchars($request['leave_type']); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($request['start_date'])); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($request['end_date'])); ?></td>
                                                <td>
                                                    <?php 
                                                    $start = new DateTime($request['start_date']);
                                                    $end = new DateTime($request['end_date']);
                                                    $days = $start->diff($end)->days + 1;
                                                    echo $days;
                                                    ?>
                                                </td>
                                                <td>
                                                    <span class="text-truncate d-inline-block" style="max-width: 150px;" 
                                                          title="<?php echo htmlspecialchars($request['reason']); ?>">
                                                        <?php echo htmlspecialchars($request['reason']); ?>
                                                    </span>
                                                </td>
                                                <!-- Department Head Approval -->
                                                <td>
                                                    <?php 
                                                    $dept_status = $request['dept_head_approval'] ?? 'pending';
                                                    $dept_class = $dept_status == 'approved' ? 'success' : ($dept_status == 'rejected' ? 'danger' : 'warning');
                                                    ?>
                                                    <span class="badge bg-<?php echo $dept_class; ?>" data-dept-status="<?php echo ucfirst($dept_status); ?>">
                                                        <?php echo ucfirst($dept_status); ?>
                                                    </span>
                                                    <?php if ($dept_status != 'pending' && !empty($request['dept_head_name'])): ?>
                                                        <br><small class="text-muted" data-dept-approver="<?php echo htmlspecialchars($request['dept_head_name']); ?>">by <?php echo htmlspecialchars($request['dept_head_name']); ?></small>
                                                    <?php else: ?>
                                                        <br><small class="text-muted" data-dept-approver="Not decided">Not decided</small>
                                                    <?php endif; ?>
                                                </td>
                                                <!-- Director Approval -->
                                                <td>
                                                    <?php 
                                                    $director_status = $request['director_approval'] ?? 'pending';
                                                    $director_class = $director_status == 'approved' ? 'success' : ($director_status == 'rejected' ? 'danger' : 'warning');
                                                    ?>
                                                    <span class="badge bg-<?php echo $director_class; ?>" data-director-status="<?php echo ucfirst($director_status); ?>">
                                                        <?php echo ucfirst($director_status); ?>
                                                    </span>
                                                    <?php if ($director_status != 'pending' && !empty($request['director_name'])): ?>
                                                        <br><small class="text-muted" data-director-approver="<?php echo htmlspecialchars($request['director_name']); ?>">by <?php echo htmlspecialchars($request['director_name']); ?></small>
                                                    <?php else: ?>
                                                        <br><small class="text-muted" data-director-approver="Not decided">Not decided</small>
                                                    <?php endif; ?>
                                                </td>
                                                <!-- Admin Approval -->
                                                <td>
                                                    <?php 
                                                    $admin_status = $request['admin_approval'] ?? 'pending';
                                                    $admin_class = $admin_status == 'approved' ? 'success' : ($admin_status == 'rejected' ? 'danger' : 'warning');
                                                    ?>
                                                    <span class="badge bg-<?php echo $admin_class; ?>" data-admin-status="<?php echo ucfirst($admin_status); ?>">
                                                        <?php echo ucfirst($admin_status); ?>
                                                    </span>
                                                    <?php if ($admin_status != 'pending' && !empty($request['admin_name'])): ?>
                                                        <br><small class="text-muted" data-admin-approver="<?php echo htmlspecialchars($request['admin_name']); ?>">by <?php echo htmlspecialchars($request['admin_name']); ?></small>
                                                    <?php else: ?>
                                                        <br><small class="text-muted" data-admin-approver="Not decided">Not decided</small>
                                                    <?php endif; ?>
                                                </td>
                                                <!-- Final Status -->
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <span class="badge bg-<?php 
                                                            echo $request['status'] == 'approved' ? 'success' : 
                                                                ($request['status'] == 'pending' ? 'warning' : 'danger'); 
                                                        ?>" data-final-status="<?php echo ucfirst($request['status']); ?>">
                                                            <?php echo ucfirst($request['status']); ?>
                                                        </span>
                                                        <button type="button" class="btn btn-sm btn-outline-info" 
                                                                onclick="showStatusInfo(<?php echo $request['id']; ?>)"
                                                                title="View Status Details">
                                                            <i class="fas fa-info-circle"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                                <!-- Actions -->
                                                <td>
                                                    <?php 
                                                    // Check approval status at each level
                                                    $dept_status = $request['dept_head_approval'] ?? 'pending';
                                                    $director_status = $request['director_approval'] ?? 'pending';
                                                    $admin_status = $request['admin_approval'] ?? 'pending';
                                                    
                                                    $dept_approved = $dept_status == 'approved';
                                                    $director_approved = $director_status == 'approved';
                                                    $both_approved = $dept_approved && $director_approved;
                                                    $any_rejected = $dept_status == 'rejected' || $director_status == 'rejected';
                                                    $admin_can_act = $both_approved && !$any_rejected && $admin_status == 'pending';
                                                    $admin_already_acted = $admin_status != 'pending';
                                                    ?>
                                                    
                                                    <?php if ($admin_can_act): ?>
                                                        <div class="btn-group btn-group-sm" role="group">
                                                            <button type="button" class="btn btn-success" 
                                                                    onclick="updateRequestStatus(<?php echo $request['id']; ?>, 'approved')"
                                                                    title="Approve">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-danger" 
                                                                    onclick="updateRequestStatus(<?php echo $request['id']; ?>, 'rejected')"
                                                                    title="Reject">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        </div>
                                                    <?php elseif ($any_rejected): ?>
                                                        <div class="text-center">
                                                            <span class="badge bg-danger mb-1">Rejected</span>
                                                            <br><small class="text-muted">by previous approver</small>
                                                        </div>
                                                    <?php elseif ($admin_already_acted): ?>
                                                        <div class="text-center">
                                                            <span class="badge bg-<?php echo $admin_status == 'approved' ? 'success' : 'danger'; ?> mb-1">
                                                                <?php echo ucfirst($admin_status); ?>
                                                            </span>
                                                            <br><small class="text-muted">by admin</small>
                                                        </div>
                                                    <?php elseif (!$both_approved): ?>
                                                        <div class="text-center">
                                                            <span class="badge bg-warning mb-1">Waiting</span>
                                                            <br><small class="text-muted">
                                                                <?php if ($dept_status == 'pending'): ?>
                                                                    Dept Head
                                                                <?php elseif ($director_status == 'pending'): ?>
                                                                    Director
                                                                <?php endif; ?>
                                                            </small>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <button type="button" class="btn btn-primary btn-sm" 
                                                            onclick="viewRequestDetails(<?php echo $request['id']; ?>)"
                                                            title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Request Details Modal -->
    <div class="modal fade" id="requestDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Leave Request Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="requestDetailsContent">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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
            // For now, we'll show a simple alert. In a real implementation,
            // you would fetch the details via AJAX and populate the modal
            alert('Request details for ID: ' + leaveId + '\n\nThis would show detailed information about the leave request including employee details, dates, reason, and any attachments.');
        }

        // Show status information modal
        function showStatusInfo(leaveId) {
            // Find the request data from the table
            const row = document.querySelector(`tr[data-request-id="${leaveId}"]`);
            if (!row) return;

            // Extract data from the row
            const deptStatus = row.querySelector('[data-dept-status]')?.textContent.trim() || 'Pending';
            const deptApprover = row.querySelector('[data-dept-approver]')?.textContent.trim() || 'Not decided';
            const directorStatus = row.querySelector('[data-director-status]')?.textContent.trim() || 'Pending';
            const directorApprover = row.querySelector('[data-director-approver]')?.textContent.trim() || 'Not decided';
            const adminStatus = row.querySelector('[data-admin-status]')?.textContent.trim() || 'Pending';
            const adminApprover = row.querySelector('[data-admin-approver]')?.textContent.trim() || 'Not decided';
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
                                        <p><strong>Approved by:</strong> ${deptApprover}</p>
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
                                        <p><strong>Approved by:</strong> ${directorApprover}</p>
                                    </div>
                                </div>
                                <hr>
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="text-primary mb-3">
                                            <i class="fas fa-user-shield me-2"></i>Admin
                                        </h6>
                                        <p><strong>Status:</strong> 
                                            <span class="badge bg-${adminStatus === 'Approved' ? 'success' : adminStatus === 'Rejected' ? 'danger' : 'warning'}">
                                                ${adminStatus}
                                            </span>
                                        </p>
                                        <p><strong>Approved by:</strong> ${adminApprover}</p>
                                    </div>
                                    <div class="col-md-6">
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
    </script>
</body>
</html> 