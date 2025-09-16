<?php
session_start();
require_once '../config/database.php';

// Strict role: director only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'director') {
    header('Location: ../auth/index.php');
    exit();
}

// Reuse manage user logic but with role label and sidebar isolated to Director
// Load existing code
ob_start();
require __DIR__ . '/manage_user.php';
ob_end_flush();
?>


