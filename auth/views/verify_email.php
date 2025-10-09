<?php
session_start();
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/app/core/services/RobustEmail.php';

// Check if token is provided
if (!isset($_GET['token']) || empty($_GET['token'])) {
    $error_message = "Invalid verification link. Please contact your administrator.";
    include 'verification_result.php';
    exit();
}

$token = $_GET['token'];

try {
    // Find the verification token
    $stmt = $pdo->prepare("
        SELECT e.*, evl.expires_at 
        FROM employees e 
        JOIN email_verification_logs evl ON e.id = evl.employee_id 
        WHERE e.verification_token = ? AND e.email_verified = 0
        ORDER BY evl.sent_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        $error_message = "Invalid or expired verification token. Please contact your administrator.";
        include 'verification_result.php';
        exit();
    }
    
    // Check if token has expired
    if (strtotime($user['expires_at']) < time()) {
        $error_message = "Verification token has expired. Please contact your administrator for a new verification link.";
        include 'verification_result.php';
        exit();
    }
    
    // Check if password is provided
    if (isset($_POST['password']) && !empty($_POST['password'])) {
        $password = $_POST['password'];
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Validate password
        if (strlen($password) < 6) {
            $error_message = "Password must be at least 6 characters long.";
            include 'verification_result.php';
            exit();
        }
        
        if ($password !== $confirmPassword) {
            $error_message = "Passwords do not match.";
            include 'verification_result.php';
            exit();
        }
        
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Update user status
        $stmt = $pdo->prepare("
            UPDATE employees 
            SET email_verified = 1, 
                password = ?, 
                account_status = 'active',
                verification_token = NULL,
                verification_expires = NULL
            WHERE id = ?
        ");
        $stmt->execute([$hashedPassword, $user['id']]);
        
        // Mark verification as used
        $stmt = $pdo->prepare("
            UPDATE email_verification_logs 
            SET verified_at = NOW(), status = 'verified' 
            WHERE employee_id = ? AND verification_token = ?
        ");
        $stmt->execute([$user['id'], $token]);
        
        // Commit transaction
        $pdo->commit();
        
        // Send welcome email
        $emailService = new RobustEmail($pdo);
        $emailService->sendWelcomeEmail($user['email'], $user['name'], '');
        
        $success_message = "Email verified successfully! Your account is now active. You can now log in with your email and password.";
        $user_name = $user['name'];
        $user_email = $user['email'];
        
        include 'verification_result.php';
    } else {
        // Show password creation form
        $user_name = $user['name'];
        $user_email = $user['email'];
        $verification_token = $token;
        include 'verification_result.php';
    }
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    $error_message = "An error occurred during verification: " . $e->getMessage();
    include 'verification_result.php';
}
?>