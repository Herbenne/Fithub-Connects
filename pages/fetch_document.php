<?php
session_start();
include '../config/database.php';
require_once '../includes/AWSFileManager.php';

// Enhanced error logging
ini_set('display_errors', 0); // Don't display errors to users
error_log("Starting fetch, REQUEST_URI: " . $_SERVER['REQUEST_URI']);
error_log("GET parameters: " . print_r($_GET, true));

// Security check - must be superadmin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    error_log("Access denied - not superadmin");
    header('HTTP/1.0 403 Forbidden');
    echo "Access denied";
    exit();
}

// Get parameters
$gym_id = $_GET['gym_id'] ?? null;
$doc_type = $_GET['doc_type'] ?? null;

if (!$gym_id || !$doc_type) {
    error_log("Missing parameters");
    header('HTTP/1.0 400 Bad Request');
    echo "Missing parameters";
    exit();
}

// Get gym details including legal documents
$query = "SELECT g.*, u.id as owner_id FROM gyms g JOIN users u ON g.owner_id = u.id WHERE g.gym_id = ?";
$stmt = $db_connection->prepare($query);
$stmt->bind_param("i", $gym_id);
$stmt->execute();
$gym = $stmt->get_result()->fetch_assoc();

if (!$gym) {
    error_log("Gym not found: " . $gym_id);
    header('HTTP/1.0 404 Not Found');
    echo "Gym not found";
    exit();
}

if (empty($gym['legal_documents'])) {
    error_log("No legal documents found for gym: " . $gym_id);
    header('HTTP/1.0 404 Not Found');
    echo "No documents found";
    exit();
}

// Parse legal documents JSON
$legal_docs = json_decode($gym['legal_documents'], true);
error_log("Legal documents: " . print_r($legal_docs, true));

if (!isset($legal_docs[$doc_type])) {
    error_log("Document type not found: " . $doc_type);
    header('HTTP/1.0 404 Not Found');
    echo "Document not found";
    exit();
}

$doc_path = $legal_docs[$doc_type];
error_log("Document path: " . $doc_path);

// Initialize AWS file manager if using AWS
if (USE_AWS) {
    $awsManager = new AWSFileManager();
    
    // Get presigned URL with expiration time
    $url = $awsManager->getPresignedUrl($doc_path, '+15 minutes');
    
    if ($url) {
        error_log("Redirecting to presigned URL: " . $url);
        header("Location: " . $url);
        exit();
    } else {
        error_log("Failed to generate presigned URL for: " . $doc_path);
    }
}

// Fallback to local file path if AWS fails or not using AWS
$local_path = "../" . ltrim($doc_path, '/');
error_log("Trying local path: " . $local_path);

if (file_exists($local_path)) {
    // Get file MIME type
    $file_info = pathinfo($local_path);
    $extension = strtolower($file_info['extension']);
    
    // Set appropriate content type
    $content_type = 'application/octet-stream'; // Default
    
    if ($extension == 'pdf') {
        $content_type = 'application/pdf';
    } elseif (in_array($extension, ['jpg', 'jpeg'])) {
        $content_type = 'image/jpeg';
    } elseif ($extension == 'png') {
        $content_type = 'image/png';
    } elseif ($extension == 'gif') {
        $content_type = 'image/gif';
    }
    
    // Output file with proper headers
    header('Content-Type: ' . $content_type);
    header('Content-Disposition: inline; filename="' . basename($local_path) . '"');
    header('Content-Length: ' . filesize($local_path));
    
    readfile($local_path);
    exit();
} else {
    error_log("File not found at: " . $local_path);
    
    // Try alternate path formats
    $alt_paths = [
        '../' . $doc_path,
        $doc_path,
        '../uploads/' . basename($doc_path)
    ];
    
    foreach ($alt_paths as $path) {
        error_log("Trying alternate path: " . $path);
        if (file_exists($path)) {
            // Get file MIME type
            $file_info = pathinfo($path);
            $extension = strtolower($file_info['extension']);
            
            // Set appropriate content type
            $content_type = 'application/octet-stream'; // Default
            
            if ($extension == 'pdf') {
                $content_type = 'application/pdf';
            } elseif (in_array($extension, ['jpg', 'jpeg'])) {
                $content_type = 'image/jpeg';
            } elseif ($extension == 'png') {
                $content_type = 'image/png';
            } elseif ($extension == 'gif') {
                $content_type = 'image/gif';
            }
            
            // Output file with proper headers
            header('Content-Type: ' . $content_type);
            header('Content-Disposition: inline; filename="' . basename($path) . '"');
            header('Content-Length: ' . filesize($path));
            
            readfile($path);
            exit();
        }
    }
    
    // If we get here, the file was not found
    header('HTTP/1.0 404 Not Found');
    echo "File not found";
    exit();
}