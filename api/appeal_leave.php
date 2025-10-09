<?php
session_start();
require_once dirname(__DIR__) . '/config/database.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid leave request ID']);
    exit();
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$leave_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];
$reason = $input['reason'] ?? '';

if (empty($reason)) {
    echo json_encode(['success' => false, 'message' => 'Appeal reason is required']);
    exit();
}

try {
    // Check if leave request exists and belongs to user
    $stmt = $pdo->prepare("
        SELECT id, status, created_at 
        FROM leave_requests 
        WHERE id = ? AND employee_id = ?
    ");
    $stmt->execute([$leave_id, $user_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$request) {
        echo json_encode(['success' => false, 'message' => 'Leave request not found or access denied']);
        exit();
    }
    
    // Check if request can be appealed (only rejected requests within 7 days)
    if ($request['status'] !== 'rejected') {
        echo json_encode(['success' => false, 'message' => 'Only rejected requests can be appealed']);
        exit();
    }
    
    // Check if within 7 days
    $created = new DateTime($request['created_at']);
    $now = new DateTime();
    $diff = $now->diff($created);
    
    if ($diff->days >= 7) {
        echo json_encode(['success' => false, 'message' => 'Appeal period has expired (7 days)']);
        exit();
    }
    
    // Create appeals table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS leave_appeals (
            id INT PRIMARY KEY AUTO_INCREMENT,
            leave_request_id INT NOT NULL,
            employee_id INT NOT NULL,
            reason TEXT NOT NULL,
            status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (leave_request_id) REFERENCES leave_requests(id),
            FOREIGN KEY (employee_id) REFERENCES employees(id)
        )
    ");
    
    // Insert appeal
    $stmt = $pdo->prepare("
        INSERT INTO leave_appeals (leave_request_id, employee_id, reason) 
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$leave_id, $user_id, $reason]);
    
    // Update leave request status to under appeal
    $stmt = $pdo->prepare("
        UPDATE leave_requests 
        SET status = 'under_appeal', updated_at = CURRENT_TIMESTAMP 
        WHERE id = ?
    ");
    $stmt->execute([$leave_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Appeal submitted successfully'
    ]);
    
} catch (PDOException $e) {
    error_log("Database error submitting appeal: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>
