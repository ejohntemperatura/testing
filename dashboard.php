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
                        <span class="text-xl font-bold text-white">ELMS</span>
                    </div>
                </div>
                
                <!-- User Menu -->
                <div class="flex items-center space-x-4">
                    <a href="logout.php" class="text-slate-300 hover:text-white transition-colors flex items-center space-x-2">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main class="pt-16 min-h-screen">
        <div class="max-w-7xl mx-auto p-6">
            <!-- Welcome Section -->
            <div class="mb-8">
                <div class="flex items-center gap-4 p-6 bg-slate-800/50 backdrop-blur-sm rounded-2xl border border-slate-700/50">
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
            </div>

            <!-- Statistics and Actions Row -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                <!-- Leave Balance Card -->
                <div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-6 border border-slate-700/50 hover:border-slate-600/50 transition-all duration-300">
                    <div class="flex items-center justify-between mb-6">
                        <div class="w-12 h-12 bg-gradient-to-r from-primary to-accent rounded-xl flex items-center justify-center">
                            <i class="fas fa-calendar-check text-white text-xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-white">Leave Balance</h3>
                    </div>
                    
                    <div class="space-y-4 mb-6">
                        <div class="flex justify-between items-center p-3 bg-slate-700/30 rounded-lg">
                            <span class="text-slate-300">Annual Leave</span>
                            <span class="text-2xl font-bold text-white"><?php echo $employee['annual_leave_balance']; ?> days</span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-slate-700/30 rounded-lg">
                            <span class="text-slate-300">Sick Leave</span>
                            <span class="text-2xl font-bold text-white"><?php echo $employee['sick_leave_balance']; ?> days</span>
                        </div>
                    </div>
                    
                    <button onclick="openLeaveModal()" class="w-full bg-gradient-to-r from-primary to-accent hover:from-primary/90 hover:to-accent/90 text-white font-semibold py-3 px-6 rounded-xl transition-all duration-300 transform hover:scale-[1.02] hover:shadow-xl">
                        <i class="fas fa-plus mr-2"></i>Request Leave
                    </button>
                </div>
                
                <!-- Recent Leave Requests Table -->
                <div class="lg:col-span-2 bg-slate-800/50 backdrop-blur-sm rounded-2xl border border-slate-700/50 overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-700/50 bg-slate-700/30">
                        <h3 class="text-xl font-semibold text-white flex items-center">
                            <i class="fas fa-list-alt text-primary mr-3"></i>
                            Recent Leave Requests
                        </h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-slate-700/30">
                                <tr>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-300 uppercase tracking-wider">Type</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-300 uppercase tracking-wider">Start Date</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-300 uppercase tracking-wider">End Date</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-300 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-300 uppercase tracking-wider">Reason</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-700/50">
                                <?php foreach ($leave_requests as $request): ?>
                                <tr class="hover:bg-slate-700/30 transition-colors">
                                    <td class="px-6 py-4">
                                        <span class="bg-primary/20 text-primary px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wide">
                                            <?php echo ucfirst($request['leave_type']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-slate-300 text-sm"><?php echo date('M d, Y', strtotime($request['start_date'])); ?></td>
                                    <td class="px-6 py-4 text-slate-300 text-sm"><?php echo date('M d, Y', strtotime($request['end_date'])); ?></td>
                                    <td class="px-6 py-4">
                                        <span class="px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wide <?php 
                                            echo $request['status'] == 'approved' ? 'bg-green-500/20 text-green-400' : 
                                                ($request['status'] == 'pending' ? 'bg-yellow-500/20 text-yellow-400' : 'bg-red-500/20 text-red-400'); 
                                        ?>">
                                            <?php echo ucfirst($request['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-slate-300 text-sm max-w-xs truncate" title="<?php echo htmlspecialchars($request['reason']); ?>">
                                        <?php echo htmlspecialchars($request['reason']); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($leave_requests)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-12">
                                        <i class="fas fa-inbox text-4xl text-slate-500 mb-4"></i>
                                        <p class="text-slate-400">No recent leave requests.</p>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
    </div>

    <!-- Leave Request Modal -->
    <div id="leaveRequestModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-slate-800 rounded-2xl p-8 w-full max-w-2xl mx-4 max-h-screen overflow-y-auto border border-slate-700">
            <div class="flex items-center justify-between mb-6">
                <h5 class="text-2xl font-bold text-white flex items-center">
                    <i class="fas fa-calendar-plus text-primary mr-3"></i>Request Leave
                </h5>
                <button type="button" onclick="closeLeaveModal()" class="text-slate-400 hover:text-white transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="leaveRequestForm" action="submit_leave.php" method="POST" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="leaveType" class="block text-sm font-semibold text-slate-300 mb-2">Leave Type</label>
                        <select id="leaveType" name="leave_type" required class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
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
                    <div>
                        <label for="startDate" class="block text-sm font-semibold text-slate-300 mb-2">Start Date</label>
                        <input type="date" id="startDate" name="start_date" required class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="endDate" class="block text-sm font-semibold text-slate-300 mb-2">End Date</label>
                        <input type="date" id="endDate" name="end_date" required class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    <div>
                        <label for="totalDays" class="block text-sm font-semibold text-slate-300 mb-2">Total Days</label>
                        <input type="text" id="totalDays" readonly placeholder="Auto-calculated" class="w-full bg-slate-700/50 border border-slate-600 rounded-xl px-4 py-3 text-slate-400 cursor-not-allowed">
                    </div>
                </div>
                
                <div>
                    <label for="reason" class="block text-sm font-semibold text-slate-300 mb-2">Reason for Leave</label>
                    <textarea id="reason" name="reason" rows="4" placeholder="Please provide a detailed reason for your leave request..." required class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent resize-none"></textarea>
                </div>
                
                <div class="flex justify-end space-x-4 pt-6">
                    <button type="button" onclick="closeLeaveModal()" class="bg-slate-600 hover:bg-slate-500 text-white font-semibold py-3 px-6 rounded-xl transition-colors">
                        <i class="fas fa-times mr-2"></i>Cancel
                    </button>
                    <button type="submit" class="bg-gradient-to-r from-primary to-accent hover:from-primary/90 hover:to-accent/90 text-white font-semibold py-3 px-6 rounded-xl transition-all duration-300 transform hover:scale-[1.02]">
                        <i class="fas fa-paper-plane mr-2"></i>Submit Request
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal functions
        function openLeaveModal() {
            document.getElementById('leaveRequestModal').classList.remove('hidden');
            document.getElementById('leaveRequestModal').classList.add('flex');
        }

        function closeLeaveModal() {
            document.getElementById('leaveRequestModal').classList.add('hidden');
            document.getElementById('leaveRequestModal').classList.remove('flex');
        }

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