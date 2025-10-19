<?php
/**
 * Secure Medical Certificate Viewer
 * Handles secure access to medical certificate files
 */

session_start();
require_once '../../../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die('Access denied. Please log in.');
}

// Get the file path from the request
$file_path = $_GET['file'] ?? '';

if (empty($file_path)) {
    http_response_code(400);
    die('No file specified.');
}

// For regular employees, verify they can only access their own medical certificates
if ($_SESSION['role'] === 'employee') {
    // Extract filename from path for verification
    $filename = basename($file_path);
    
    // Check if this medical certificate belongs to the current user
    $stmt = $pdo->prepare("SELECT id FROM leave_requests WHERE employee_id = ? AND medical_certificate_path LIKE ?");
    $stmt->execute([$_SESSION['user_id'], '%' . $filename]);
    $leave_request = $stmt->fetch();
    
    if (!$leave_request) {
        http_response_code(403);
        die('Access denied. You can only view your own medical certificates.');
    }
}

// Debug: Log the requested file path
error_log("Medical Certificate Request: " . $file_path);

// Security: Ensure the file path is within allowed directories
$allowed_base_path = realpath('../../../uploads/medical_certificates');

// Clean the file path - remove any path traversal attempts
$file_path = str_replace(['../', '..\\'], '', $file_path);
$file_path = ltrim($file_path, '/\\');

// Remove the uploads/medical_certificates prefix if it exists
$file_path = preg_replace('#^uploads/medical_certificates/#', '', $file_path);

// Construct the full file path
$full_file_path = '../../../uploads/medical_certificates/' . $file_path;
$requested_file = realpath($full_file_path);

// Debug: Log paths for troubleshooting
error_log("Allowed base path: " . $allowed_base_path);
error_log("Full file path: " . $full_file_path);
error_log("Requested file: " . $requested_file);

// Check if the requested file is within the allowed directory
if ($requested_file === false || strpos($requested_file, $allowed_base_path) !== 0) {
    http_response_code(403);
    die('Access denied. Invalid file path. Debug: ' . $full_file_path);
}

// Check if file exists
if (!file_exists($requested_file)) {
    http_response_code(404);
    die('File not found: ' . $requested_file);
}

// Get file information
$file_info = pathinfo($requested_file);
$file_extension = strtolower($file_info['extension']);
$file_size = filesize($requested_file);

// Define allowed file types and their MIME types
$allowed_types = [
    'pdf' => 'application/pdf',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
];

// Check if file type is allowed
if (!array_key_exists($file_extension, $allowed_types)) {
    http_response_code(415);
    die('File type not supported: ' . $file_extension);
}

// Get MIME type
$mime_type = $allowed_types[$file_extension];

// Clear any previous output
if (ob_get_level()) {
    ob_end_clean();
}

// Set appropriate headers
header('Content-Type: ' . $mime_type);
header('Content-Length: ' . $file_size);
header('Content-Disposition: inline; filename="' . basename($requested_file) . '"');
header('Cache-Control: private, max-age=3600');

// For security, add some additional headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

// Output the file
readfile($requested_file);
exit();
?>
