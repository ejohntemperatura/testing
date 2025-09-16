<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/index.php');
    exit();
}

// Get leave history
$stmt = $pdo->prepare("
    SELECT lr.*, 
           lr.leave_type as display_leave_type,
           lr.is_late,
           lr.late_justification
    FROM leave_requests lr 
    WHERE lr.employee_id = ? 
    ORDER BY lr.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$leave_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave History - ELMS</title>
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
                
                <!-- Active Navigation Item -->
                <a href="leave_history.php" class="flex items-center space-x-3 px-4 py-3 text-white bg-primary/20 rounded-lg border border-primary/30">
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
                <!-- Page Header -->
                <div class="mb-8">
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 bg-gradient-to-r from-primary to-accent rounded-2xl flex items-center justify-center">
                            <i class="fas fa-history text-2xl text-white"></i>
                        </div>
                        <div>
                            <h1 class="text-3xl font-bold text-white mb-2">Leave History</h1>
                            <p class="text-slate-400">View all your leave requests and their status</p>
                        </div>
                    </div>
                </div>
                
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="bg-green-500/20 border border-green-500/30 text-green-400 p-4 rounded-xl mb-6 flex items-center">
                        <i class="fas fa-check-circle mr-3"></i>
                        <?php 
                        echo $_SESSION['success'];
                        unset($_SESSION['success']);
                        ?>
                    </div>
                <?php endif; ?>

                <!-- Leave History Table -->
                <div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl border border-slate-700/50 overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-700/50 bg-slate-700/30">
                        <h3 class="text-xl font-semibold text-white flex items-center">
                            <i class="fas fa-list text-primary mr-3"></i>
                            Your Leave Requests
                        </h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-slate-700/30">
                                <tr>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-300 uppercase tracking-wider">Leave Type</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-300 uppercase tracking-wider">Start Date</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-300 uppercase tracking-wider">End Date</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-300 uppercase tracking-wider">Reason</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-300 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-300 uppercase tracking-wider">Applied On</th>
                                    <th class="px-6 py-4 text-center text-xs font-semibold text-slate-300 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-700/50">
                                <?php foreach ($leave_requests as $request): ?>
                                    <tr class="hover:bg-slate-700/30 transition-colors">
                                        <td class="px-6 py-4">
                                            <div class="flex flex-col gap-2">
                                                <span class="bg-primary/20 text-primary px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wide">
                                                    <?php echo ucfirst(str_replace('_', ' ', $request['display_leave_type'])); ?>
                                                </span>
                                                <?php if ($request['is_late']): ?>
                                                    <span class="bg-yellow-500/20 text-yellow-400 px-2 py-1 rounded-full text-xs font-semibold uppercase tracking-wide inline-block w-fit">
                                                        <i class="fas fa-exclamation-triangle mr-1"></i>Late Application
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-slate-300 text-sm"><?php echo date('M d, Y', strtotime($request['start_date'])); ?></td>
                                        <td class="px-6 py-4 text-slate-300 text-sm"><?php echo date('M d, Y', strtotime($request['end_date'])); ?></td>
                                        <td class="px-6 py-4 text-slate-300 text-sm max-w-xs truncate" title="<?php echo htmlspecialchars($request['reason']); ?>">
                                            <?php echo htmlspecialchars($request['reason']); ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wide <?php 
                                                echo $request['status'] === 'approved' ? 'bg-green-500/20 text-green-400' : 
                                                    ($request['status'] === 'rejected' ? 'bg-red-500/20 text-red-400' : 'bg-yellow-500/20 text-yellow-400'); 
                                            ?>">
                                                <?php echo ucfirst($request['status']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-slate-300 text-sm"><?php echo date('M d, Y', strtotime($request['created_at'])); ?></td>
                                        <td class="px-6 py-4 text-center">
                                            <button onclick="viewLeaveDetails(<?php echo $request['id']; ?>)" 
                                                    class="bg-primary hover:bg-primary/90 text-white p-2 rounded-lg transition-colors">
                                                <i class="fas fa-eye text-xs"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($leave_requests)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-12">
                                            <i class="fas fa-inbox text-4xl text-slate-500 mb-4"></i>
                                            <p class="text-slate-400 text-lg">No leave requests found</p>
                                            <p class="text-slate-500 text-sm">Start by applying for your first leave request</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Leave Details Modal -->
    <div id="leaveDetailsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-slate-800 rounded-2xl p-8 w-full max-w-2xl mx-4 max-h-screen overflow-y-auto border border-slate-700">
            <div class="flex items-center justify-between mb-6">
                <h5 class="text-2xl font-bold text-white flex items-center">
                    <i class="fas fa-eye text-primary mr-3"></i>Leave Request Details
                </h5>
                <button type="button" onclick="closeLeaveDetailsModal()" class="text-slate-400 hover:text-white transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div id="leaveDetailsContent" class="text-slate-300">
                <!-- Content will be loaded here -->
            </div>
            <div class="flex justify-end pt-6">
                <button type="button" onclick="closeLeaveDetailsModal()" class="bg-slate-600 hover:bg-slate-500 text-white font-semibold py-3 px-6 rounded-xl transition-colors">
                    Close
                </button>
            </div>
        </div>
    </div>

    <script>
        function openLeaveDetailsModal() {
            document.getElementById('leaveDetailsModal').classList.remove('hidden');
            document.getElementById('leaveDetailsModal').classList.add('flex');
        }

        function closeLeaveDetailsModal() {
            document.getElementById('leaveDetailsModal').classList.add('hidden');
            document.getElementById('leaveDetailsModal').classList.remove('flex');
        }

        function viewLeaveDetails(leaveId) {
            // Fetch leave details via AJAX
            fetch(`get_leave_details.php?id=${leaveId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const leave = data.leave;
                        const modalContent = document.getElementById('leaveDetailsContent');
                        
                        modalContent.innerHTML = `
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <h6 class="text-slate-400 mb-2 font-semibold">Leave Type</h6>
                                    <p class="mb-3">
                                        <span class="bg-primary/20 text-primary px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wide">${leave.leave_type.charAt(0).toUpperCase() + leave.leave_type.slice(1).replace('_', ' ')}</span>
                                        ${leave.is_late ? '<span class="bg-yellow-500/20 text-yellow-400 px-2 py-1 rounded-full text-xs font-semibold uppercase tracking-wide ml-2">Late Application</span>' : ''}
                                    </p>
                                    
                                    <h6 class="text-slate-400 mb-2 font-semibold">Start Date</h6>
                                    <p class="mb-3 text-white">${new Date(leave.start_date).toLocaleDateString('en-US', { 
                                        year: 'numeric', 
                                        month: 'long', 
                                        day: 'numeric' 
                                    })}</p>
                                    
                                    <h6 class="text-slate-400 mb-2 font-semibold">End Date</h6>
                                    <p class="mb-3 text-white">${new Date(leave.end_date).toLocaleDateString('en-US', { 
                                        year: 'numeric', 
                                        month: 'long', 
                                        day: 'numeric' 
                                    })}</p>
                                </div>
                                <div>
                                    <h6 class="text-slate-400 mb-2 font-semibold">Status</h6>
                                    <p class="mb-3">
                                        <span class="px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wide ${leave.status === 'approved' ? 'bg-green-500/20 text-green-400' : 
                                            (leave.status === 'rejected' ? 'bg-red-500/20 text-red-400' : 'bg-yellow-500/20 text-yellow-400')}">
                                            ${leave.status.charAt(0).toUpperCase() + leave.status.slice(1)}
                                        </span>
                                    </p>
                                    
                                    <h6 class="text-slate-400 mb-2 font-semibold">Applied On</h6>
                                    <p class="mb-3 text-white">${new Date(leave.created_at).toLocaleDateString('en-US', { 
                                        year: 'numeric', 
                                        month: 'long', 
                                        day: 'numeric' 
                                    })}</p>
                                </div>
                            </div>
                            <div class="mt-6">
                                <h6 class="text-slate-400 mb-2 font-semibold">Reason</h6>
                                <p class="text-white bg-slate-700/50 p-4 rounded-lg">${leave.reason}</p>
                            </div>
                        `;
                        
                        openLeaveDetailsModal();
                    } else {
                        alert('Error loading leave details: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading leave details');
                });
        }
    </script>
</body>
</html> 