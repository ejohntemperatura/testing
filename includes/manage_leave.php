<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

// Function to send email notification
function sendLeaveStatusEmail($employee_email, $employee_name, $status, $start_date, $end_date) {
    // Validate email address
    if (!filter_var($employee_email, FILTER_VALIDATE_EMAIL)) {
        error_log("Invalid email address: " . $employee_email);
        return false;
    }

    // Email content
    $to = $employee_email;
    $subject = "Leave Request Update - ELMS";
    
    // Create HTML message
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
    
    // Set headers for HTML email
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: ELMS <" . $employee_email . ">" . "\r\n";
    
    try {
        // Send email using the employee's email address
        $mail_sent = mail($to, $subject, $message, $headers);
        
        if ($mail_sent) {
            error_log("Email sent successfully to: " . $employee_email);
            return true;
        } else {
            error_log("Failed to send email to: " . $employee_email);
            return false;
        }
    } catch (Exception $e) {
        error_log("Error sending email: " . $e->getMessage());
        return false;
    }
}

// Handle leave request status update
if (isset($_POST['update_status'])) {
    $leave_id = $_POST['leave_id'];
    $status = $_POST['status'];
    
    try {
        // First get the leave request details including employee email
        $stmt = $pdo->prepare("
            SELECT lr.*, e.name as employee_name, e.email as employee_email 
            FROM leave_requests lr 
            JOIN employees e ON lr.employee_id = e.id 
            WHERE lr.id = ?
        ");
        $stmt->execute([$leave_id]);
        $leave_request = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($leave_request) {
            // Update the status
            $stmt = $pdo->prepare("UPDATE leave_requests SET status = ? WHERE id = ?");
            $stmt->execute([$status, $leave_id]);
            
            // Send email notification using the employee's own email
            $email_sent = sendLeaveStatusEmail(
                $leave_request['employee_email'],
                $leave_request['employee_name'],
                $status,
                $leave_request['start_date'],
                $leave_request['end_date']
            );
            
            if ($email_sent) {
                $_SESSION['success'] = "Leave request status updated and notification sent to " . $leave_request['employee_email'];
            } else {
                $_SESSION['warning'] = "Leave request status updated but email notification failed. Please check the email address: " . $leave_request['employee_email'];
            }
        }
    } catch(PDOException $e) {
        $_SESSION['error'] = "Error updating leave request: " . $e->getMessage();
    }
    header('Location: manage_leave.php');
    exit();
}

// Fetch all leave requests with employee names
try {
    $stmt = $pdo->query("
        SELECT lr.*, e.name as employee_name, e.email as employee_email 
        FROM leave_requests lr 
        JOIN employees e ON lr.employee_id = e.id 
        ORDER BY lr.created_at DESC
    ");
    $leave_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error = "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Leave - ELMS</title>
    <link href="../assets/libs/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/libs/fontawesome/css/all.min.css" rel="stylesheet">
    <!-- <style>
        .sidebar {
            min-height: 100vh;
            background: #343a40;
            color: white;
        }
        .sidebar a {
            color: white;
            text-decoration: none;
            padding: 10px 15px;
            display: block;
        }
        .sidebar a:hover {
            background: #495057;
        }
        .main-content {
            padding: 20px;
        }
        .status-badge {
            font-size: 0.9em;
            padding: 5px 10px;
        }
    </style> -->
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar p-0">
                <div class="p-3">
                    <h4>ELMS Admin</h4>
                </div>
                <nav>
                    <a href="admin_dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                    <a href="manage_users.php"><i class="fas fa-users"></i> Manage Users</a>
                    <a href="manage_courses.php"><i class="fas fa-book"></i> Manage Courses</a>
                    <a href="manage_leave.php" class="active"><i class="fas fa-calendar"></i> Manage Leave</a>
                    <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                    <a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <h2 class="mb-4">Manage Leave Requests</h2>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success">
                        <?php 
                        echo $_SESSION['success'];
                        unset($_SESSION['success']);
                        ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger">
                        <?php 
                        echo $_SESSION['error'];
                        unset($_SESSION['error']);
                        ?>
                    </div>
                <?php endif; ?>

                <div style="padding: 1.5rem;">
                    <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Employee</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Reason</th>
                                        <th>Status</th>
                                        <th>Created At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($leave_requests as $request): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($request['id']); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($request['employee_name']); ?><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($request['employee_email']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($request['start_date']); ?></td>
                                        <td><?php echo htmlspecialchars($request['end_date']); ?></td>
                                        <td><?php echo htmlspecialchars($request['reason']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $request['status'] === 'approved' ? 'success' : 
                                                    ($request['status'] === 'rejected' ? 'danger' : 'warning');
                                            ?> status-badge">
                                                <?php echo htmlspecialchars($request['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($request['created_at']); ?></td>
                                        <td>
                                            <?php if ($request['status'] === 'pending'): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="leave_id" value="<?php echo $request['id']; ?>">
                                                <input type="hidden" name="status" value="approved">
                                                <button type="submit" name="update_status" class="btn btn-sm btn-success">
                                                    <i class="fas fa-check"></i> Approve
                                                </button>
                                            </form>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="leave_id" value="<?php echo $request['id']; ?>">
                                                <input type="hidden" name="status" value="rejected">
                                                <button type="submit" name="update_status" class="btn btn-sm btn-danger">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        function showNotification(message, type) {
            const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
            const notification = document.createElement('div');
            notification.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
            notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            notification.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(notification);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 5000);
        }

        // Add search functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Add search input to the page
            const tableHeader = document.querySelector('.card-body');
            const searchDiv = document.createElement('div');
            searchDiv.className = 'mb-3';
            searchDiv.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <input type="text" class="form-control" id="searchInput" placeholder="Search leave requests...">
                    </div>
                    <div class="col-md-6 text-end">
                        <button class="btn btn-primary" onclick="exportLeaveRequests()">
                            <i class="fas fa-download me-2"></i>Export
                        </button>
                    </div>
                </div>
            `;
            tableHeader.insertBefore(searchDiv, tableHeader.firstChild);

            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.addEventListener('keyup', function() {
                    const searchTerm = this.value.toLowerCase();
                    const tableRows = document.querySelectorAll('tbody tr');
                    
                    tableRows.forEach(row => {
                        const text = row.textContent.toLowerCase();
                        row.style.display = text.includes(searchTerm) ? '' : 'none';
                    });
                });
            }

            // Add hover effects to table rows
            const tableRows = document.querySelectorAll('tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = '#f8f9fa';
                });
                row.addEventListener('mouseleave', function() {
                    this.style.backgroundColor = '';
                });
            });
        });

        function exportLeaveRequests() {
            // Create a CSV export of the leave requests
            const table = document.querySelector('table');
            const rows = Array.from(table.querySelectorAll('tbody tr'));
            let csv = 'Employee,Start Date,End Date,Reason,Status,Created At\n';
            
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                const rowData = [];
                cells.forEach((cell, index) => {
                    if (index !== 0 && index !== 7) { // Skip ID and Actions columns
                        rowData.push('"' + cell.textContent.trim().replace(/"/g, '""') + '"');
                    }
                });
                csv += rowData.join(',') + '\n';
            });
            
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'leave_requests_' + new Date().toISOString().split('T')[0] + '.csv';
            a.click();
            window.URL.revokeObjectURL(url);
        }
    </script>
</body>
</html> 