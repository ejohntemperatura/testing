<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/index.php');
    exit();
}

// Fetch employee's leave information
$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$employee = $stmt->fetch();

// Get selected year or current year
$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Fetch leave history for the selected year
$stmt = $pdo->prepare("
    SELECT 
        leave_type,
        SUM(DATEDIFF(end_date, start_date) + 1) as days_used
    FROM leave_requests 
    WHERE employee_id = ? 
    AND YEAR(start_date) = ? 
    AND status = 'approved'
    GROUP BY leave_type
");
$stmt->execute([$_SESSION['user_id'], $selectedYear]);
$leave_usage = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Calculate real-time available credits
$leave_balances = [
    'annual' => $employee['annual_leave_balance'] ?? 20,
    'vacation' => $employee['vacation_leave_balance'] ?? 15,
    'sick' => $employee['sick_leave_balance'] ?? 10,
    'maternity' => $employee['maternity_leave_balance'] ?? 90,
    'paternity' => $employee['paternity_leave_balance'] ?? 7,
    'bereavement' => $employee['bereavement_leave_balance'] ?? 3,
    'study' => $employee['study_leave_balance'] ?? 5,
    'emergency' => $employee['emergency_leave_balance'] ?? 3
];

// Calculate remaining credits
$remaining_credits = [];
foreach ($leave_balances as $type => $total) {
    $used = isset($leave_usage[$type]) ? $leave_usage[$type] : 0;
    $remaining_credits[$type] = max(0, $total - $used);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ELMS - Leave Credits</title>
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
                            <i class="fas fa-calculator text-white text-sm"></i>
                        </div>
                        <span class="text-xl font-bold text-white">ELMS Employee</span>
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
                <a href="dashboard.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                    <i class="fas fa-tachometer-alt w-5"></i>
                    <span>Dashboard</span>
                </a>
                
                <a href="leave_history.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                    <i class="fas fa-history w-5"></i>
                    <span>Leave History</span>
                </a>
                
                <a href="profile.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                    <i class="fas fa-user w-5"></i>
                    <span>Profile</span>
                </a>
                
                <!-- Active Navigation Item -->
                <a href="leave_credits.php" class="flex items-center space-x-3 px-4 py-3 text-white bg-primary/20 rounded-lg border border-primary/30">
                    <i class="fas fa-calculator w-5"></i>
                    <span>Leave Credits</span>
                </a>
                
                <a href="apply_leave.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                    <i class="fas fa-calendar-plus w-5"></i>
                    <span>Apply Leave</span>
                </a>
                
                <a href="view_chart.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                    <i class="fas fa-calendar w-5"></i>
                    <span>Leave Chart</span>
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
                            <i class="fas fa-calculator text-2xl text-white"></i>
                        </div>
                        <div>
                            <h1 class="text-3xl font-bold text-white mb-2">Leave Credits</h1>
                            <p class="text-slate-400">Track your available leave credits and usage</p>
                        </div>
                    </div>
                </div>

            <!-- Year Selector -->
            <div class="row mb-4">
                <div class="col-12">
                    <div style="padding: 1.5rem;">
                        <div class="year-selector">
                            <label class="form-label">Select Year:</label>
                            <select class="form-select" id="yearSelect" style="max-width: 200px;">
                                <?php
                                $currentYear = date('Y');
                                for ($year = $currentYear; $year >= $currentYear - 2; $year--) {
                                    echo "<option value='$year'" . ($year == $currentYear ? ' selected' : '') . ">$year</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Leave Credits -->
            <div class="row">
                <div class="col-md-6">
                    <div class="leave-credits-card">
                        <h4><i class="fas fa-calendar-check me-2"></i>Regular Leaves</h4>
                        <div class="credit-item">
                            <div class="credit-label">Annual Leave</div>
                            <div class="credit-details">
                                <div class="credit-value"><?php echo $remaining_credits['annual']; ?> days remaining</div>
                                <div class="credit-used">
                                    Total: <?php echo $leave_balances['annual']; ?> | Used: <?php echo isset($leave_usage['annual']) ? $leave_usage['annual'] : 0; ?> days
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg-success" role="progressbar" 
                                         style="width: <?php echo (isset($leave_usage['annual']) ? ($leave_usage['annual'] / $leave_balances['annual']) * 100 : 0); ?>%">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="credit-item">
                            <div class="credit-label">Vacation Leave</div>
                            <div class="credit-details">
                                <div class="credit-value"><?php echo $remaining_credits['vacation']; ?> days remaining</div>
                                <div class="credit-used">
                                    Total: <?php echo $leave_balances['vacation']; ?> | Used: <?php echo isset($leave_usage['vacation']) ? $leave_usage['vacation'] : 0; ?> days
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg-info" role="progressbar" 
                                         style="width: <?php echo (isset($leave_usage['vacation']) ? ($leave_usage['vacation'] / $leave_balances['vacation']) * 100 : 0); ?>%">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="credit-item">
                            <div class="credit-label">Sick Leave</div>
                            <div class="credit-details">
                                <div class="credit-value"><?php echo $remaining_credits['sick']; ?> days remaining</div>
                                <div class="credit-used">
                                    Total: <?php echo $leave_balances['sick']; ?> | Used: <?php echo isset($leave_usage['sick']) ? $leave_usage['sick'] : 0; ?> days
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg-warning" role="progressbar" 
                                         style="width: <?php echo (isset($leave_usage['sick']) ? ($leave_usage['sick'] / $leave_balances['sick']) * 100 : 0); ?>%">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="leave-credits-card">
                        <h4><i class="fas fa-calendar-plus me-2"></i>Special Leaves</h4>
                        <div class="credit-item">
                            <div class="credit-label">Maternity Leave</div>
                            <div class="credit-details">
                                <div class="credit-value"><?php echo $remaining_credits['maternity']; ?> days remaining</div>
                                <div class="credit-used">
                                    Total: <?php echo $leave_balances['maternity']; ?> | Used: <?php echo isset($leave_usage['maternity']) ? $leave_usage['maternity'] : 0; ?> days
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg-info" role="progressbar" 
                                         style="width: <?php echo (isset($leave_usage['maternity']) ? ($leave_usage['maternity'] / $leave_balances['maternity']) * 100 : 0); ?>%">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="credit-item">
                            <div class="credit-label">Paternity Leave</div>
                            <div class="credit-details">
                                <div class="credit-value"><?php echo $remaining_credits['paternity']; ?> days remaining</div>
                                <div class="credit-used">
                                    Total: <?php echo $leave_balances['paternity']; ?> | Used: <?php echo isset($leave_usage['paternity']) ? $leave_usage['paternity'] : 0; ?> days
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg-info" role="progressbar" 
                                         style="width: <?php echo (isset($leave_usage['paternity']) ? ($leave_usage['paternity'] / $leave_balances['paternity']) * 100 : 0); ?>%">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="credit-item">
                            <div class="credit-label">Bereavement Leave</div>
                            <div class="credit-details">
                                <div class="credit-value"><?php echo $remaining_credits['bereavement']; ?> days remaining</div>
                                <div class="credit-used">
                                    Total: <?php echo $leave_balances['bereavement']; ?> | Used: <?php echo isset($leave_usage['bereavement']) ? $leave_usage['bereavement'] : 0; ?> days
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg-secondary" role="progressbar" 
                                         style="width: <?php echo (isset($leave_usage['bereavement']) ? ($leave_usage['bereavement'] / $leave_balances['bereavement']) * 100 : 0); ?>%">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="credit-item">
                            <div class="credit-label">Study Leave</div>
                            <div class="credit-details">
                                <div class="credit-value"><?php echo $remaining_credits['study']; ?> days remaining</div>
                                <div class="credit-used">
                                    Total: <?php echo $leave_balances['study']; ?> | Used: <?php echo isset($leave_usage['study']) ? $leave_usage['study'] : 0; ?> days
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg-primary" role="progressbar" 
                                         style="width: <?php echo (isset($leave_usage['study']) ? ($leave_usage['study'] / $leave_balances['study']) * 100 : 0); ?>%">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="credit-item">
                            <div class="credit-label">Emergency Leave</div>
                            <div class="credit-details">
                                <div class="credit-value"><?php echo $remaining_credits['emergency']; ?> days remaining</div>
                                <div class="credit-used">
                                    Total: <?php echo $leave_balances['emergency']; ?> | Used: <?php echo isset($leave_usage['emergency']) ? $leave_usage['emergency'] : 0; ?> days
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg-danger" role="progressbar" 
                                         style="width: <?php echo (isset($leave_usage['emergency']) ? ($leave_usage['emergency'] / $leave_balances['emergency']) * 100 : 0); ?>%">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh every 30 seconds for real-time updates
        setInterval(function() {
            // Only refresh if user is still on the page
            if (!document.hidden) {
                location.reload();
            }
        }, 30000);

        // Year selection handler
        document.getElementById('yearSelect').addEventListener('change', function() {
            // Reload page with selected year
            window.location.href = 'leave_credits.php?year=' + this.value;
        });

        // Mobile sidebar toggle
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('active');
            document.querySelector('.main-content').classList.toggle('active');
        }

        // Add real-time clock
        function updateClock() {
            const now = new Date();
            const timeString = now.toLocaleTimeString();
            const dateString = now.toLocaleDateString();
            
            // Update any clock elements if they exist
            const clockElements = document.querySelectorAll('.real-time-clock');
            clockElements.forEach(element => {
                element.textContent = `${dateString} ${timeString}`;
            });
        }

        // Update clock every second
        setInterval(updateClock, 1000);
        updateClock(); // Initial call

        // Add visual indicators for low credits
        document.addEventListener('DOMContentLoaded', function() {
            const creditValues = document.querySelectorAll('.credit-value');
            creditValues.forEach(element => {
                const text = element.textContent;
                const remaining = parseInt(text.match(/\d+/)[0]);
                
                if (remaining <= 2) {
                    element.style.color = '#dc3545'; // Red for very low credits
                    element.style.fontWeight = 'bold';
                    element.innerHTML = text + ' <i class="fas fa-exclamation-triangle text-warning"></i>';
                } else if (remaining <= 5) {
                    element.style.color = '#ffc107'; // Yellow for low credits
                    element.style.fontWeight = 'bold';
                }
            });
        });
    </script>
</body>
</html> 