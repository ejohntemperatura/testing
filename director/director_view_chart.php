<?php
session_start();
require_once '../config/database.php';

// Strict access: Director only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'director') {
    header('Location: ../auth/index.php');
    exit();
}

// Redirect to the dedicated calendar view
header('Location: view_chart.php');
exit();
?>


