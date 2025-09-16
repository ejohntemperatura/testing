<?php
// Employee Sidebar Component
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- Dashboard -->
<a href="dashboard.php" class="flex items-center space-x-3 px-4 py-3 <?php echo $current_page === 'dashboard.php' ? 'text-white bg-primary/20 rounded-lg border border-primary/30' : 'text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors'; ?>">
    <i class="fas fa-tachometer-alt w-5"></i>
    <span>Dashboard</span>
</a>

<!-- Leave History -->
<a href="leave_history.php" class="flex items-center space-x-3 px-4 py-3 <?php echo $current_page === 'leave_history.php' ? 'text-white bg-primary/20 rounded-lg border border-primary/30' : 'text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors'; ?>">
    <i class="fas fa-history w-5"></i>
    <span>Leave History</span>
</a>

<!-- Profile -->
<a href="profile.php" class="flex items-center space-x-3 px-4 py-3 <?php echo $current_page === 'profile.php' ? 'text-white bg-primary/20 rounded-lg border border-primary/30' : 'text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors'; ?>">
    <i class="fas fa-user w-5"></i>
    <span>Profile</span>
</a>

<!-- Leave Credits -->
<a href="leave_credits.php" class="flex items-center space-x-3 px-4 py-3 <?php echo $current_page === 'leave_credits.php' ? 'text-white bg-primary/20 rounded-lg border border-primary/30' : 'text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors'; ?>">
    <i class="fas fa-calculator w-5"></i>
    <span>Leave Credits</span>
</a>

<!-- Apply Leave -->
<a href="apply_leave.php" class="flex items-center space-x-3 px-4 py-3 <?php echo $current_page === 'apply_leave.php' ? 'text-white bg-primary/20 rounded-lg border border-primary/30' : 'text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors'; ?>">
    <i class="fas fa-calendar-plus w-5"></i>
    <span>Apply Leave</span>
</a>

<!-- Leave Chart -->
<a href="view_chart.php" class="flex items-center space-x-3 px-4 py-3 <?php echo $current_page === 'view_chart.php' ? 'text-white bg-primary/20 rounded-lg border border-primary/30' : 'text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors'; ?>">
    <i class="fas fa-calendar w-5"></i>
    <span>Leave Chart</span>
</a>
