<?php
session_start();

// Check if user is logged in
if (isset($_SESSION['user_id'])) {
    // User is logged in, redirect based on role
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        // Admin user - redirect to admin dashboard
        header('Location: admin/admin_dashboard.php');
    } else {
        // Regular user - redirect to user dashboard
        header('Location: user/dashboard.php');
    }
    exit();
} else {
    // User not logged in - redirect to login page
    header('Location: auth/index.php');
    exit();
}
?>