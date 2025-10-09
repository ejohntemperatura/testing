<?php
// Main Dashboard Router
// Redirects to appropriate dashboard based on user role

session_start();

// Check if user is logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    // User is logged in, redirect to appropriate dashboard based on role
    $role = $_SESSION['role'];
    
    switch ($role) {
        case 'admin':
            header('Location: ../app/modules/admin/views/dashboard.php');
            break;
        case 'director':
            header('Location: ../app/modules/director/views/dashboard.php');
            break;
        case 'department_head':
            header('Location: ../app/modules/department/views/dashboard.php');
            break;
        case 'employee':
        default:
            header('Location: ../app/modules/user/views/dashboard.php');
            break;
    }
    exit();
} else {
    // User is not logged in, redirect to login
    header('Location: ../auth/views/login.php');
    exit();
}
?>

