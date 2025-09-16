<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/index.php');
    exit();
}

// Handle alert sending (keeping for fallback)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'send_alert') {
        $employee_id = $_POST['employee_id'];
        $alert_type = $_POST['alert_type'];
        $message = $_POST['message'];
        
        try {
            // Insert alert into database
            $stmt = $pdo->prepare("
                INSERT INTO leave_alerts (employee_id, alert_type, message, sent_by, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$employee_id, $alert_type, $message, $_SESSION['user_id']]);
            
            $_SESSION['success'] = "Leave maximization alert sent successfully!";
        } catch (Exception $e) {
            $_SESSION['error'] = "Error sending alert: " . $e->getMessage();
        }
    }
}

// Get employees with low leave usage
$currentYear = date('Y');
$stmt = $pdo->prepare("
    SELECT 
        e.id,
        e.name,
        e.email,
        e.department,
        e.annual_leave_balance,
        e.vacation_leave_balance,
        e.sick_leave_balance,
        COALESCE(annual_used.days_used, 0) as annual_used,
        COALESCE(vacation_used.days_used, 0) as vacation_used,
        COALESCE(sick_used.days_used, 0) as sick_used
    FROM employees e
    LEFT JOIN (
        SELECT employee_id, SUM(DATEDIFF(end_date, start_date) + 1) as days_used
        FROM leave_requests 
        WHERE leave_type = 'annual' AND YEAR(start_date) = ? AND status = 'approved'
        GROUP BY employee_id
    ) annual_used ON e.id = annual_used.employee_id
    LEFT JOIN (
        SELECT employee_id, SUM(DATEDIFF(end_date, start_date) + 1) as days_used
        FROM leave_requests 
        WHERE leave_type = 'vacation' AND YEAR(start_date) = ? AND status = 'approved'
        GROUP BY employee_id
    ) vacation_used ON e.id = vacation_used.employee_id
    LEFT JOIN (
        SELECT employee_id, SUM(DATEDIFF(end_date, start_date) + 1) as days_used
        FROM leave_requests 
        WHERE leave_type = 'sick' AND YEAR(start_date) = ? AND status = 'approved'
        GROUP BY employee_id
    ) sick_used ON e.id = sick_used.employee_id
    WHERE e.role = 'employee'
    ORDER BY e.name
");
$stmt->execute([$currentYear, $currentYear, $currentYear]);
$employees = $stmt->fetchAll();

// Calculate leave utilization for each employee
foreach ($employees as &$employee) {
    $employee['annual_remaining'] = $employee['annual_leave_balance'] - $employee['annual_used'];
    $employee['vacation_remaining'] = $employee['vacation_leave_balance'] - $employee['vacation_used'];
    $employee['sick_remaining'] = $employee['sick_leave_balance'] - $employee['sick_used'];
    
    // Calculate utilization percentage
    $employee['annual_utilization'] = $employee['annual_leave_balance'] > 0 ? 
        round(($employee['annual_used'] / $employee['annual_leave_balance']) * 100, 1) : 0;
    $employee['vacation_utilization'] = $employee['vacation_leave_balance'] > 0 ? 
        round(($employee['vacation_used'] / $employee['vacation_leave_balance']) * 100, 1) : 0;
    $employee['sick_utilization'] = $employee['sick_leave_balance'] > 0 ? 
        round(($employee['sick_used'] / $employee['sick_leave_balance']) * 100, 1) : 0;
    
    // Determine if employee needs alert (less than 50% utilization)
    $employee['needs_alert'] = $employee['annual_utilization'] < 50 || 
                              $employee['vacation_utilization'] < 50 || 
                              $employee['sick_utilization'] < 50;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ELMS - Leave Maximization Alerts</title>
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
    <style>
        .alert-card {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border: 1px solid #ffc107;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .utilization-bar {
            height: 8px;
            border-radius: 4px;
            background: #e9ecef;
            overflow: hidden;
        }
        .utilization-fill {
            height: 100%;
            transition: width 0.3s ease;
        }
        .low-utilization {
            background: #dc3545;
        }
        .medium-utilization {
            background: #ffc107;
        }
        .high-utilization {
            background: #28a745;
        }
        .employee-card {
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }
        .employee-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .needs-alert {
            border-left: 4px solid #ffc107;
            background: #fff9e6;
        }
    </style>
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
                            <i class="fas fa-bell text-white text-sm"></i>
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
                <!-- Other Navigation Items -->
                <a href="admin_dashboard.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                    <i class="fas fa-tachometer-alt w-5"></i>
                    <span>Dashboard</span>
                </a>
                
                <a href="manage_user.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                    <i class="fas fa-users w-5"></i>
                    <span>Manage Users</span>
                </a>
                
                <a href="leave_management.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                    <i class="fas fa-calendar-check w-5"></i>
                    <span>Leave Management</span>
                    <span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full" id="pendingLeaveBadge" style="display: none;">0</span>
                </a>
                
                <!-- Active Navigation Item -->
                <a href="leave_alerts.php" class="flex items-center space-x-3 px-4 py-3 text-white bg-primary/20 rounded-lg border border-primary/30">
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
                <!-- Page Header -->
                <div class="mb-8">
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 bg-gradient-to-r from-primary to-accent rounded-2xl flex items-center justify-center">
                            <i class="fas fa-bell text-2xl text-white"></i>
                        </div>
                        <div>
                            <h1 class="text-3xl font-bold text-white mb-2">Leave Maximization Alerts</h1>
                            <p class="text-slate-400">Monitor and send alerts to employees with low leave utilization</p>
                        </div>
                    </div>
                </div>

                <!-- Alert Info -->
                <div class="bg-yellow-500/20 border border-yellow-500/30 rounded-2xl p-6 mb-8">
                    <div class="flex items-center mb-4">
                        <i class="fas fa-info-circle text-yellow-400 text-2xl mr-4"></i>
                        <div>
                            <h3 class="text-xl font-semibold text-white mb-2">Leave Maximization System</h3>
                            <p class="text-slate-300">This system helps identify employees who have not fully utilized their leave credits for the current year. Employees with less than 50% utilization are flagged for alerts.</p>
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

            <!-- Employee List -->
            <div class="row">
                <?php foreach ($employees as $employee): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="employee-card <?php echo $employee['needs_alert'] ? 'needs-alert' : ''; ?>">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h6 class="mb-1"><?php echo htmlspecialchars($employee['name']); ?></h6>
                                    <small class="text-muted"><?php echo htmlspecialchars($employee['department']); ?></small>
                                </div>
                                <?php if ($employee['needs_alert']): ?>
                                    <span class="badge bg-warning">
                                        <i class="fas fa-exclamation-triangle me-1"></i>Needs Alert
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- Annual Leave -->
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <small class="fw-bold">Annual Leave</small>
                                    <small><?php echo $employee['annual_utilization']; ?>% used</small>
                                </div>
                                <div class="utilization-bar">
                                    <div class="utilization-fill <?php 
                                        echo $employee['annual_utilization'] < 30 ? 'low-utilization' : 
                                            ($employee['annual_utilization'] < 70 ? 'medium-utilization' : 'high-utilization'); 
                                    ?>" style="width: <?php echo $employee['annual_utilization']; ?>%"></div>
                                </div>
                                <small class="text-muted">
                                    <?php echo $employee['annual_used']; ?>/<?php echo $employee['annual_leave_balance']; ?> days
                                    (<?php echo $employee['annual_remaining']; ?> remaining)
                                </small>
                            </div>

                            <!-- Vacation Leave -->
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <small class="fw-bold">Vacation Leave</small>
                                    <small><?php echo $employee['vacation_utilization']; ?>% used</small>
                                </div>
                                <div class="utilization-bar">
                                    <div class="utilization-fill <?php 
                                        echo $employee['vacation_utilization'] < 30 ? 'low-utilization' : 
                                            ($employee['vacation_utilization'] < 70 ? 'medium-utilization' : 'high-utilization'); 
                                    ?>" style="width: <?php echo $employee['vacation_utilization']; ?>%"></div>
                                </div>
                                <small class="text-muted">
                                    <?php echo $employee['vacation_used']; ?>/<?php echo $employee['vacation_leave_balance']; ?> days
                                    (<?php echo $employee['vacation_remaining']; ?> remaining)
                                </small>
                            </div>

                            <!-- Sick Leave -->
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <small class="fw-bold">Sick Leave</small>
                                    <small><?php echo $employee['sick_utilization']; ?>% used</small>
                                </div>
                                <div class="utilization-bar">
                                    <div class="utilization-fill <?php 
                                        echo $employee['sick_utilization'] < 30 ? 'low-utilization' : 
                                            ($employee['sick_utilization'] < 70 ? 'medium-utilization' : 'high-utilization'); 
                                    ?>" style="width: <?php echo $employee['sick_utilization']; ?>%"></div>
                                </div>
                                <small class="text-muted">
                                    <?php echo $employee['sick_used']; ?>/<?php echo $employee['sick_leave_balance']; ?> days
                                    (<?php echo $employee['sick_remaining']; ?> remaining)
                                </small>
                            </div>

                            <!-- Action Button -->
                            <button class="btn btn-warning btn-sm w-100" data-bs-toggle="modal" data-bs-target="#alertModal" 
                                    data-employee-id="<?php echo $employee['id']; ?>" 
                                    data-employee-name="<?php echo htmlspecialchars($employee['name']); ?>">
                                <i class="fas fa-bell me-2"></i>Send Alert
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Alert Modal -->
    <div class="modal fade" id="alertModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-bell me-2"></i>Send Leave Maximization Alert
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="send_alert">
                        <input type="hidden" name="employee_id" id="modal_employee_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Employee</label>
                            <input type="text" class="form-control" id="modal_employee_name" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label for="alert_type" class="form-label">Alert Type</label>
                            <select class="form-select" name="alert_type" id="alert_type" required>
                                <option value="">Select Alert Type</option>
                                <option value="low_utilization">Low Leave Utilization</option>
                                <option value="year_end_reminder">Year-End Reminder</option>
                                <option value="custom">Custom Message</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="message" class="form-label">Message</label>
                            <textarea class="form-control" name="message" id="message" rows="4" required 
                                placeholder="Enter your message to the employee..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-paper-plane me-2"></i>Send Alert
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle modal data
        document.getElementById('alertModal').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const employeeId = button.getAttribute('data-employee-id');
            const employeeName = button.getAttribute('data-employee-name');
            
            document.getElementById('modal_employee_id').value = employeeId;
            document.getElementById('modal_employee_name').value = employeeName;
        });

        // Auto-fill message based on alert type
        document.getElementById('alert_type').addEventListener('change', function() {
            const messageField = document.getElementById('message');
            const alertType = this.value;
            
            let defaultMessage = '';
            switch(alertType) {
                case 'low_utilization':
                    defaultMessage = 'Dear Employee,\n\nWe noticed that you have not fully utilized your leave credits for this year. Please consider taking some time off to rest and recharge. Your well-being is important to us.\n\nBest regards,\nHR Department';
                    break;
                case 'year_end_reminder':
                    defaultMessage = 'Dear Employee,\n\nAs we approach the end of the year, we wanted to remind you that unused leave credits may not carry over to next year. Please plan your remaining leave days accordingly.\n\nBest regards,\nHR Department';
                    break;
                case 'custom':
                    defaultMessage = '';
                    break;
            }
            
            messageField.value = defaultMessage;
        });

        // Real-time alert sending with AJAX
        document.querySelector('form[method="POST"]').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const alertData = {
                employee_id: formData.get('employee_id'),
                alert_type: formData.get('alert_type'),
                message: formData.get('message')
            };
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Sending...';
            submitBtn.disabled = true;
            
            // Send AJAX request
            fetch('../api/send_alert.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(alertData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    showAlert('success', `Alert sent successfully to ${data.employee_name}!`);
                    
                    // Close modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('alertModal'));
                    modal.hide();
                    
                    // Reset form
                    this.reset();
                    
                    // Update the employee card to show alert was sent
                    updateEmployeeCard(alertData.employee_id);
                    
                } else {
                    showAlert('danger', data.error || 'Error sending alert');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('danger', 'Network error. Please try again.');
            })
            .finally(() => {
                // Reset button state
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });

        // Function to show alerts
        function showAlert(type, message) {
            // Remove existing alerts
            const existingAlerts = document.querySelectorAll('.alert');
            existingAlerts.forEach(alert => alert.remove());
            
            // Create new alert
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            // Insert at top of main content
            const mainContent = document.querySelector('.main-content .container-fluid');
            mainContent.insertBefore(alertDiv, mainContent.firstChild);
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }

        // Function to update employee card after sending alert
        function updateEmployeeCard(employeeId) {
            const employeeCard = document.querySelector(`[data-employee-id="${employeeId}"]`).closest('.employee-card');
            if (employeeCard) {
                // Add a visual indicator that alert was sent
                const button = employeeCard.querySelector('button');
                button.innerHTML = '<i class="fas fa-check me-2"></i>Alert Sent';
                button.classList.remove('btn-warning');
                button.classList.add('btn-success');
                button.disabled = true;
                
                // Reset after 3 seconds
                setTimeout(() => {
                    button.innerHTML = '<i class="fas fa-bell me-2"></i>Send Alert';
                    button.classList.remove('btn-success');
                    button.classList.add('btn-warning');
                    button.disabled = false;
                }, 3000);
            }
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
            </div>
        </main>
    </div>
</body>
</html>
