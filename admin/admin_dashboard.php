<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/index.php');
    exit();
}

// Fetch admin details
$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ? AND role = 'admin'");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch();

// Fetch statistics
$stmt = $pdo->query("SELECT COUNT(*) FROM employees");
$total_employees = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'pending'");
$pending_requests = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'approved'");
$approved_requests = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'rejected'");
$rejected_requests = $stmt->fetchColumn();

// Fetch recent leave requests
$stmt = $pdo->prepare("
    SELECT lr.*, e.name as employee_name 
    FROM leave_requests lr 
    JOIN employees e ON lr.employee_id = e.id 
    ORDER BY lr.created_at DESC 
    LIMIT 10
");
$stmt->execute();
$recent_requests = $stmt->fetchAll();

// Fetch all leave requests for calendar
$stmt = $pdo->prepare("
    SELECT lr.*, e.name as employee_name 
    FROM leave_requests lr 
    JOIN employees e ON lr.employee_id = e.id 
    ORDER BY lr.start_date ASC
");
$stmt->execute();
$leave_requests = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ELMS - Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                    },
                    animation: {
                        'bounce-slow': 'bounce 2s infinite',
                        'pulse-slow': 'pulse 3s infinite',
                        'float': 'float 6s ease-in-out infinite',
                        'slide-up': 'slideUp 0.5s ease-out',
                        'fade-in': 'fadeIn 0.6s ease-out'
                    },
                    keyframes: {
                        float: {
                            '0%, 100%': { transform: 'translateY(0px)' },
                            '50%': { transform: 'translateY(-20px)' }
                        },
                        slideUp: {
                            '0%': { transform: 'translateY(20px)', opacity: '0' },
                            '100%': { transform: 'translateY(0)', opacity: '1' }
                        },
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' }
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-900 text-white">
    <!-- Top Navigation Bar -->
    <nav class="bg-slate-800 border-b border-slate-700 fixed top-0 left-0 right-0 z-50 h-16">
        <div class="px-4 md:px-6 py-4 h-full">
            <div class="flex items-center justify-between h-full">
                <!-- Mobile Menu Button -->
                <button class="md:hidden text-slate-400 hover:text-white transition-colors" onclick="toggleSidebar()">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                
                <!-- Logo and Title -->
                <div class="flex items-center space-x-4">
                    <div class="flex items-center space-x-2">
                        <div class="w-8 h-8 bg-gradient-to-r from-primary to-accent rounded-lg flex items-center justify-center">
                            <i class="fas fa-user-shield text-white text-sm"></i>
                        </div>
                        <span class="text-lg md:text-xl font-bold text-white">ELMS Admin</span>
                    </div>
                </div>
                
                <!-- Search Bar -->
                <div class="hidden md:flex flex-1 max-w-md mx-8">
                    <div class="relative w-full">
                        <input type="text" 
                               placeholder="Search..." 
                               class="w-full bg-slate-700 border border-slate-600 rounded-lg px-4 py-2 pl-10 text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-slate-400"></i>
                    </div>
                </div>
                
                <!-- User Menu -->
                <div class="flex items-center space-x-2 md:space-x-4">
                    <!-- Mobile Search Button -->
                    <button class="md:hidden p-2 text-slate-400 hover:text-white transition-colors" onclick="toggleSearch()">
                        <i class="fas fa-search text-lg"></i>
                    </button>
                    
                    <!-- Notifications -->
                    <button class="relative p-2 text-slate-400 hover:text-white transition-colors" onclick="toggleNotifications()">
                        <i class="fas fa-bell text-lg"></i>
                        <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center" id="admin-notification-badge" style="display: none;">0</span>
                    </button>
                    
                    <!-- User Dropdown -->
                    <div class="relative">
                        <button class="flex items-center space-x-2 text-slate-300 hover:text-white transition-colors" onclick="toggleUserMenu()">
                            <div class="w-8 h-8 bg-primary rounded-full flex items-center justify-center">
                                <span class="text-sm font-medium"><?php echo strtoupper(substr($admin['name'], 0, 2)); ?></span>
                            </div>
                            <span class="hidden lg:block"><?php echo htmlspecialchars($admin['name']); ?></span>
                            <i class="fas fa-chevron-down text-xs hidden md:block"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="flex">
        <!-- Left Sidebar -->
        <aside id="sidebar" class="fixed left-0 top-16 h-screen w-64 bg-slate-800 border-r border-slate-700 overflow-y-auto z-40 transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out">
            <nav class="p-4 space-y-2">
                <!-- Active Navigation Item -->
                <a href="admin_dashboard.php" class="flex items-center space-x-3 px-4 py-3 text-white bg-primary/20 rounded-lg border border-primary/30">
                    <i class="fas fa-tachometer-alt w-5"></i>
                    <span>Dashboard</span>
                </a>
                
                <!-- Section Headers -->
                <div class="space-y-1">
                    <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider px-4 py-2">Management</h3>
                    
                    <!-- Navigation Items -->
                    <a href="manage_user.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                        <i class="fas fa-users-cog w-5"></i>
                        <span>Manage Users</span>
                    </a>
                    
                    <a href="leave_management.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                        <i class="fas fa-calendar-check w-5"></i>
                        <span>Leave Management</span>
                        <span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full" id="pendingLeaveBadge" style="display: none;">0</span>
                    </a>
                    
                    <a href="leave_alerts.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                        <i class="fas fa-bell w-5"></i>
                        <span>Leave Alerts</span>
                    </a>
                </div>
                
                <div class="space-y-1">
                    <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider px-4 py-2">Reports</h3>
                    
                    <a href="view_chart.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                        <i class="fas fa-calendar w-5"></i>
                        <span>Leave Chart</span>
                    </a>
                    
                    <a href="reports.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                        <i class="fas fa-file-alt w-5"></i>
                        <span>Reports</span>
                    </a>
                </div>
                
                <div class="pt-4 border-t border-slate-700">
                    <a href="../auth/logout.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                        <i class="fas fa-sign-out-alt w-5"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <main class="flex-1 md:ml-64 p-4 md:p-6">
            <div class="max-w-7xl mx-auto">
                <!-- Welcome Section -->
                <div class="mb-6">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 bg-slate-800 rounded-xl flex items-center justify-center border border-slate-700">
                                <i class="fas fa-user-shield text-xl text-primary"></i>
                            </div>
                            <div>
                                <h1 class="text-3xl font-bold text-white m-0">Welcome, <?php echo htmlspecialchars($admin['name']); ?>!</h1>
                                <p class="text-slate-400 m-0 flex items-center">
                                    <i class="fas fa-calendar-alt mr-2"></i>
                                    Today is <?php echo date('l, F j, Y'); ?> • <?php echo date('H:i A'); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-6 md:mb-8">
                    <!-- Total Users Card -->
                    <div class="bg-slate-800 rounded-lg p-6 border border-slate-700 hover:border-slate-600 transition-all duration-200 hover:scale-[1.02]">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <p class="text-slate-400 text-sm font-semibold uppercase tracking-wider">Total Users</p>
                                <h2 class="text-4xl font-bold text-white mt-2"><?php echo $total_employees; ?></h2>
                            </div>
                            <div class="w-12 h-12 bg-primary/20 rounded-lg flex items-center justify-center">
                                <i class="fas fa-users text-primary text-xl"></i>
                            </div>
                        </div>
                        <div class="flex items-center gap-1 text-green-400 text-sm font-medium">
                            <i class="fas fa-arrow-up"></i>
                            <span>Active users</span>
                        </div>
                    </div>
                    
                    <!-- Pending Requests Card -->
                    <div class="bg-slate-800 rounded-lg p-6 border border-slate-700 hover:border-slate-600 transition-all duration-200 hover:scale-[1.02]">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <p class="text-slate-400 text-sm font-semibold uppercase tracking-wider">Pending Requests</p>
                                <h2 class="text-4xl font-bold text-white mt-2"><?php echo $pending_requests; ?></h2>
                            </div>
                            <div class="w-12 h-12 bg-yellow-500/20 rounded-lg flex items-center justify-center">
                                <i class="fas fa-clock text-yellow-500 text-xl"></i>
                            </div>
                        </div>
                        <div class="flex items-center gap-1 text-slate-400 text-sm font-medium">
                            <i class="fas fa-hourglass-half"></i>
                            <span>Awaiting review</span>
                        </div>
                    </div>
                    
                    <!-- Approved Requests Card -->
                    <div class="bg-slate-800 rounded-lg p-6 border border-slate-700 hover:border-slate-600 transition-all duration-200 hover:scale-[1.02]">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <p class="text-slate-400 text-sm font-semibold uppercase tracking-wider">Approved Requests</p>
                                <h2 class="text-4xl font-bold text-white mt-2"><?php echo $approved_requests; ?></h2>
                            </div>
                            <div class="w-12 h-12 bg-green-500/20 rounded-lg flex items-center justify-center">
                                <i class="fas fa-check-circle text-green-500 text-xl"></i>
                            </div>
                        </div>
                        <div class="flex items-center gap-1 text-green-400 text-sm font-medium">
                            <i class="fas fa-arrow-up"></i>
                            <span>This month</span>
                        </div>
                    </div>
                    
                    <!-- Rejected Requests Card -->
                    <div class="bg-slate-800 rounded-lg p-6 border border-slate-700 hover:border-slate-600 transition-all duration-200 hover:scale-[1.02]">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <p class="text-slate-400 text-sm font-semibold uppercase tracking-wider">Rejected Requests</p>
                                <h2 class="text-4xl font-bold text-white mt-2"><?php echo $rejected_requests; ?></h2>
                            </div>
                            <div class="w-12 h-12 bg-red-500/20 rounded-lg flex items-center justify-center">
                                <i class="fas fa-times-circle text-red-500 text-xl"></i>
                            </div>
                        </div>
                        <div class="flex items-center gap-1 text-red-400 text-sm font-medium">
                            <i class="fas fa-arrow-down"></i>
                            <span>This month</span>
                        </div>
                    </div>
                </div>

                <!-- Recent Leave Requests Table -->
                <div class="bg-slate-800 rounded-lg border border-slate-700 overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-700 bg-slate-700">
                        <h3 class="text-xl font-semibold text-white m-0 flex items-center gap-3">
                            <i class="fas fa-list text-primary"></i>
                            Recent Leave Requests
                        </h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[800px]">
                            <thead class="bg-slate-700">
                                <tr>
                                    <th class="px-3 md:px-6 py-4 text-left text-xs font-semibold text-slate-300 uppercase tracking-wider">Employee</th>
                                    <th class="px-3 md:px-6 py-4 text-left text-xs font-semibold text-slate-300 uppercase tracking-wider hidden sm:table-cell">Type</th>
                                    <th class="px-3 md:px-6 py-4 text-left text-xs font-semibold text-slate-300 uppercase tracking-wider hidden md:table-cell">Start Date</th>
                                    <th class="px-3 md:px-6 py-4 text-left text-xs font-semibold text-slate-300 uppercase tracking-wider hidden md:table-cell">End Date</th>
                                    <th class="px-3 md:px-6 py-4 text-left text-xs font-semibold text-slate-300 uppercase tracking-wider">
                                        <div class="flex items-center gap-2">
                                            <span>Status</span>
                                            <button type="button" 
                                                    onclick="showStatusInfoHelp()"
                                                    title="View Status Information"
                                                    class="text-blue-400 hover:text-blue-300 transition-colors">
                                                <i class="fas fa-info-circle text-xs"></i>
                                            </button>
                                        </div>
                                    </th>
                                    <th class="px-3 md:px-6 py-4 text-left text-xs font-semibold text-slate-300 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-700">
                                <?php foreach ($recent_requests as $request): ?>
                                <tr class="hover:bg-slate-700/50 transition-colors">
                                    <td class="px-3 md:px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 md:w-10 md:h-10 bg-primary rounded-full flex items-center justify-center text-white">
                                                <i class="fas fa-user text-xs md:text-sm"></i>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <div class="font-medium text-white text-sm truncate"><?php echo htmlspecialchars($request['employee_name']); ?></div>
                                                <small class="text-slate-400 text-xs">ID: #<?php echo $request['employee_id']; ?></small>
                                                <!-- Mobile: Show additional info -->
                                                <div class="sm:hidden mt-1">
                                                    <span class="bg-blue-500/20 text-blue-400 px-2 py-1 rounded-full text-xs font-semibold uppercase tracking-wide">
                                                        <?php echo ucfirst($request['leave_type']); ?>
                                                    </span>
                                                    <div class="text-slate-400 text-xs mt-1">
                                                        <?php echo date('M d', strtotime($request['start_date'])); ?> - <?php echo date('M d', strtotime($request['end_date'])); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 md:px-6 py-4 hidden sm:table-cell">
                                        <span class="bg-blue-500/20 text-blue-400 px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wide">
                                            <?php echo ucfirst($request['leave_type']); ?>
                                        </span>
                                    </td>
                                    <td class="px-3 md:px-6 py-4 text-slate-300 text-sm hidden md:table-cell"><?php echo date('M d, Y', strtotime($request['start_date'])); ?></td>
                                    <td class="px-3 md:px-6 py-4 text-slate-300 text-sm hidden md:table-cell"><?php echo date('M d, Y', strtotime($request['end_date'])); ?></td>
                                    <td class="px-3 md:px-6 py-4">
                                        <span class="px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wide <?php 
                                            echo $request['status'] == 'approved' ? 'bg-green-500/20 text-green-400' : 
                                                ($request['status'] == 'pending' ? 'bg-yellow-500/20 text-yellow-400' : 'bg-red-500/20 text-red-400'); 
                                        ?>">
                                            <?php echo ucfirst($request['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-3 md:px-6 py-4">
                                        <div class="flex gap-1 md:gap-2">
                                            <button onclick="viewRequestDetails(<?php echo $request['id']; ?>)" 
                                                    title="View Details"
                                                    class="bg-primary hover:bg-primary/90 text-white p-1.5 md:p-2 rounded-lg transition-colors">
                                                <i class="fas fa-eye text-xs"></i>
                                            </button>
                                            <a href="leave_management.php" 
                                               title="Manage Leave Requests"
                                               class="bg-slate-600 hover:bg-slate-500 text-white p-1.5 md:p-2 rounded-lg transition-colors inline-flex items-center">
                                                <i class="fas fa-cog text-xs"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Mobile Search Overlay -->
    <div id="mobileSearchOverlay" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
        <div class="bg-slate-800 p-4">
            <div class="flex items-center space-x-4">
                <div class="flex-1 relative">
                    <input type="text" 
                           placeholder="Search..." 
                           class="w-full bg-slate-700 border border-slate-600 rounded-lg px-4 py-3 pl-10 text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-slate-400"></i>
                </div>
                <button onclick="toggleSearch()" class="text-slate-400 hover:text-white">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
    </div>

    <script>
        // Toggle functions for navigation
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('-translate-x-full');
        }

        function toggleSearch() {
            const searchOverlay = document.getElementById('mobileSearchOverlay');
            searchOverlay.classList.toggle('hidden');
        }

        function toggleNotifications() {
            // Implementation for notification dropdown
            console.log('Toggle notifications');
        }

        function toggleUserMenu() {
            // Implementation for user menu dropdown
            console.log('Toggle user menu');
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const sidebarButton = event.target.closest('[onclick="toggleSidebar()"]');
            
            if (window.innerWidth < 768 && !sidebar.contains(event.target) && !sidebarButton) {
                sidebar.classList.add('-translate-x-full');
            }
        });

        // Handle window resize
        window.addEventListener('resize', function() {
            const sidebar = document.getElementById('sidebar');
            if (window.innerWidth >= 768) {
                sidebar.classList.remove('-translate-x-full');
            } else {
                sidebar.classList.add('-translate-x-full');
            }
        });

        function viewRequestDetails(leaveId) {
            // Create modal to show detailed information
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
            modal.id = 'requestModal';
            modal.innerHTML = `
                <div class="bg-slate-800 rounded-lg p-6 w-full max-w-2xl mx-4 max-h-screen overflow-y-auto">
                    <div class="flex items-center justify-between mb-4">
                        <h5 class="text-xl font-semibold text-white">Leave Request Details</h5>
                        <button type="button" class="text-slate-400 hover:text-white" onclick="closeModal()">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                    <div class="text-center py-8">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto"></div>
                        <p class="mt-2 text-slate-400">Loading request details...</p>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            
            // Fetch request details
            fetch('get_request_details.php?id=' + leaveId)
                .then(response => response.json())
                .then(data => {
                    modal.querySelector('.text-center').innerHTML = `
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <h6 class="text-lg font-semibold text-white mb-3">Employee Information</h6>
                                <div class="space-y-2">
                                    <p class="text-slate-300"><strong class="text-white">Name:</strong> ${data.employee_name}</p>
                                    <p class="text-slate-300"><strong class="text-white">Department:</strong> ${data.department}</p>
                                    <p class="text-slate-300"><strong class="text-white">Email:</strong> ${data.employee_email}</p>
                                </div>
                            </div>
                            <div>
                                <h6 class="text-lg font-semibold text-white mb-3">Leave Details</h6>
                                <div class="space-y-2">
                                    <p class="text-slate-300"><strong class="text-white">Type:</strong> ${data.leave_type}</p>
                                    <p class="text-slate-300"><strong class="text-white">Start Date:</strong> ${data.start_date}</p>
                                    <p class="text-slate-300"><strong class="text-white">End Date:</strong> ${data.end_date}</p>
                                    <p class="text-slate-300"><strong class="text-white">Status:</strong> 
                                        <span class="px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wide ${data.status === 'approved' ? 'bg-green-500/20 text-green-400' : (data.status === 'pending' ? 'bg-yellow-500/20 text-yellow-400' : 'bg-red-500/20 text-red-400')}">${data.status}</span>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="mt-6">
                            <h6 class="text-lg font-semibold text-white mb-3">Reason</h6>
                            <p class="text-slate-300 bg-slate-700 p-4 rounded-lg">${data.reason}</p>
                        </div>
                        <div class="mt-6 flex justify-end">
                            <button onclick="closeModal()" class="bg-slate-600 hover:bg-slate-500 text-white px-6 py-2 rounded-lg transition-colors">
                                Close
                            </button>
                        </div>
                    `;
                })
                .catch(error => {
                    modal.querySelector('.text-center').innerHTML = `
                        <div class="bg-red-500/20 border border-red-500/30 rounded-lg p-4">
                            <div class="flex items-center">
                                <i class="fas fa-exclamation-triangle text-red-400 mr-2"></i>
                                <span class="text-red-400">Error loading request details: ${error}</span>
                            </div>
                        </div>
                    `;
                });
        }

        function closeModal() {
            const modal = document.getElementById('requestModal');
            if (modal) {
                modal.remove();
            }
        }

        function showNotification(message, type) {
            const bgColor = type === 'success' ? 'bg-green-500/20 border-green-500/30' : 'bg-red-500/20 border-red-500/30';
            const textColor = type === 'success' ? 'text-green-400' : 'text-red-400';
            const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle';
            
            const notification = document.createElement('div');
            notification.className = `fixed top-5 right-5 z-50 min-w-80 max-w-md ${bgColor} border rounded-lg p-4 shadow-lg`;
            notification.innerHTML = `
                <div class="flex items-center">
                    <i class="fas ${icon} ${textColor} mr-3"></i>
                    <span class="${textColor} flex-1">${message}</span>
                    <button onclick="this.parentElement.parentElement.remove()" class="text-slate-400 hover:text-white ml-2">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            document.body.appendChild(notification);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 5000);
        }

        // Show status information help modal
        function showStatusInfoHelp() {
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
            modal.id = 'statusInfoHelpModal';
            modal.innerHTML = `
                <div class="bg-slate-800 rounded-lg p-6 w-full max-w-4xl mx-4 max-h-screen overflow-y-auto">
                    <div class="flex items-center justify-between mb-6">
                        <h5 class="text-2xl font-semibold text-white flex items-center">
                            <i class="fas fa-info-circle text-primary mr-3"></i>Status Information Guide
                        </h5>
                        <button type="button" class="text-slate-400 hover:text-white" onclick="closeStatusModal()">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                    
                    <div class="bg-blue-500/20 border border-blue-500/30 rounded-lg p-4 mb-6">
                        <h6 class="text-lg font-semibold text-blue-400 flex items-center mb-2">
                            <i class="fas fa-lightbulb mr-2"></i>Understanding Leave Request Status
                        </h6>
                        <p class="text-slate-300">This table shows the current status of leave requests in the system. Here's what each status means:</p>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <h6 class="text-yellow-400 text-lg font-semibold mb-3 flex items-center">
                                <i class="fas fa-clock mr-2"></i>Pending Status
                            </h6>
                            <div class="space-y-2 text-slate-300">
                                <p><strong class="text-white">Meaning:</strong> Leave request is waiting for approval</p>
                                <p><strong class="text-white">What you can do:</strong> Go to Leave Management to approve/reject</p>
                                <p><strong class="text-white">Next step:</strong> Use the settings button to access management</p>
                            </div>
                        </div>
                        <div>
                            <h6 class="text-green-400 text-lg font-semibold mb-3 flex items-center">
                                <i class="fas fa-check-circle mr-2"></i>Approved Status
                            </h6>
                            <div class="space-y-2 text-slate-300">
                                <p><strong class="text-white">Meaning:</strong> Leave request has been approved</p>
                                <p><strong class="text-white">What this means:</strong> Employee can take the leave</p>
                                <p><strong class="text-white">Note:</strong> Leave balance will be deducted</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="border-t border-slate-700 pt-6 mb-6"></div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <h6 class="text-red-400 text-lg font-semibold mb-3 flex items-center">
                                <i class="fas fa-times-circle mr-2"></i>Rejected Status
                            </h6>
                            <div class="space-y-2 text-slate-300">
                                <p><strong class="text-white">Meaning:</strong> Leave request has been rejected</p>
                                <p><strong class="text-white">What this means:</strong> Employee cannot take the leave</p>
                                <p><strong class="text-white">Note:</strong> Employee will be notified</p>
                            </div>
                        </div>
                        <div>
                            <h6 class="text-primary text-lg font-semibold mb-3 flex items-center">
                                <i class="fas fa-cog mr-2"></i>Leave Management
                            </h6>
                            <div class="space-y-2 text-slate-300">
                                <p><strong class="text-white">Purpose:</strong> Access full leave management system</p>
                                <p><strong class="text-white">Includes:</strong> Approve, reject, and manage all requests</p>
                                <p><strong class="text-white">Action:</strong> Click the settings button</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-yellow-500/20 border border-yellow-500/30 rounded-lg p-4 mb-6">
                        <h6 class="text-lg font-semibold text-yellow-400 flex items-center mb-3">
                            <i class="fas fa-exclamation-triangle mr-2"></i>Important Notes
                        </h6>
                        <ul class="text-slate-300 space-y-1">
                            <li>• Use the <strong class="text-white">Leave Management</strong> page to approve or reject requests</li>
                            <li>• This dashboard shows a summary view only</li>
                            <li>• All approval actions must be done in the management section</li>
                            <li>• All actions are logged for audit purposes</li>
                        </ul>
                    </div>
                    
                    <div class="flex justify-end">
                        <button onclick="closeStatusModal()" class="bg-slate-600 hover:bg-slate-500 text-white px-6 py-2 rounded-lg transition-colors">
                            Close
                        </button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }

        function closeStatusModal() {
            const modal = document.getElementById('statusInfoHelpModal');
            if (modal) {
                modal.remove();
            }
        }

        // Add click handlers for sidebar navigation
        document.addEventListener('DOMContentLoaded', function() {
            // Add active class to current page
            const currentPage = window.location.pathname.split('/').pop();
            const sidebarLinks = document.querySelectorAll('aside nav a');
            sidebarLinks.forEach(link => {
                if (link.getAttribute('href') === currentPage) {
                    link.classList.remove('text-slate-300', 'hover:text-white', 'hover:bg-slate-700');
                    link.classList.add('text-white', 'bg-primary/20', 'border', 'border-primary/30');
                }
            });
        });

        // Function to fetch pending leave count
        function fetchPendingLeaveCount() {
            fetch('api/get_pending_leave_count.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const badge = document.getElementById('pendingLeaveBadge');
                        if (data.count > 0) {
                            badge.textContent = data.count;
                            badge.style.display = 'inline-block';
                        } else {
                            badge.style.display = 'none';
                        }
                    }
                })
                .catch(error => {
                    console.error('Error fetching pending leave count:', error);
                });
        }

        // Fetch pending leave count on page load
        fetchPendingLeaveCount();

        // Update pending leave count every 30 seconds
        setInterval(fetchPendingLeaveCount, 30000);
    </script>
</body>
</html> 