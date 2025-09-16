<?php
// Reusable Sidebar Component for ELMS
$current_page = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role'] ?? 'employee';

// Determine panel title and dashboard link based on role
$panelTitle = $role === 'director' ? 'Director Panel' : 
              ($role === 'manager' ? 'Department Head' : 
              ($role === 'admin' ? 'Admin Panel' : 'Employee Panel'));

$dashboardLink = $role === 'director' ? '../director/director_head_dashboard.php' : 
                 ($role === 'manager' ? '../department/department_head_dashboard.php' : 
                 ($role === 'admin' ? 'admin_dashboard.php' : 'dashboard.php'));

// Define navigation items based on role
$navItems = [];

if ($role === 'admin') {
    $navItems = [
        ['href' => 'admin_dashboard.php', 'icon' => 'fas fa-tachometer-alt', 'text' => 'Dashboard'],
        ['href' => 'manage_user.php', 'icon' => 'fas fa-users-cog', 'text' => 'Manage Users'],
        ['href' => 'leave_management.php', 'icon' => 'fas fa-calendar-check', 'text' => 'Leave Management', 'badge' => 'pendingLeaveBadge'],
        ['href' => 'leave_alerts.php', 'icon' => 'fas fa-bell', 'text' => 'Leave Alerts'],
        ['href' => 'view_chart.php', 'icon' => 'fas fa-calendar', 'text' => 'Leave Chart'],
        ['href' => 'reports.php', 'icon' => 'fas fa-file-alt', 'text' => 'Reports']
    ];
} elseif ($role === 'manager') {
    $navItems = [
        ['href' => '../department/department_head_dashboard.php', 'icon' => 'fas fa-tachometer-alt', 'text' => 'Dashboard'],
        ['href' => '../department/approve_leave.php', 'icon' => 'fas fa-calendar-check', 'text' => 'Approve Leave'],
        ['href' => '../department/view_chart.php', 'icon' => 'fas fa-calendar', 'text' => 'Leave Chart']
    ];
} elseif ($role === 'director') {
    $navItems = [
        ['href' => '../director/director_head_dashboard.php', 'icon' => 'fas fa-tachometer-alt', 'text' => 'Dashboard'],
        ['href' => '../director/director_leave_management.php', 'icon' => 'fas fa-calendar-check', 'text' => 'Leave Management'],
        ['href' => '../director/director_manage_user.php', 'icon' => 'fas fa-users-cog', 'text' => 'Manage Users'],
        ['href' => '../director/view_chart.php', 'icon' => 'fas fa-calendar', 'text' => 'Leave Chart']
    ];
} else {
    // Employee navigation
    $navItems = [
        ['href' => 'dashboard.php', 'icon' => 'fas fa-tachometer-alt', 'text' => 'Dashboard'],
        ['href' => 'leave_history.php', 'icon' => 'fas fa-history', 'text' => 'Leave History'],
        ['href' => 'profile.php', 'icon' => 'fas fa-user', 'text' => 'Profile'],
        ['href' => 'leave_credits.php', 'icon' => 'fas fa-calculator', 'text' => 'Leave Credits'],
        ['href' => 'apply_leave.php', 'icon' => 'fas fa-calendar-plus', 'text' => 'Apply Leave'],
        ['href' => 'view_chart.php', 'icon' => 'fas fa-calendar', 'text' => 'Leave Chart']
    ];
}
?>

<!-- Left Sidebar -->
<aside class="fixed left-0 top-16 h-screen w-64 bg-slate-800 border-r border-slate-700 overflow-y-auto z-40">
    <nav class="p-4 space-y-2">
        <?php foreach ($navItems as $item): ?>
            <?php 
            $isActive = $current_page === $item['href'] || 
                       (isset($item['href']) && strpos($current_page, $item['href']) !== false);
            $activeClass = $isActive ? 'text-white bg-primary/20 rounded-lg border border-primary/30' : 
                                      'text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors';
            ?>
            <a href="<?php echo $item['href']; ?>" class="flex items-center space-x-3 px-4 py-3 <?php echo $activeClass; ?>">
                <i class="<?php echo $item['icon']; ?> w-5"></i>
                <span><?php echo $item['text']; ?></span>
                <?php if (isset($item['badge'])): ?>
                    <span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full" id="<?php echo $item['badge']; ?>" style="display: none;">0</span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>
</aside>
