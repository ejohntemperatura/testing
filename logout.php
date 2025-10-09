<?php
/**
 * Root Level Logout Redirect
 * This provides a simple logout endpoint accessible from anywhere
 */

session_start();
session_destroy();

// Redirect to login page
header('Location: auth/views/login.php');
exit();
?>

