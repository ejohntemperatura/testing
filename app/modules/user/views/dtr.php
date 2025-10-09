<?php
date_default_timezone_set('Asia/Manila');
session_start();
require_once '../../../../config/database.php';

// Debug information
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../../auth/views/login.php');
    exit();
}

// Get user information
$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: ../../../auth/views/login.php');
    exit();
}

// Get today's DTR record
$today = date('Y-m-d');
$stmt = $pdo->prepare("SELECT * FROM dtr WHERE user_id = ? AND date = ?");
$stmt->execute([$_SESSION['user_id'], $today]);
$today_record = $stmt->fetch();

// Handle time in/out
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_time = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $formatted_time = $current_time->format('Y-m-d H:i:s');
    $current_hour = (int)$current_time->format('H');
    
    if (isset($_POST['action'])) {
    if ($_POST['action'] === 'time_in') {
            // Check if it's morning or afternoon
            if ($current_hour < 12) {
                // Morning time in
        if (!$today_record) {
                    // Create new record
                    $stmt = $pdo->prepare("INSERT INTO dtr (user_id, date, morning_time_in) VALUES (?, ?, ?)");
                    if ($stmt->execute([$_SESSION['user_id'], $today, $formatted_time])) {
                        $_SESSION['message'] = "Morning Time In recorded successfully at " . $current_time->format('h:i A');
                    }
                } else {
                    // Update existing record
                    $stmt = $pdo->prepare("UPDATE dtr SET morning_time_in = ? WHERE user_id = ? AND date = ?");
                    if ($stmt->execute([$formatted_time, $_SESSION['user_id'], $today])) {
                        $_SESSION['message'] = "Morning Time In recorded successfully at " . $current_time->format('h:i A');
                    }
                }
            } else {
                // Afternoon time in
        if (!$today_record) {
                    // Create new record
                    $stmt = $pdo->prepare("INSERT INTO dtr (user_id, date, afternoon_time_in) VALUES (?, ?, ?)");
                    if ($stmt->execute([$_SESSION['user_id'], $today, $formatted_time])) {
                        $_SESSION['message'] = "Afternoon Time In recorded successfully at " . $current_time->format('h:i A');
                    }
                } else {
                    // Update existing record
                    $stmt = $pdo->prepare("UPDATE dtr SET afternoon_time_in = ? WHERE user_id = ? AND date = ?");
                    if ($stmt->execute([$formatted_time, $_SESSION['user_id'], $today])) {
                        $_SESSION['message'] = "Afternoon Time In recorded successfully at " . $current_time->format('h:i A');
                    }
                }
            }
        } elseif ($_POST['action'] === 'time_out') {
            if ($today_record && $today_record['morning_time_in'] && !$today_record['morning_time_out']) {
                // Morning time out
                $stmt = $pdo->prepare("UPDATE dtr SET morning_time_out = ? WHERE user_id = ? AND date = ?");
                if ($stmt->execute([$formatted_time, $_SESSION['user_id'], $today])) {
                    $_SESSION['message'] = "Morning Time Out recorded successfully at " . $current_time->format('h:i A');
                }
            } else if ($today_record && $today_record['afternoon_time_in'] && !$today_record['afternoon_time_out']) {
                // Afternoon time out
                $stmt = $pdo->prepare("UPDATE dtr SET afternoon_time_out = ? WHERE user_id = ? AND date = ?");
                if ($stmt->execute([$formatted_time, $_SESSION['user_id'], $today])) {
                    $_SESSION['message'] = "Afternoon Time Out recorded successfully at " . $current_time->format('h:i A');
                }
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
    <!-- OFFLINE Tailwind CSS - No internet required! -->
    <link rel="stylesheet" href="../../../../assets/css/tailwind.css">
        <!-- Font Awesome Local - No internet required! -->
    <link rel="stylesheet" href="../../../../assets/libs/fontawesome/css/all.min.css">
    

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ELMS - Time In/Out</title>
    <script>
    </script>
    
    <link rel="stylesheet" href="../../../../assets/css/style.css">
    <link rel="stylesheet" href="../../../../assets/css/dark-theme.css">
    
</head>
<body class="bg-slate-900 text-white min-h-screen">
    <?php include '../../../../includes/unified_navbar.php'; ?>

    <!-- Main Content -->
    <main class="pt-24 px-4 pb-6">
        <div class="max-w-4xl mx-auto">
            <!-- Welcome Section -->
            <div class="text-center mb-8 animate-fade-in mt-12">
                <h1 class="text-3xl font-bold text-white mb-2">Welcome, <?php echo htmlspecialchars($user['name']); ?>!</h1>
                <p class="text-slate-400">Please record your attendance for today</p>
            </div>

            <!-- Current Time Display -->
            <div class="bg-slate-800 rounded-2xl border border-slate-700 p-8 mb-8 text-center animate-slide-up">
                <div class="mb-6">
                    <i class="fas fa-clock text-4xl text-primary mb-4 animate-pulse-slow"></i>
                    <h2 id="current-time" class="text-6xl font-bold text-white mb-2 tracking-wider">00:00:00</h2>
                    <p id="current-date" class="text-xl text-slate-400">Loading...</p>
                </div>
                
                <!-- Status Indicator -->
                <div class="inline-block">
                    <span id="session-status" class="bg-slate-700 text-slate-300 px-4 py-2 rounded-full text-sm font-semibold">
                        Checking status...
                    </span>
                </div>
            </div>

            <!-- Alert Messages -->
            <div id="alert-container" class="mb-8">
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="bg-green-500/20 border border-green-500/30 text-green-400 p-4 rounded-xl mb-4 flex items-center animate-slide-up">
                        <i class="fas fa-check-circle mr-3"></i>
                        <?php 
                        echo $_SESSION['message'];
                        unset($_SESSION['message']);
                        ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="bg-red-500/20 border border-red-500/30 text-red-400 p-4 rounded-xl mb-4 flex items-center animate-slide-up">
                        <i class="fas fa-exclamation-circle mr-3"></i>
                        <?php 
                        echo $_SESSION['error'];
                        unset($_SESSION['error']);
                        ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- DTR Buttons -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <!-- Dashboard Button -->
                <a href="dashboard.php" class="bg-gradient-to-r from-primary to-accent hover:from-primary/90 hover:to-accent/90 text-white font-semibold py-4 px-6 rounded-xl transition-all duration-300 transform hover:scale-[1.02] hover:shadow-xl text-center flex items-center justify-center">
                    <i class="fas fa-tachometer-alt mr-2"></i>
                    Go to Dashboard
                </a>

                <?php 
                // Determine which buttons to show
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
                // State 3: Morning completed, ready for afternoon
                else if ($today_record['morning_time_in'] && $today_record['morning_time_out'] && !$today_record['afternoon_time_in']) {
                    $can_time_in = true;
                    $time_in_text = 'Afternoon Time In';
                }
                // State 4: Afternoon time in exists but no time out
                else if ($today_record['afternoon_time_in'] && !$today_record['afternoon_time_out']) {
                    $can_time_out = true;
                    $time_out_text = 'Afternoon Time Out';
                }
                
                // Show Time In button if allowed
                if ($can_time_in):
                ?>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="action" value="time_in">
                    <button type="submit" class="w-full bg-gradient-to-r from-green-500 to-emerald-500 hover:from-green-400 hover:to-emerald-400 text-white font-semibold py-4 px-6 rounded-xl transition-all duration-300 transform hover:scale-[1.02] hover:shadow-xl flex items-center justify-center">
                        <i class="fas fa-sign-in-alt mr-2"></i>
                        <?php echo $time_in_text; ?>
                    </button>
                </form>
                <?php endif; ?>
                
                <?php if ($can_time_out): ?>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="action" value="time_out">
                    <button type="submit" class="w-full bg-gradient-to-r from-orange-500 to-red-500 hover:from-orange-400 hover:to-red-400 text-white font-semibold py-4 px-6 rounded-xl transition-all duration-300 transform hover:scale-[1.02] hover:shadow-xl flex items-center justify-center">
                        <i class="fas fa-sign-out-alt mr-2"></i>
                        <?php echo $time_out_text; ?>
                    </button>
                </form>
                <?php endif; ?>
            </div>

            <!-- Today's Status -->
            <div class="bg-slate-800 rounded-2xl border border-slate-700 p-6 mb-8">
                <h3 class="text-xl font-semibold text-white mb-4 flex items-center">
                    <i class="fas fa-calendar-day text-primary mr-2"></i>
                    Today's Status
                </h3>
                
                <?php if ($today_record): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Morning Session -->
                        <div class="bg-slate-700/30 rounded-xl p-4">
                            <h4 class="text-lg font-semibold text-white mb-3 flex items-center">
                                <i class="fas fa-sun text-yellow-400 mr-2"></i>
                                Morning Session
                            </h4>
                            <div class="space-y-2">
                                <div class="flex justify-between items-center">
                                    <span class="text-slate-400">Time In:</span>
                                    <span class="text-white font-mono">
                                        <?php echo $today_record['morning_time_in'] ? date('h:i A', strtotime($today_record['morning_time_in'])) : 'Not recorded'; ?>
                                    </span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-slate-400">Time Out:</span>
                                    <span class="text-white font-mono">
                                        <?php echo $today_record['morning_time_out'] ? date('h:i A', strtotime($today_record['morning_time_out'])) : 'Not recorded'; ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Afternoon Session -->
                        <div class="bg-slate-700/30 rounded-xl p-4">
                            <h4 class="text-lg font-semibold text-white mb-3 flex items-center">
                                <i class="fas fa-moon text-blue-400 mr-2"></i>
                                Afternoon Session
                            </h4>
                            <div class="space-y-2">
                                <div class="flex justify-between items-center">
                                    <span class="text-slate-400">Time In:</span>
                                    <span class="text-white font-mono">
                                        <?php echo $today_record['afternoon_time_in'] ? date('h:i A', strtotime($today_record['afternoon_time_in'])) : 'Not recorded'; ?>
                                    </span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-slate-400">Time Out:</span>
                                    <span class="text-white font-mono">
                                        <?php echo $today_record['afternoon_time_out'] ? date('h:i A', strtotime($today_record['afternoon_time_out'])) : 'Not recorded'; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <i class="fas fa-calendar-times text-4xl text-slate-500 mb-4"></i>
                        <p class="text-slate-400">No attendance recorded for today yet.</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Recent Records -->
            <?php if (!empty($recent_records)): ?>
            <div class="bg-slate-800 rounded-2xl border border-slate-700 p-6">
                <h3 class="text-xl font-semibold text-white mb-4 flex items-center">
                    <i class="fas fa-history text-primary mr-2"></i>
                    Recent Attendance Records
                </h3>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-700">
                                <th class="text-left py-3 px-4 text-slate-400">Date</th>
                                <th class="text-left py-3 px-4 text-slate-400">Morning In</th>
                                <th class="text-left py-3 px-4 text-slate-400">Morning Out</th>
                                <th class="text-left py-3 px-4 text-slate-400">Afternoon In</th>
                                <th class="text-left py-3 px-4 text-slate-400">Afternoon Out</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_records as $record): ?>
                            <tr class="border-b border-slate-700">
                                <td class="py-3 px-4 text-white font-mono"><?php echo date('M d, Y', strtotime($record['date'])); ?></td>
                                <td class="py-3 px-4 text-slate-300 font-mono"><?php echo $record['morning_time_in'] ? date('h:i A', strtotime($record['morning_time_in'])) : '-'; ?></td>
                                <td class="py-3 px-4 text-slate-300 font-mono"><?php echo $record['morning_time_out'] ? date('h:i A', strtotime($record['morning_time_out'])) : '-'; ?></td>
                                <td class="py-3 px-4 text-slate-300 font-mono"><?php echo $record['afternoon_time_in'] ? date('h:i A', strtotime($record['afternoon_time_in'])) : '-'; ?></td>
                                <td class="py-3 px-4 text-slate-300 font-mono"><?php echo $record['afternoon_time_out'] ? date('h:i A', strtotime($record['afternoon_time_out'])) : '-'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>

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

        // Update time display
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', { 
                hour12: false,
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            const dateString = now.toLocaleDateString('en-US', { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
            
            document.getElementById('current-time').textContent = timeString;
            document.getElementById('current-date').textContent = dateString;
        }

        // Update status indicator
        function updateStatus() {
            const now = new Date();
            const hour = now.getHours();
            const statusElement = document.getElementById('session-status');
            
            if (hour < 12) {
                statusElement.textContent = 'Morning Session';
                statusElement.className = 'bg-yellow-500/20 text-yellow-400 px-4 py-2 rounded-full text-sm font-semibold';
            } else if (hour < 17) {
                statusElement.textContent = 'Afternoon Session';
                statusElement.className = 'bg-blue-500/20 text-blue-400 px-4 py-2 rounded-full text-sm font-semibold';
            } else {
                statusElement.textContent = 'After Hours';
                statusElement.className = 'bg-slate-700 text-slate-300 px-4 py-2 rounded-full text-sm font-semibold';
            }
        }

        // Initialize
            updateTime();
        updateStatus();
            setInterval(updateTime, 1000);
        setInterval(updateStatus, 60000); // Update status every minute

        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('#alert-container > div');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s ease-out';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html> 