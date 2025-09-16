<?php
// Director Sidebar Component
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- Dashboard -->
<a href="../director/director_head_dashboard.php" class="flex items-center space-x-3 px-4 py-3 <?php echo $current_page === 'director_head_dashboard.php' ? 'text-white bg-primary/20 rounded-lg border border-primary/30' : 'text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors'; ?>">
    <i class="fas fa-tachometer-alt w-5"></i>
    <span>Dashboard</span>
</a>

<!-- Leave Management -->
<a href="../director/director_leave_management.php" class="flex items-center space-x-3 px-4 py-3 <?php echo $current_page === 'director_leave_management.php' ? 'text-white bg-primary/20 rounded-lg border border-primary/30' : 'text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors'; ?>">
    <i class="fas fa-calendar-check w-5"></i>
    <span>Leave Management</span>
</a>

<!-- Manage Users -->
<a href="../director/director_manage_user.php" class="flex items-center space-x-3 px-4 py-3 <?php echo $current_page === 'director_manage_user.php' ? 'text-white bg-primary/20 rounded-lg border border-primary/30' : 'text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors'; ?>">
    <i class="fas fa-users-cog w-5"></i>
    <span>Manage Users</span>
</a>

<!-- Leave Chart -->
<a href="../director/view_chart.php" class="flex items-center space-x-3 px-4 py-3 <?php echo $current_page === 'view_chart.php' ? 'text-white bg-primary/20 rounded-lg border border-primary/30' : 'text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors'; ?>">
    <i class="fas fa-calendar w-5"></i>
    <span>Leave Chart</span>
</a>
