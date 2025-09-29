<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Fetch user's leave requests
$stmt = $pdo->prepare("SELECT * FROM leave_requests WHERE employee_id = ? ORDER BY created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$leave_requests = $stmt->fetchAll();

// Fetch user's leave balance
$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$employee = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ELMS - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">ELMS</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <!-- Welcome Section -->
        <div style="margin-bottom: 1.5rem;">
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.25rem; padding: 1.5rem;">
                <div style="width: 40px; height: 40px; background: #f8f9fa; border-radius: 10px; display: flex; align-items: center; justify-content: center; border: 1px solid #e9ecef;">
                    <i class="fas fa-sun" style="font-size: 1.25rem; color: #6c757d;"></i>
                </div>
                <div>
                    <h2 style="margin: 0; font-size: 1.5rem; font-weight: 600; color: #212529;">
                        Good <?php echo date('H') < 12 ? 'Morning' : (date('H') < 17 ? 'Afternoon' : 'Evening'); ?>, <?php echo htmlspecialchars($employee['name']); ?>!
                    </h2>
                    <p style="margin: 0; font-size: 0.95rem; color: #6c757d;">
                        <i class="fas fa-calendar-alt" style="margin-right: 0.5rem;"></i>
                        Today is <?php echo date('l, F j, Y'); ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Statistics and Actions Row -->
        <div class="row mb-4">
            <div class="col-md-4 mb-4">
                <div class="stat-card">
                    <div class="stat-icon" style="background: var(--primary);">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <h6 class="card-title">Leave Balance</h6>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted">Annual Leave</span>
                            <span class="fw-bold"><?php echo $employee['annual_leave_balance']; ?> days</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted">Sick Leave</span>
                            <span class="fw-bold"><?php echo $employee['sick_leave_balance']; ?> days</span>
                        </div>
                    </div>
                    <button class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#leaveRequestModal">
                        <i class="fas fa-plus me-2"></i>Request Leave
                    </button>
                </div>
            </div>
            
            <div class="col-md-8 mb-4">
                <div class="leave-table">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="fas fa-list-alt"></i>
                            Recent Leave Requests
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Status</th>
                                        <th>Reason</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($leave_requests as $request): ?>
                                    <tr>
                                        <td>
                                            <span class="badge badge-primary"><?php echo ucfirst($request['leave_type']); ?></span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($request['start_date'])); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($request['end_date'])); ?></td>
                                        <td>
                                            <span class="status-badge bg-<?php 
                                                echo $request['status'] == 'approved' ? 'success' : 
                                                    ($request['status'] == 'pending' ? 'warning' : 'danger'); 
                                            ?>">
                                                <?php echo ucfirst($request['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="text-truncate d-inline-block" style="max-width: 200px;" title="<?php echo htmlspecialchars($request['reason']); ?>">
                                                <?php echo htmlspecialchars($request['reason']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($leave_requests)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4">
                                            <i class="fas fa-inbox fa-2x text-muted mb-2"></i>
                                            <p class="text-muted mb-0">No recent leave requests.</p>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Leave Request Modal -->
    <div class="modal fade" id="leaveRequestModal" tabindex="-1" aria-labelledby="leaveRequestModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="leaveRequestModalLabel">
                        <i class="fas fa-calendar-plus me-2"></i>Request Leave
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="leaveRequestForm" action="submit_leave.php" method="POST">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="leaveType" class="form-label">Leave Type</label>
                                <select class="form-select" id="leaveType" name="leave_type" required>
                                    <option value="">Select Leave Type</option>
                                    <option value="annual">Annual Leave</option>
                                    <option value="vacation">Vacation Leave</option>
                                    <option value="sick">Sick Leave</option>
                                    <option value="maternity">Maternity Leave</option>
                                    <option value="paternity">Paternity Leave</option>
                                    <option value="bereavement">Bereavement Leave</option>
                                    <option value="study">Study Leave</option>
                                    <option value="unpaid">Unpaid Leave</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="startDate" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="startDate" name="start_date" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="endDate" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="endDate" name="end_date" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="totalDays" class="form-label">Total Days</label>
                                <input type="text" class="form-control" id="totalDays" readonly placeholder="Auto-calculated">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="reason" class="form-label">Reason for Leave</label>
                            <textarea class="form-control" id="reason" name="reason" rows="4" placeholder="Please provide a detailed reason for your leave request..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-2"></i>Submit Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add date validation and total days calculation
        document.addEventListener('DOMContentLoaded', function() {
            const startDateInput = document.getElementById('startDate');
            const endDateInput = document.getElementById('endDate');
            const totalDaysInput = document.getElementById('totalDays');
            
            function calculateTotalDays() {
                const startDate = new Date(startDateInput.value);
                const endDate = new Date(endDateInput.value);
                
                if (startDate && endDate && endDate >= startDate) {
                    const timeDiff = endDate.getTime() - startDate.getTime();
                    const daysDiff = Math.ceil(timeDiff / (1000 * 3600 * 24)) + 1; // +1 to include both start and end dates
                    totalDaysInput.value = daysDiff + ' day' + (daysDiff !== 1 ? 's' : '');
                } else {
                    totalDaysInput.value = '';
                }
            }
            
            startDateInput.addEventListener('change', calculateTotalDays);
            endDateInput.addEventListener('change', calculateTotalDays);
            
            // Form validation
            document.getElementById('leaveRequestForm').addEventListener('submit', function(e) {
                const startDate = new Date(startDateInput.value);
                const endDate = new Date(endDateInput.value);
                
                if (endDate < startDate) {
                    e.preventDefault();
                    alert('End date cannot be before start date');
                    return;
                }
                
                if (startDate < new Date()) {
                    e.preventDefault();
                    alert('Start date cannot be in the past');
                    return;
                }
            });
        });
    </script>
</body>
</html> 