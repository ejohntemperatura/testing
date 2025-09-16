<?php
date_default_timezone_set('Asia/Manila');
session_start();
require_once '../config/database.php';

// Debug information
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/index.php');
    exit();
}

// Get user information
$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: ../auth/index.php');
    exit();
}

// Get today's DTR record
$today = date('Y-m-d');
$stmt = $pdo->prepare("SELECT * FROM dtr WHERE user_id = ? AND date = ?");
$stmt->execute([$_SESSION['user_id'], $today]);
$today_record = $stmt->fetch();

// Handle time in and time out
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Get the exact current time from the server
    $current_time = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $formatted_time = $current_time->format('Y-m-d H:i:s');
    $current_hour = (int)$current_time->format('H');
    
    if ($_POST['action'] === 'time_in') {
        // STRICT VALIDATION: Check if Time In is allowed
        $time_in_allowed = false;
        
        if (!$today_record) {
            // State 1: No record exists (first time of day)
            $time_in_allowed = true;
        } else if ($today_record['morning_time_in'] && $today_record['morning_time_out'] && !$today_record['afternoon_time_in']) {
            // State 2: Morning completed (timed in AND out), ready for afternoon
            $time_in_allowed = true;
        }
        // BLOCK Time In if validation fails - NO NEXT SESSION
        if (!$time_in_allowed) {
            $_SESSION['error'] = "ACCESS DENIED: You must complete Time Out first before you can Time In again.";
            header('Location: dtr.php');
            exit();
        }
        
        // Process Time In only if validation passes
        if (!$today_record) {
            // Create new record for today (first time)
            $stmt = $pdo->prepare("INSERT INTO dtr (user_id, date, morning_time_in) VALUES (?, CURDATE(), ?)");
            if ($stmt->execute([$_SESSION['user_id'], $formatted_time])) {
                $_SESSION['message'] = "Time In recorded successfully at " . $current_time->format('h:i A');
            }
        } else if ($today_record['morning_time_in'] && $today_record['morning_time_out'] && !$today_record['afternoon_time_in']) {
            // Can time in for afternoon after morning time out
            $stmt = $pdo->prepare("UPDATE dtr SET afternoon_time_in = ? WHERE user_id = ? AND date = CURDATE()");
            if ($stmt->execute([$formatted_time, $_SESSION['user_id']])) {
                $_SESSION['message'] = "Time In recorded successfully at " . $current_time->format('h:i A');
            }
        }
    } else if ($_POST['action'] === 'time_out') {
        // STRICT VALIDATION: Check if Time Out is allowed
        $time_out_allowed = false;
        
        if ($today_record) {
            if ($today_record['morning_time_in'] && !$today_record['morning_time_out']) {
                // State: Morning time in exists but no time out - can time out
                $time_out_allowed = true;
            } else if ($today_record['afternoon_time_in'] && !$today_record['afternoon_time_out']) {
                // State: Afternoon time in exists but no time out - can time out
                $time_out_allowed = true;
            }
        }
        
        // BLOCK Time Out if validation fails
        if (!$time_out_allowed) {
            $_SESSION['error'] = "ACCESS DENIED: You must Time In first before you can Time Out.";
            header('Location: dtr.php');
            exit();
        }
        
        // Process Time Out only if validation passes
        if ($today_record['morning_time_in'] && !$today_record['morning_time_out']) {
            // Time out from morning session
            $stmt = $pdo->prepare("UPDATE dtr SET morning_time_out = ? WHERE user_id = ? AND date = CURDATE()");
            if ($stmt->execute([$formatted_time, $_SESSION['user_id']])) {
                $_SESSION['message'] = "Time Out recorded successfully at " . $current_time->format('h:i A');
            }
        } else if ($today_record['afternoon_time_in'] && !$today_record['afternoon_time_out']) {
            // Time out from afternoon session
            $stmt = $pdo->prepare("UPDATE dtr SET afternoon_time_out = ? WHERE user_id = ? AND date = CURDATE()");
            if ($stmt->execute([$formatted_time, $_SESSION['user_id']])) {
                $_SESSION['message'] = "Time Out recorded successfully at " . $current_time->format('h:i A');
            }
        }
    }
    header('Location: dtr.php');
    exit();
}

// Get recent DTR records
$stmt = $pdo->prepare("SELECT * FROM dtr WHERE user_id = ? ORDER BY date DESC, morning_time_in DESC LIMIT 10");
$stmt->execute([$_SESSION['user_id']]);
$recent_records = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ELMS - Time In/Out</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        body {
            background: #fff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .dtr-container {
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 10px 30px rgba(30, 60, 114, 0.10);
            padding: 2.2rem 2rem 2rem 2rem;
            width: 100%;
            max-width: 480px;
            margin: 2.5rem auto;
            animation: fadeIn 0.7s;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .current-time {
            text-align: center;
            margin-bottom: 2rem;
        }
        .current-time h1 {
            font-size: 3.2rem;
            font-weight: 700;
            color: #1e3c72;
            margin-bottom: 0.5rem;
            letter-spacing: 2px;
        }
        .current-time p {
            font-size: 1.1rem;
            color: #333;
        }
        .current-time .fa-clock {
            color: #2a5298;
            font-size: 2.2rem;
            margin-bottom: 0.5rem;
        }
        .dtr-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
            flex-wrap: wrap;
        }
        .btn-dtr {
            padding: 1rem 2rem;
            font-size: 1.15rem;
            font-weight: 600;
            border-radius: 10px;
            transition: all 0.3s cubic-bezier(.4,0,.2,1);
            box-shadow: 0 2px 8px rgba(30,60,114,0.07);
        }
        .btn-dtr:focus, .btn-dtr:hover {
            transform: translateY(-2px) scale(1.01);
            box-shadow: 0 5px 15px rgba(30, 60, 114, 0.13);
        }
        .status-info {
            background: #f5f7fa;
            border-radius: 12px;
            padding: 1.5rem 1.2rem;
            margin-top: 2.2rem;
            box-shadow: 0 2px 8px rgba(30,60,114,0.04);
        }
        .status-info h6 {
            color: #1e3c72;
            font-weight: 700;
            margin-bottom: 1.1rem;
            font-size: 1.1rem;
        }
        .status-info p {
            margin-bottom: 0.5rem;
            font-size: 1.08rem;
            color: #333;
        }
        .status-info strong {
            color: #1e3c72;
        }
        .alert {
            border-radius: 8px;
            margin-bottom: 20px;
            animation: shake 0.3s;
        }
        @keyframes shake {
            10%, 90% { transform: translateX(-2px); }
            20%, 80% { transform: translateX(4px); }
            30%, 50%, 70% { transform: translateX(-8px); }
            40%, 60% { transform: translateX(8px); }
        }
        .alert-success {
            color: #0f5132;
            background: #d1e7dd;
            border: 1px solid #badbcc;
        }
        .alert-danger {
            color: #b02a37;
            background: #fff3f4;
            border: 1px solid #f5c2c7;
        }
        .alert-info {
            color: #055160;
            background: #cff4fc;
            border: 1px solid #b6effb;
        }
        .badge {
            font-size: 1rem;
            padding: 0.5em 1em;
            border-radius: 8px;
        }
        @media (max-width: 600px) {
            .dtr-container { max-width: 99vw; padding: 1.2rem 0.2rem; }
            .current-time h1 { font-size: 2.1rem; }
            .status-info { padding: 1rem 0.5rem; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="dtr-container">
            <div class="text-center mb-4">
                <h2 class="mb-3">Welcome, <?php echo htmlspecialchars($user['name']); ?>!</h2>
                <p class="text-muted">Please record your attendance for today</p>
            </div>

            <div class="current-time">
                <div><i class="fas fa-clock"></i></div>
                <h1 id="current-time">00:00:00</h1>
                <p id="current-date">Loading...</p>
                <div class="status-indicator mt-3">
                    <span id="session-status" class="badge bg-secondary">Checking status...</span>
                </div>
            </div>

            <div id="alert-container">
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert" id="success-alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php 
                        echo $_SESSION['message'];
                        unset($_SESSION['message']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert" id="error-alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php 
                        echo $_SESSION['error'];
                        unset($_SESSION['error']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
            </div>

            <div class="dtr-buttons">
                <a href="dashboard.php" class="btn btn-success btn-dtr">
                    <i class="fas fa-tachometer-alt me-2"></i>Go to Dashboard
                </a>

                <?php 
                // PROPER STATE MACHINE: Determine which buttons to show
                $can_time_in = false;
                $can_time_out = false;
                $time_in_text = 'Time In';
                $time_out_text = 'Time Out';
                
                // State 1: No record exists (first time of day)
                if (!$today_record) {
                    $can_time_in = true;
                }
                // State 2: Morning time in exists but no time out
                else if ($today_record['morning_time_in'] && !$today_record['morning_time_out']) {
                    $can_time_out = true;
                    $time_out_text = 'Morning Time Out';
                }
                // State 3: Morning completed (timed in AND out), ready for afternoon
                else if ($today_record['morning_time_in'] && $today_record['morning_time_out'] && !$today_record['afternoon_time_in']) {
                    $can_time_in = true;
                    $time_in_text = 'Afternoon Time In';
                }
                // State 4: Afternoon time in exists but no time out
                else if ($today_record['afternoon_time_in'] && !$today_record['afternoon_time_out']) {
                    $can_time_out = true;
                    $time_out_text = 'Afternoon Time Out';
                }
                // NO MORE STATES - After afternoon time out, session is complete
                
                // Show Time In button if allowed
                if ($can_time_in):
                ?>
                <form method="POST" class="d-inline" id="time-in-form">
                    <input type="hidden" name="action" value="time_in">
                    <button type="submit" class="btn btn-primary btn-dtr" id="time-in-btn">
                        <span id="time-in-btn-text"><i class="fas fa-sign-in-alt me-2"></i><?php echo $time_in_text; ?></span>
                        <span class="spinner-border spinner-border-sm d-none" id="time-in-spinner" role="status" aria-hidden="true"></span>
                    </button>
                </form>
                <?php endif; ?>
                
                <!-- Show Time Out button if allowed -->
                <?php if ($can_time_out): ?>
                <form method="POST" class="d-inline" id="time-out-form">
                    <input type="hidden" name="action" value="time_out">
                    <button type="submit" class="btn btn-danger btn-dtr" id="time-out-btn">
                        <span id="time-out-btn-text"><i class="fas fa-sign-out-alt me-2"></i><?php echo $time_out_text; ?></span>
                        <span class="spinner-border spinner-border-sm d-none" id="time-out-spinner" role="status" aria-hidden="true"></span>
                    </button>
                </form>
                <?php endif; ?>
                
                <!-- Show message if neither button is available -->
                <?php if (!$can_time_in && !$can_time_out): ?>
                <div class="alert alert-info" id="status-message">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Session Complete!</strong> You have completed all time records for today.
                </div>
                <?php endif; ?>
            </div>

            <!-- Real-time status panel -->
            <div class="status-info mt-4" id="status-panel">
                <h6 class="mb-3"><i class="fas fa-clock me-2"></i>Today's Status</h6>
                <div id="status-content">
                    <?php if ($today_record): ?>
                        <?php if ($today_record['morning_time_in']): ?>
                            <p><strong>Morning Time In:</strong> <span id="morning-in"><?php echo date('h:i A', strtotime($today_record['morning_time_in'])); ?></span></p>
                        <?php endif; ?>
                        <?php if ($today_record['morning_time_out']): ?>
                            <p><strong>Morning Time Out:</strong> <span id="morning-out"><?php echo date('h:i A', strtotime($today_record['morning_time_out'])); ?></span></p>
                        <?php endif; ?>
                        <?php if ($today_record['afternoon_time_in']): ?>
                            <p><strong>Afternoon Time In:</strong> <span id="afternoon-in"><?php echo date('h:i A', strtotime($today_record['afternoon_time_in'])); ?></span></p>
                        <?php endif; ?>
                        <?php if ($today_record['afternoon_time_out']): ?>
                            <p><strong>Afternoon Time Out:</strong> <span id="afternoon-out"><?php echo date('h:i A', strtotime($today_record['afternoon_time_out'])); ?></span></p>
                        <?php endif; ?>
                        <?php
                        $total_hours_worked = 0;
                        if ($today_record['morning_time_in'] && $today_record['morning_time_out']) {
                            $morning_in = strtotime($today_record['morning_time_in']);
                            $morning_out = strtotime($today_record['morning_time_out']);
                            $total_hours_worked += ($morning_out - $morning_in) / 3600;
                        }
                        if ($today_record['afternoon_time_in'] && $today_record['afternoon_time_out']) {
                            $afternoon_in = strtotime($today_record['afternoon_time_in']);
                            $afternoon_out = strtotime($today_record['afternoon_time_out']);
                            $total_hours_worked += ($afternoon_out - $afternoon_in) / 3600;
                        }
                        if ($total_hours_worked > 0) :
                        ?>
                            <p><strong>Total Hours Worked:</strong> <span id="total-hours"><?php echo round($total_hours_worked, 2); ?></span> hours</p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p id="no-record">No time record for today</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Real-time functionality
        let refreshInterval;
        let lastStatus = '';

        // Update current time
        function updateTime() {
            const now = new Date();
            const timeElement = document.getElementById('current-time');
            const dateElement = document.getElementById('current-date');
            
            timeElement.textContent = now.toLocaleTimeString();
            dateElement.textContent = now.toLocaleDateString('en-US', { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
        }

        // Update session status
        function updateSessionStatus() {
            const statusElement = document.getElementById('session-status');
            const timeInBtn = document.getElementById('time-in-btn');
            const timeOutBtn = document.getElementById('time-out-btn');
            const statusMessage = document.getElementById('status-message');
            
            if (timeInBtn) {
                statusElement.textContent = 'Ready to Time In';
                statusElement.className = 'badge bg-success';
            } else if (timeOutBtn) {
                statusElement.textContent = 'Timed In - Ready to Time Out';
                statusElement.className = 'badge bg-warning';
            } else if (statusMessage) {
                statusElement.textContent = 'Session Complete';
                statusElement.className = 'badge bg-info';
            } else {
                statusElement.textContent = 'Checking status...';
                statusElement.className = 'badge bg-secondary';
            }
        }

        // Auto-refresh status every 5 seconds
        function startAutoRefresh() {
            refreshInterval = setInterval(() => {
                fetch('dtr_status.php')
                    .then(response => response.json())
                    .then(data => {
                        updateStatusPanel(data);
                        updateSessionStatus();
                    })
                    .catch(error => {
                        console.log('Status refresh failed:', error);
                    });
            }, 5000); // Refresh every 5 seconds
        }

        // Update status panel with real-time data
        function updateStatusPanel(data) {
            const statusContent = document.getElementById('status-content');
            const noRecord = document.getElementById('no-record');
            
            if (data.hasRecord) {
                if (noRecord) noRecord.style.display = 'none';
                
                let html = '';
                if (data.morning_time_in) {
                    html += `<p><strong>Morning Time In:</strong> <span id="morning-in">${data.morning_time_in}</span></p>`;
                }
                if (data.morning_time_out) {
                    html += `<p><strong>Morning Time Out:</strong> <span id="morning-out">${data.morning_time_out}</span></p>`;
                }
                if (data.afternoon_time_in) {
                    html += `<p><strong>Afternoon Time In:</strong> <span id="afternoon-in">${data.afternoon_time_in}</span></p>`;
                }
                if (data.afternoon_time_out) {
                    html += `<p><strong>Afternoon Time Out:</strong> <span id="afternoon-out">${data.afternoon_time_out}</span></p>`;
                }
                if (data.total_hours > 0) {
                    html += `<p><strong>Total Hours Worked:</strong> <span id="total-hours">${data.total_hours}</span> hours</p>`;
                }
                
                statusContent.innerHTML = html;
            } else {
                if (noRecord) {
                    noRecord.style.display = 'block';
                } else {
                    statusContent.innerHTML = '<p id="no-record">No time record for today</p>';
                }
            }
        }

        // Show real-time notification
        function showNotification(message, type = 'success') {
            const alertContainer = document.getElementById('alert-container');
            const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
            const icon = type === 'success' ? 'check-circle' : 'exclamation-circle';
            
            const alertHtml = `
                <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                    <i class="fas fa-${icon} me-2"></i>
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            
            alertContainer.innerHTML = alertHtml;
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                const alert = alertContainer.querySelector('.alert');
                if (alert) {
                    alert.remove();
                }
            }, 5000);
        }

        // Handle form submission with real-time feedback
        document.addEventListener('DOMContentLoaded', function() {
            const timeInForm = document.getElementById('time-in-form');
            const timeOutForm = document.getElementById('time-out-form');
            
            // Handle Time In form
            if (timeInForm) {
                timeInForm.addEventListener('submit', function(e) {
                    const btn = document.getElementById('time-in-btn');
                    const spinner = document.getElementById('time-in-spinner');
                    const btnText = document.getElementById('time-in-btn-text');
                    btn.disabled = true;
                    spinner.classList.remove('d-none');
                    btnText.style.opacity = 0.5;
                });
            }
            
            // Handle Time Out form
            if (timeOutForm) {
                timeOutForm.addEventListener('submit', function(e) {
                    const btn = document.getElementById('time-out-btn');
                    const spinner = document.getElementById('time-out-spinner');
                    const btnText = document.getElementById('time-out-btn-text');
                    btn.disabled = true;
                    spinner.classList.remove('d-none');
                    btnText.style.opacity = 0.5;
                });
            }

            // Initialize real-time features
            updateTime();
            updateSessionStatus();
            startAutoRefresh();
            
            // Update time every second
            setInterval(updateTime, 1000);
        });

        // Clean up interval when page is unloaded
        window.addEventListener('beforeunload', function() {
            if (refreshInterval) {
                clearInterval(refreshInterval);
            }
        });
    </script>
</body>
</html> 