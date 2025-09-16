<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/index.php');
    exit();
}

// Get employee details
$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $leave_type = $_POST['leave_type'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $reason = $_POST['reason'];
    
    // Validate dates
    if (strtotime($start_date) > strtotime($end_date)) {
        $error = "End date must be after start date.";
    } else {
        try {
            // Insert leave request
            $stmt = $pdo->prepare("
                INSERT INTO leave_requests (employee_id, leave_type, start_date, end_date, reason, status, created_at) 
                VALUES (?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([$_SESSION['user_id'], $leave_type, $start_date, $end_date, $reason]);
            
            $_SESSION['success'] = "Leave application submitted successfully!";
            header('Location: leave_history.php');
            exit();
        } catch (Exception $e) {
            $error = "Error submitting leave application: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply Leave - ELMS</title>
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
                
                <a href="leave_credits.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                    <i class="fas fa-calculator w-5"></i>
                    <span>Leave Credits</span>
                </a>
                
                <!-- Active Navigation Item -->
                <a href="apply_leave.php" class="flex items-center space-x-3 px-4 py-3 text-white bg-primary/20 rounded-lg border border-primary/30">
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
                            <i class="fas fa-calendar-plus text-2xl text-white"></i>
                        </div>
                        <div>
                            <h1 class="text-3xl font-bold text-white mb-2">Apply Leave</h1>
                            <p class="text-slate-400">Submit your regular leave application</p>
                        </div>
                    </div>
                </div>

                <!-- Error Message -->
                <?php if (isset($error)): ?>
                    <div class="bg-red-500/20 border border-red-500/30 text-red-400 p-4 rounded-xl mb-6 flex items-center">
                        <i class="fas fa-exclamation-circle mr-3"></i>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <!-- Leave Application Form -->
                <div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl border border-slate-700/50 overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-700/50 bg-slate-700/30">
                        <h3 class="text-xl font-semibold text-white flex items-center">
                            <i class="fas fa-file-alt text-primary mr-3"></i>
                            Leave Application Form
                        </h3>
                    </div>
                    <div class="p-8">
                        <form method="POST" action="" class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="leave_type" class="block text-sm font-semibold text-slate-300 mb-2">
                                        <i class="fas fa-calendar-check mr-2"></i>Leave Type
                                    </label>
                                    <select id="leave_type" name="leave_type" required class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        <option value="">Select Leave Type</option>
                                        <option value="annual">Annual Leave</option>
                                        <option value="vacation">Vacation Leave</option>
                                        <option value="sick">Sick Leave</option>
                                        <option value="maternity">Maternity Leave</option>
                                        <option value="paternity">Paternity Leave</option>
                                        <option value="bereavement">Bereavement Leave</option>
                                        <option value="study">Study Leave</option>
                                        <option value="emergency">Emergency Leave</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="start_date" class="block text-sm font-semibold text-slate-300 mb-2">
                                        <i class="fas fa-calendar-day mr-2"></i>Start Date
                                    </label>
                                    <input type="date" id="start_date" name="start_date" required class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="end_date" class="block text-sm font-semibold text-slate-300 mb-2">
                                        <i class="fas fa-calendar-day mr-2"></i>End Date
                                    </label>
                                    <input type="date" id="end_date" name="end_date" required class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-slate-300 mb-2">
                                        <i class="fas fa-calculator mr-2"></i>Total Days
                                    </label>
                                    <input type="text" id="total_days" readonly class="w-full bg-slate-600 border border-slate-600 rounded-xl px-4 py-3 text-slate-400">
                                </div>
                            </div>
                            
                            <div>
                                <label for="reason" class="block text-sm font-semibold text-slate-300 mb-2">
                                    <i class="fas fa-comment-alt mr-2"></i>Reason for Leave
                                </label>
                                <textarea id="reason" name="reason" rows="4" placeholder="Please provide a detailed reason for your leave request..." required class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"></textarea>
                            </div>
                            
                            <div class="flex gap-4 justify-end pt-6">
                                <a href="dashboard.php" class="bg-slate-600 hover:bg-slate-500 text-white font-semibold py-3 px-6 rounded-xl transition-colors flex items-center">
                                    <i class="fas fa-arrow-left mr-2"></i>Cancel
                                </a>
                                <button type="submit" class="bg-gradient-to-r from-primary to-accent hover:from-primary/90 hover:to-accent/90 text-white font-semibold py-3 px-6 rounded-xl transition-all duration-300 transform hover:scale-[1.02] hover:shadow-xl flex items-center">
                                    <i class="fas fa-paper-plane mr-2"></i>Submit Leave Application
                                </button>
                            </div>
                    </form>
                </div>
            </div>

                <!-- Quick Actions -->
                <div class="mt-8 flex gap-4 justify-center">
                    <a href="late_leave_application.php" class="bg-yellow-500 hover:bg-yellow-600 text-white font-semibold py-3 px-6 rounded-xl transition-colors flex items-center">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        Submit Late Leave Application
                    </a>
                    <a href="leave_history.php" class="bg-primary/20 hover:bg-primary/30 text-primary border border-primary/30 font-semibold py-3 px-6 rounded-xl transition-colors flex items-center">
                        <i class="fas fa-history mr-2"></i>
                        View Leave History
                    </a>
                </div>
            </div>
        </main>
    </div>
    <script>
        // Calculate total days
        function calculateDays() {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            
            if (startDate && endDate) {
                const start = new Date(startDate);
                const end = new Date(endDate);
                const timeDiff = end.getTime() - start.getTime();
                const daysDiff = Math.ceil(timeDiff / (1000 * 3600 * 24)) + 1;
                
                if (daysDiff > 0) {
                    document.getElementById('total_days').value = daysDiff + ' day' + (daysDiff > 1 ? 's' : '');
                } else {
                    document.getElementById('total_days').value = '';
                }
            } else {
                document.getElementById('total_days').value = '';
            }
        }
        
        // Add event listeners
        document.getElementById('start_date').addEventListener('change', calculateDays);
        document.getElementById('end_date').addEventListener('change', calculateDays);
        
        // Set minimum date to today
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('start_date').setAttribute('min', today);
        document.getElementById('end_date').setAttribute('min', today);
    </script>
</body>
</html>
