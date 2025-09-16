<?php
// Manager (Department Head) Sidebar Component
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- Dashboard -->
<a href="../department/department_head_dashboard.php" class="flex items-center space-x-3 px-4 py-3 <?php echo $current_page === 'department_head_dashboard.php' ? 'text-white bg-primary/20 rounded-lg border border-primary/30' : 'text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors'; ?>">
    <i class="fas fa-tachometer-alt w-5"></i>
    <span>Dashboard</span>
</a>

<!-- Approve Leave -->
<a href="../department/approve_leave.php" class="flex items-center space-x-3 px-4 py-3 <?php echo $current_page === 'approve_leave.php' ? 'text-white bg-primary/20 rounded-lg border border-primary/30' : 'text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors'; ?>">
    <i class="fas fa-calendar-check w-5"></i>
    <span>Approve Leave</span>
</a>

<!-- Leave Chart -->
<a href="../department/view_chart.php" class="flex items-center space-x-3 px-4 py-3 <?php echo $current_page === 'view_chart.php' ? 'text-white bg-primary/20 rounded-lg border border-primary/30' : 'text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors'; ?>">
    <i class="fas fa-calendar w-5"></i>
    <span>Leave Chart</span>
</a>
