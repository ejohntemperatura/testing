<?php
session_start();
require_once '../config/database.php';

// Strict access: Director only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'director') {
	header('Location: ../auth/index.php');
	exit();
}

// Reuse the same logic as admin/leave_management.php but with director-only sidebar and links

// Handle bulk/individual updates and listing – include the core logic file contents
// We safely include the original file's logic by requiring it here after setting a flag
$__DIRECTOR_LEAVE_PAGE__ = true;
require __DIR__ . '/../admin/leave_management.php';
?>


