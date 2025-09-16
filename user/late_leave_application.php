<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/index.php');
    exit();
}

// Get user information
$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$employee = $stmt->fetch();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $leave_type = $_POST['leave_type'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $reason = $_POST['reason'];
    $late_justification = $_POST['late_justification'];
    $is_late = 1; // Mark as late application
    
    // Calculate number of days
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $interval = $start->diff($end);
    $days = $interval->days + 1;
    
    // Check if it's actually a late application (start date is in the past)
    $today = new DateTime();
    $is_actually_late = $start < $today;
    
    try {
        // Insert late leave request
        $stmt = $pdo->prepare("
            INSERT INTO leave_requests 
            (employee_id, leave_type, start_date, end_date, reason, late_justification, is_late, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([$_SESSION['user_id'], $leave_type, $start_date, $end_date, $reason, $late_justification, $is_late]);
        
        $_SESSION['success'] = "Late leave application submitted successfully. Your justification will be reviewed by your supervisor.";
        header('Location: leave_history.php');
        exit();
    } catch (Exception $e) {
        $_SESSION['error'] = "Error submitting late leave application: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ELMS - Late Leave Application</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .late-application-warning {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border: 1px solid #ffc107;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .justification-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }
        .form-label.required::after {
            content: " *";
            color: #dc3545;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h4><i class="fas fa-user me-2"></i>Employee Panel</h4>
        </div>
        <div class="sidebar-menu">
            <a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="leave_history.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'leave_history.php' ? 'active' : ''; ?>">
                <i class="fas fa-history"></i>
                <span>Leave History</span>
            </a>
            <a href="profile.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>
            <a href="leave_credits.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'leave_credits.php' ? 'active' : ''; ?>">
                <i class="fas fa-calculator"></i>
                <span>Leave Credits</span>
            </a>
            <a href="view_chart.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'view_chart.php' ? 'active' : ''; ?>">
                <i class="fas fa-calendar"></i>
                <span>Calendar View</span>
            </a>
            <a href="../auth/logout.php">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <!-- Header -->
            <div style="margin-bottom: 1.5rem;">
                <div style="padding: 1.5rem;">
                    <h2 class="mb-0">
                        <i class="fas fa-exclamation-triangle me-2 text-warning"></i>
                        Late Leave Application
                    </h2>
                </div>
            </div>

            <!-- Warning Section -->
            <div class="late-application-warning">
                <div class="d-flex align-items-center mb-3">
                    <i class="fas fa-exclamation-triangle text-warning me-3" style="font-size: 2rem;"></i>
                    <div>
                        <h4 class="mb-1 text-warning">Late Application Notice</h4>
                        <p class="mb-0">You are submitting a leave application for dates that have already passed or are very close to the start date. Please provide a valid justification for the late submission.</p>
                    </div>
                </div>
                <div class="alert alert-info mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Note:</strong> Late applications require additional approval and may be subject to different processing times. Your justification will be carefully reviewed by your supervisor.
                </div>
            </div>

            <!-- Application Form -->
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="leave_type" class="form-label required">Leave Type</label>
                                <select class="form-select" id="leave_type" name="leave_type" required>
                                    <option value="">Select Leave Type</option>
                                    <option value="annual">Annual Leave</option>
                                    <option value="vacation">Vacation Leave</option>
                                    <option value="sick">Sick Leave</option>
                                    <option value="maternity">Maternity Leave</option>
                                    <option value="paternity">Paternity Leave</option>
                                    <option value="bereavement">Bereavement Leave</option>
                                    <option value="study">Study Leave</option>
                                    <option value="unpaid">Unpaid Leave</option>
                                    <option value="emergency">Emergency Leave</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="start_date" class="form-label required">Start Date</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="end_date" class="form-label required">End Date</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Duration</label>
                                <input type="text" class="form-control" id="duration" readonly placeholder="Will be calculated automatically">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="reason" class="form-label required">Reason for Leave</label>
                            <textarea class="form-control" id="reason" name="reason" rows="3" required placeholder="Please provide a detailed reason for your leave request"></textarea>
                        </div>

                        <!-- Late Justification Section -->
                        <div class="justification-section">
                            <h5 class="text-primary mb-3">
                                <i class="fas fa-file-alt me-2"></i>
                                Late Application Justification
                            </h5>
                            <div class="mb-3">
                                <label for="late_justification" class="form-label required">Justification for Late Submission</label>
                                <textarea class="form-control" id="late_justification" name="late_justification" rows="4" required 
                                    placeholder="Please explain why this leave application is being submitted late. Include any circumstances that prevented you from submitting it on time (e.g., medical emergency, family crisis, system issues, etc.)"></textarea>
                                <div class="form-text">
                                    <i class="fas fa-lightbulb me-1"></i>
                                    <strong>Tips for a good justification:</strong>
                                    <ul class="mb-0 mt-2">
                                        <li>Be specific about the circumstances</li>
                                        <li>Include dates and times if relevant</li>
                                        <li>Mention any supporting documentation if available</li>
                                        <li>Explain how this won't happen again in the future</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="leave_history.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i>
                                Back to Leave History
                            </a>
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-paper-plane me-2"></i>
                                Submit Late Application
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Calculate duration when dates change
        function calculateDuration() {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            
            if (startDate && endDate) {
                const start = new Date(startDate);
                const end = new Date(endDate);
                const diffTime = Math.abs(end - start);
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
                document.getElementById('duration').value = diffDays + ' day(s)';
            }
        }

        // Add event listeners
        document.getElementById('start_date').addEventListener('change', calculateDuration);
        document.getElementById('end_date').addEventListener('change', calculateDuration);

        // Validate that end date is not before start date
        document.getElementById('end_date').addEventListener('change', function() {
            const startDate = document.getElementById('start_date').value;
            const endDate = this.value;
            
            if (startDate && endDate && new Date(endDate) < new Date(startDate)) {
                alert('End date cannot be before start date.');
                this.value = '';
                document.getElementById('duration').value = '';
            }
        });

        // Check if it's actually a late application
        document.getElementById('start_date').addEventListener('change', function() {
            const startDate = new Date(this.value);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            if (startDate < today) {
                // Show additional warning for past dates
                const warningDiv = document.createElement('div');
                warningDiv.className = 'alert alert-danger mt-2';
                warningDiv.innerHTML = '<i class="fas fa-exclamation-circle me-2"></i><strong>Warning:</strong> You are applying for leave that has already started or passed. This requires strong justification.';
                
                // Remove any existing warning
                const existingWarning = document.querySelector('.alert-danger');
                if (existingWarning) {
                    existingWarning.remove();
                }
                
                this.parentNode.appendChild(warningDiv);
            }
        });
    </script>
</body>
</html>
