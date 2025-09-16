<?php
session_start();
require_once '../config/database.php';

echo "<h1>Debug Information</h1>";
echo "<p>Session ID: " . session_id() . "</p>";
echo "<p>User ID in session: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'Not set') . "</p>";
echo "<p>Role in session: " . (isset($_SESSION['role']) ? $_SESSION['role'] : 'Not set') . "</p>";

if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $employee = $stmt->fetch();
        
        if ($employee) {
            echo "<p>Employee found: " . htmlspecialchars($employee['name']) . "</p>";
            echo "<p>Employee email: " . htmlspecialchars($employee['email']) . "</p>";
        } else {
            echo "<p>No employee found with ID: " . $_SESSION['user_id'] . "</p>";
        }
    } catch (Exception $e) {
        echo "<p>Database error: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p>No user ID in session. Redirecting to login...</p>";
    header('Location: ../auth/index.php');
    exit();
}
?>
