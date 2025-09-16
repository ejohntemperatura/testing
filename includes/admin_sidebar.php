<?php
// Admin Sidebar Component
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- Dashboard -->
<a href="admin_dashboard.php" class="flex items-center space-x-3 px-4 py-3 <?php echo $current_page === 'admin_dashboard.php' ? 'text-white bg-primary/20 rounded-lg border border-primary/30' : 'text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors'; ?>">
    <i class="fas fa-tachometer-alt w-5"></i>
    <span>Dashboard</span>
</a>

<!-- Manage Users -->
<a href="manage_user.php" class="flex items-center space-x-3 px-4 py-3 <?php echo $current_page === 'manage_user.php' ? 'text-white bg-primary/20 rounded-lg border border-primary/30' : 'text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors'; ?>">
    <i class="fas fa-users-cog w-5"></i>
    <span>Manage Users</span>
</a>

<!-- Leave Management -->
<a href="leave_management.php" class="flex items-center space-x-3 px-4 py-3 <?php echo $current_page === 'leave_management.php' ? 'text-white bg-primary/20 rounded-lg border border-primary/30' : 'text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors'; ?>">
    <i class="fas fa-calendar-check w-5"></i>
    <span>Leave Management</span>
    <span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full" id="pendingLeaveBadge" style="display: none;">0</span>
</a>

<!-- Leave Alerts -->
<a href="leave_alerts.php" class="flex items-center space-x-3 px-4 py-3 <?php echo $current_page === 'leave_alerts.php' ? 'text-white bg-primary/20 rounded-lg border border-primary/30' : 'text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors'; ?>">
    <i class="fas fa-bell w-5"></i>
    <span>Leave Alerts</span>
</a>

<!-- Leave Chart -->
<a href="view_chart.php" class="flex items-center space-x-3 px-4 py-3 <?php echo $current_page === 'view_chart.php' ? 'text-white bg-primary/20 rounded-lg border border-primary/30' : 'text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors'; ?>">
    <i class="fas fa-calendar w-5"></i>
    <span>Leave Chart</span>
</a>

<!-- Reports -->
<a href="reports.php" class="flex items-center space-x-3 px-4 py-3 <?php echo $current_page === 'reports.php' ? 'text-white bg-primary/20 rounded-lg border border-primary/30' : 'text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors'; ?>">
    <i class="fas fa-file-alt w-5"></i>
    <span>Reports</span>
</a>
