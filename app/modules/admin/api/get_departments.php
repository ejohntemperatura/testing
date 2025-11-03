<?php
session_start();
require_once '../../../../config/database.php';

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'manager', 'director'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

try {
    // Get distinct departments from employees table where employees still exist
    // Excluding NULL, empty values, Executive, and Operations
    // This ensures that if a department head is deleted and no other employees exist in that department,
    // the department will be removed from the dropdown
    $stmt = $pdo->query("
        SELECT DISTINCT department 
        FROM employees 
        WHERE department IS NOT NULL 
        AND department != '' 
        AND department NOT IN ('Executive', 'Operations')
        GROUP BY department
        HAVING COUNT(*) > 0
        ORDER BY department ASC
    ");
    
    $departments = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo json_encode([
        'success' => true,
        'departments' => $departments
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
