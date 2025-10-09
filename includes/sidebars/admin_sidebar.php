<?php
// Admin Sidebar Component
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- Active Navigation Item (Dashboard) -->
<a href="admin_dashboard.php" class="flex items-center space-x-3 px-4 py-3 <?php echo $current_page === 'admin_dashboard.php' ? 'text-white bg-blue-500/20 rounded-lg border border-blue-500/30' : 'text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors'; ?>">
    <i class="fas fa-tachometer-alt w-5"></i>
    <span>Dashboard</span>
</a>

<!-- Section Headers -->
<div class="space-y-1">
    <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider px-4 py-2">Management</h3>
    
    <!-- Navigation Items -->
    <a href="manage_user.php" class="flex items-center space-x-3 px-4 py-3 <?php echo $current_page === 'manage_user.php' ? 'text-white bg-blue-500/20 rounded-lg border border-blue-500/30' : 'text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors'; ?>">
        <i class="fas fa-users-cog w-5"></i>
        <span>Manage Users</span>
    </a>
    
    <a href="leave_management.php" class="flex items-center space-x-3 px-4 py-3 <?php echo $current_page === 'leave_management.php' ? 'text-white bg-blue-500/20 rounded-lg border border-blue-500/30' : 'text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors'; ?>">
        <i class="fas fa-calendar-check w-5"></i>
        <span>Leave Management</span>
        <span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full" id="pendingLeaveBadge" style="display: none;">0</span>
    </a>
    
    <a href="leave_management.php?status=pending" class="flex items-center space-x-3 px-4 py-3 <?php echo (isset($_GET['status']) && $_GET['status'] === 'pending') ? 'text-white bg-blue-500/20 rounded-lg border border-blue-500/30' : 'text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors'; ?>">
        <i class="fas fa-clock w-5"></i>
        <span>Pending Requests</span>
    </a>
    
    <a href="leave_management.php?status=approved" class="flex items-center space-x-3 px-4 py-3 <?php echo (isset($_GET['status']) && $_GET['status'] === 'approved') ? 'text-white bg-blue-500/20 rounded-lg border border-blue-500/30' : 'text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors'; ?>">
        <i class="fas fa-check-circle w-5"></i>
        <span>Approved Requests</span>
    </a>
    
    <a href="leave_management.php?status=rejected" class="flex items-center space-x-3 px-4 py-3 <?php echo (isset($_GET['status']) && $_GET['status'] === 'rejected') ? 'text-white bg-blue-500/20 rounded-lg border border-blue-500/30' : 'text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors'; ?>">
        <i class="fas fa-times-circle w-5"></i>
        <span>Rejected Requests</span>
    </a>
    
    <a href="leave_alerts.php" class="flex items-center space-x-3 px-4 py-3 <?php echo $current_page === 'leave_alerts.php' ? 'text-white bg-blue-500/20 rounded-lg border border-blue-500/30' : 'text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors'; ?>">
        <i class="fas fa-bell w-5"></i>
        <span>Leave Alerts</span>
    </a>
</div>

<div class="space-y-1">
    <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider px-4 py-2">Reports</h3>
    
    <a href="view_chart.php" class="flex items-center space-x-3 px-4 py-3 <?php echo $current_page === 'view_chart.php' ? 'text-white bg-blue-500/20 rounded-lg border border-blue-500/30' : 'text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors'; ?>">
        <i class="fas fa-chart-line w-5"></i>
        <span>Leave Chart</span>
    </a>
    
    <a href="reports.php" class="flex items-center space-x-3 px-4 py-3 <?php echo $current_page === 'reports.php' ? 'text-white bg-blue-500/20 rounded-lg border border-blue-500/30' : 'text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors'; ?>">
        <i class="fas fa-file-alt w-5"></i>
        <span>Reports</span>
    </a>
</div>
