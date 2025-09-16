<?php
session_start();
require_once '../config/database.php';

// Test admin session
echo "<h2>Admin Session Test</h2>";
echo "<p>User ID: " . ($_SESSION['user_id'] ?? 'Not set') . "</p>";
echo "<p>Role: " . ($_SESSION['role'] ?? 'Not set') . "</p>";

// Test database connection
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM employees");
    $count = $stmt->fetchColumn();
    echo "<p>Total employees in database: $count</p>";
    
    // Test admin query
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ? AND role = 'admin'");
        $stmt->execute([$_SESSION['user_id']]);
        $admin = $stmt->fetch();
        
        if ($admin) {
            echo "<p>Admin found: " . htmlspecialchars($admin['name']) . "</p>";
        } else {
            echo "<p>No admin found for current user</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p>Database error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<h3>Quick Links</h3>";
echo "<p><a href='admin_dashboard.php'>Admin Dashboard</a></p>";
echo "<p><a href='manage_user.php'>Manage Users</a></p>";
echo "<p><a href='index.php'>Login Page</a></p>";
?> 