<?php
session_start();
require_once '../config/database.php';

// Strict access: Department Head only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    header('Location: ../auth/index.php');
    exit();
}

// Redirect to the dedicated calendar view
header('Location: view_chart.php');
exit();
?>


