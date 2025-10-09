<?php
/**
 * Secure Medical Certificate Viewer
 * Handles secure access to medical certificate files
 */

session_start();
require_once '../../../config/database.php';

// Check if user is logged in and has appropriate permissions
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'manager', 'director'])) {
    http_response_code(403);
    die('Access denied. Insufficient permissions.');
}

// Get the file path from the request
$file_path = $_GET['file'] ?? '';

if (empty($file_path)) {
    http_response_code(400);
    die('No file specified.');
}

// Debug: Log the requested file path
error_log("Medical Certificate Request: " . $file_path);

// Security: Ensure the file path is within allowed directories
$allowed_base_path = realpath('../../../uploads/medical_certificates');
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
