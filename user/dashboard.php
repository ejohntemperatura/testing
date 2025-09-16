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

// Get today's DTR record
$today = date('Y-m-d');
$stmt = $pdo->prepare("SELECT * FROM dtr WHERE user_id = ? AND date = ?");
$stmt->execute([$_SESSION['user_id'], $today]);
$today_record = $stmt->fetch();

// Handle time out only
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $current_time = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $formatted_time = $current_time->format('Y-m-d H:i:s');
    $current_hour = (int)$current_time->format('H');

    if ($_POST['action'] === 'time_out') {
        if ($today_record && $today_record['morning_time_in'] && !$today_record['morning_time_out']) {
            // Morning time out
            $stmt = $pdo->prepare("UPDATE dtr SET morning_time_out = ? WHERE user_id = ? AND date = CURDATE()");
            if ($stmt->execute([$formatted_time, $_SESSION['user_id']])) {
                $_SESSION['message'] = "Time Out recorded successfully at " . $current_time->format('h:i A');
                unset($_SESSION['logged_in_this_session']); // Clear session flag after time out
            }
        } else if ($today_record && $today_record['afternoon_time_in'] && !$today_record['afternoon_time_out']) {
            // Afternoon time out
            $stmt = $pdo->prepare("UPDATE dtr SET afternoon_time_out = ? WHERE user_id = ? AND date = CURDATE()");
            if ($stmt->execute([$formatted_time, $_SESSION['user_id']])) {
                $_SESSION['message'] = "Afternoon Time Out recorded successfully at " . $current_time->format('h:i A');
                unset($_SESSION['logged_in_this_session']); // Clear session flag after time out
            }
        } else {
            $_SESSION['error'] = "Invalid time out request. You need to time in first from the DTR page.";
        }
    }
    header('Location: dashboard.php'); // Redirect back to dashboard to refresh status
    exit();
}

// Fetch user's leave requests
$stmt = $pdo->prepare("SELECT * FROM leave_requests WHERE employee_id = ? ORDER BY created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$leave_requests = $stmt->fetchAll();

// Fetch recent leave alerts
$stmt = $pdo->prepare("
    SELECT la.*, e.name as sent_by_name 
    FROM leave_alerts la 
    LEFT JOIN employees e ON la.sent_by = e.id 
    WHERE la.employee_id = ? 
    ORDER BY la.created_at DESC 
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$leave_alerts = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ELMS - Dashboard</title>
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
                            <i class="fas fa-user text-white text-sm"></i>
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
                <!-- Active Navigation Item -->
                <a href="dashboard.php" class="flex items-center space-x-3 px-4 py-3 text-white bg-primary/20 rounded-lg border border-primary/30">
                    <i class="fas fa-tachometer-alt w-5"></i>
                <span>Dashboard</span>
            </a>
                
                <!-- Other Navigation Items -->
                <a href="leave_history.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                    <i class="fas fa-history w-5"></i>
                <span>Leave History</span>
            </a>
                
                <a href="profile.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                    <i class="fas fa-user w-5"></i>
                <span>Profile</span>
            </a>
                
                <a href="leave_credits.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
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
            <!-- Welcome Section -->
                <div class="mb-8">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <div class="w-16 h-16 bg-gradient-to-r from-primary to-accent rounded-2xl flex items-center justify-center">
                                <i class="fas fa-sun text-2xl text-white"></i>
                        </div>
                        <div>
                                <h1 class="text-3xl font-bold text-white mb-2">
                                Good <?php echo date('H') < 12 ? 'Morning' : (date('H') < 17 ? 'Afternoon' : 'Evening'); ?>, <?php echo htmlspecialchars($employee['name']); ?>!
                                </h1>
                                <p class="text-slate-400 flex items-center">
                                    <i class="fas fa-calendar-alt mr-2"></i>
                                Today is <?php echo date('l, F j, Y'); ?>
                            </p>
                        </div>
                    </div>
                        
                        <!-- Notification Button -->
                        <div class="relative">
                            <button class="bg-slate-800 hover:bg-slate-700 text-white px-4 py-3 rounded-xl transition-colors flex items-center space-x-2" onclick="toggleNotifications()">
                                <i class="fas fa-bell text-lg"></i>
                                <span id="notification-count" class="font-semibold text-primary">0</span>
                                <span id="notification-badge" class="bg-red-500 text-white text-xs px-2 py-1 rounded-full hidden">0</span>
                            </button>
                            
                            <!-- Notification Dropdown -->
                            <div id="notificationDropdown" class="absolute right-0 mt-2 w-80 bg-slate-800 border border-slate-700 rounded-xl shadow-xl z-50 hidden">
                                <div class="p-4 border-b border-slate-700">
                                    <h3 class="text-lg font-semibold text-white flex items-center">
                                        <i class="fas fa-bell mr-2"></i>Notifications
                                    </h3>
                                    </div>
                                <div id="notifications-list" class="max-h-64 overflow-y-auto">
                                    <div class="text-center text-slate-400 py-8">
                                        <i class="fas fa-bell-slash text-3xl mb-3"></i>
                                        <p>No notifications</p>
                                    </div>
                                </div>
                                <div class="p-4 border-t border-slate-700">
                                    <a href="leave_history.php" class="text-primary hover:text-primary/80 text-sm font-medium">
                                        <i class="fas fa-eye mr-2"></i>View All Alerts
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
            </div>

                <!-- Success/Error Messages -->
            <?php if (isset($_SESSION['message'])): ?>
                    <div class="bg-green-500/20 border border-green-500/30 text-green-400 p-4 rounded-xl mb-6 flex items-center">
                        <i class="fas fa-check-circle mr-3"></i>
                    <?php 
                    echo $_SESSION['message'];
                    unset($_SESSION['message']);
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                    <div class="bg-red-500/20 border border-red-500/30 text-red-400 p-4 rounded-xl mb-6 flex items-center">
                        <i class="fas fa-exclamation-circle mr-3"></i>
                    <?php 
                    echo $_SESSION['error'];
                    unset($_SESSION['error']);
                    ?>
                </div>
            <?php endif; ?>

                <!-- Quick Actions -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-6 border border-slate-700/50 hover:border-slate-600/50 transition-all duration-300">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-slate-400 text-sm font-medium">Total Leave Requests</p>
                                <p class="text-2xl font-bold text-white"><?php echo count($leave_requests); ?></p>
                            </div>
                            <div class="w-12 h-12 bg-primary/20 rounded-xl flex items-center justify-center">
                                <i class="fas fa-calendar-check text-primary text-xl"></i>
                    </div>
                </div>
            </div>

                    <div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-6 border border-slate-700/50 hover:border-slate-600/50 transition-all duration-300">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-slate-400 text-sm font-medium">Pending Requests</p>
                                <p class="text-2xl font-bold text-white"><?php echo count(array_filter($leave_requests, function($req) { return $req['status'] === 'pending'; })); ?></p>
                        </div>
                            <div class="w-12 h-12 bg-yellow-500/20 rounded-xl flex items-center justify-center">
                                <i class="fas fa-clock text-yellow-400 text-xl"></i>
                            </div>
                        </div>
                    </div>

                    <div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-6 border border-slate-700/50 hover:border-slate-600/50 transition-all duration-300">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-slate-400 text-sm font-medium">Approved Requests</p>
                                <p class="text-2xl font-bold text-white"><?php echo count(array_filter($leave_requests, function($req) { return $req['status'] === 'approved'; })); ?></p>
                </div>
                            <div class="w-12 h-12 bg-green-500/20 rounded-xl flex items-center justify-center">
                                <i class="fas fa-check-circle text-green-400 text-xl"></i>
            </div>
        </div>
    </div>

                    <div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-6 border border-slate-700/50 hover:border-slate-600/50 transition-all duration-300">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-slate-400 text-sm font-medium">Alerts</p>
                                <p class="text-2xl font-bold text-white"><?php echo count($leave_alerts); ?></p>
    </div>
                            <div class="w-12 h-12 bg-red-500/20 rounded-xl flex items-center justify-center">
                                <i class="fas fa-bell text-red-400 text-xl"></i>
                            </div>
                            </div>
                            </div>
                </div>

                <!-- Recent Leave Requests -->
                <div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl border border-slate-700/50 overflow-hidden mb-8">
                    <div class="px-6 py-4 border-b border-slate-700/50 bg-slate-700/30">
                        <h3 class="text-xl font-semibold text-white flex items-center">
                            <i class="fas fa-history text-primary mr-3"></i>Recent Leave Requests
                        </h3>
                        </div>
                    <div class="p-6">
                        <?php if (empty($leave_requests)): ?>
                            <div class="text-center py-12">
                                <i class="fas fa-inbox text-4xl text-slate-500 mb-4"></i>
                                <p class="text-slate-400 text-lg">No leave requests yet</p>
                                <p class="text-slate-500 text-sm">Start by applying for your first leave request</p>
                                <a href="apply_leave.php" class="mt-4 inline-block bg-gradient-to-r from-primary to-accent hover:from-primary/90 hover:to-accent/90 text-white font-semibold py-3 px-6 rounded-xl transition-all duration-300 transform hover:scale-[1.02] hover:shadow-xl">
                                    <i class="fas fa-plus mr-2"></i>Apply for Leave
                                </a>
                </div>
                        <?php else: ?>
                            <div class="space-y-4">
                                <?php foreach (array_slice($leave_requests, 0, 5) as $request): ?>
                                    <div class="bg-slate-700/30 rounded-xl p-4 border border-slate-600/30">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center space-x-4">
                                                <div class="w-10 h-10 bg-primary/20 rounded-lg flex items-center justify-center">
                                                    <i class="fas fa-calendar text-primary"></i>
                            </div>
                                                <div>
                                                    <h4 class="text-white font-semibold"><?php echo ucfirst(str_replace('_', ' ', $request['leave_type'])); ?></h4>
                                                    <p class="text-slate-400 text-sm">
                                                        <?php echo date('M d, Y', strtotime($request['start_date'])); ?> - 
                                                        <?php echo date('M d, Y', strtotime($request['end_date'])); ?>
                                                    </p>
                                        </div>
                                    </div>
                                            <div class="text-right">
                                                <span class="px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wide <?php 
                                                    echo $request['status'] === 'approved' ? 'bg-green-500/20 text-green-400' : 
                                                        ($request['status'] === 'rejected' ? 'bg-red-500/20 text-red-400' : 'bg-yellow-500/20 text-yellow-400'); 
                                                ?>">
                                                    <?php echo ucfirst($request['status']); ?>
                                                </span>
                                                <p class="text-slate-400 text-sm mt-1">
                                                    <?php echo date('M d, Y', strtotime($request['created_at'])); ?>
                                                </p>
                                </div>
                            </div>
                            </div>
                                <?php endforeach; ?>
                        </div>
                            <div class="text-center mt-6">
                                <a href="leave_history.php" class="bg-primary/20 hover:bg-primary/30 text-primary border border-primary/30 font-semibold py-3 px-6 rounded-xl transition-colors">
                                    View All Requests
                                </a>
                    </div>
                        <?php endif; ?>
                </div>
                    </div>

                <!-- Quick Actions -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <a href="apply_leave.php" class="bg-gradient-to-r from-primary to-accent hover:from-primary/90 hover:to-accent/90 text-white font-semibold py-6 px-6 rounded-2xl transition-all duration-300 transform hover:scale-[1.02] hover:shadow-xl text-center">
                        <i class="fas fa-calendar-plus text-2xl mb-3 block"></i>
                        <h3 class="text-lg font-semibold mb-2">Apply for Leave</h3>
                        <p class="text-sm opacity-90">Submit a new leave request</p>
                    </a>

                    <a href="leave_history.php" class="bg-slate-800/50 hover:bg-slate-700/50 backdrop-blur-sm border border-slate-700/50 hover:border-slate-600/50 text-white font-semibold py-6 px-6 rounded-2xl transition-all duration-300 transform hover:scale-[1.02] hover:shadow-xl text-center">
                        <i class="fas fa-history text-2xl mb-3 block text-primary"></i>
                        <h3 class="text-lg font-semibold mb-2">Leave History</h3>
                        <p class="text-sm text-slate-400">View all your leave requests</p>
                    </a>

                    <a href="profile.php" class="bg-slate-800/50 hover:bg-slate-700/50 backdrop-blur-sm border border-slate-700/50 hover:border-slate-600/50 text-white font-semibold py-6 px-6 rounded-2xl transition-all duration-300 transform hover:scale-[1.02] hover:shadow-xl text-center">
                        <i class="fas fa-user text-2xl mb-3 block text-primary"></i>
                        <h3 class="text-lg font-semibold mb-2">Profile</h3>
                        <p class="text-sm text-slate-400">Manage your account settings</p>
                    </a>
                        </div>
                                    </div>
        </main>
                                </div>

    <script>
        // Notification toggle function
        function toggleNotifications() {
            const dropdown = document.getElementById('notificationDropdown');
            dropdown.classList.toggle('hidden');
        }

        // Close modals when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('notificationDropdown');
            if (dropdown && !dropdown.contains(event.target) && !event.target.closest('button[onclick="toggleNotifications()"]')) {
                dropdown.classList.add('hidden');
            }
        });

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Any initialization code can go here
        });
    </script>
</body>
</html>