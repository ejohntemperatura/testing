<?php
// Navigation helper file
// Include this file in your pages to get consistent navigation

function getNavigation($user_role = 'user') {
    if ($user_role === 'admin') {
        return [
            ['url' => 'admin_dashboard.php', 'title' => 'Dashboard', 'icon' => 'fas fa-tachometer-alt'],
            ['url' => 'manage_user.php', 'title' => 'Manage Users', 'icon' => 'fas fa-users-cog'],
            ['url' => 'leave_management.php', 'title' => 'Leave Management', 'icon' => 'fas fa-calendar-check'],
            ['url' => 'view_chart.php', 'title' => 'View Charts', 'icon' => 'fas fa-chart-bar'],
            ['url' => 'reports.php', 'title' => 'Reports', 'icon' => 'fas fa-chart-line'],
            ['url' => '../user/profile.php', 'title' => 'Profile', 'icon' => 'fas fa-user'],
            ['url' => '../auth/logout.php', 'title' => 'Logout', 'icon' => 'fas fa-sign-out-alt']
        ];
    } else {
        return [
            ['url' => 'dashboard.php', 'title' => 'Dashboard', 'icon' => 'fas fa-tachometer-alt'],
            ['url' => 'leave_history.php', 'title' => 'Leave History', 'icon' => 'fas fa-history'],
            ['url' => 'submit_leave.php', 'title' => 'Submit Leave', 'icon' => 'fas fa-plus'],
            ['url' => 'leave_credits.php', 'title' => 'Leave Credits', 'icon' => 'fas fa-calendar'],
            ['url' => 'dtr.php', 'title' => 'Daily Time Record', 'icon' => 'fas fa-clock'],
            ['url' => 'profile.php', 'title' => 'Profile', 'icon' => 'fas fa-user'],
            ['url' => '../auth/logout.php', 'title' => 'Logout', 'icon' => 'fas fa-sign-out-alt']
        ];
    }
}

function renderNavigation($user_role = 'user') {
    $nav_items = getNavigation($user_role);
    $current_page = basename($_SERVER['PHP_SELF']);
    
    echo '<div class="sidebar">';
    echo '<div class="sidebar-header">';
    echo '<h4><i class="fas fa-user-shield me-2"></i>' . ($user_role === 'admin' ? 'Admin Panel' : 'Employee Panel') . '</h4>';
    echo '</div>';
    echo '<div class="sidebar-menu">';
    
    foreach ($nav_items as $item) {
        $is_active = ($current_page === $item['url']) ? 'active' : '';
        echo '<a href="' . $item['url'] . '" class="' . $is_active . '">';
        echo '<i class="' . $item['icon'] . '"></i>' . $item['title'];
        echo '</a>';
    }
    
    echo '</div>';
    echo '</div>';
}
?>
