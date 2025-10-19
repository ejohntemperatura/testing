<?php
session_start();

// Clear remember token from database if exists
if (isset($_SESSION['user_id'])) {
    require_once '../../config/database.php';
    $stmt = $pdo->prepare("UPDATE employees SET remember_token = NULL, remember_token_expires = NULL WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
}

// Clear remember token cookie (multiple attempts to ensure it's cleared)
setcookie('remember_token', '', time() - 3600, '/');
setcookie('remember_token', '', time() - 3600, '/', '');
setcookie('remember_token', '', time() - 3600, '/', '', false, true);

session_destroy();

header('Location: ../../auth/views/login.php?logged_out=1');
exit();
?> 