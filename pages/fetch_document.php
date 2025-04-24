<?php
session_start();
include '../config/database.php';
require_once '../includes/AWSFileManager.php';

// Add extensive logging
error_log("Attempting to fetch document. Gym ID: " . ($_GET['gym_id'] ?? 'none') . 
          ", Doc type: " . ($_GET['doc_type'] ?? 'none'));

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

// Initialize AWS file manager
$awsManager = new AWSFileManager();

// If it's an AWS URL (starts with http)
if (strpos($doc_path, 'http') === 0) {
    error_log("Redirecting to AWS URL: " . $doc_path);
    header("Location: " . $doc_path);
    exit();
}

// If it's a path to AWS without http
if (USE_AWS) {
    // Get the public URL from AWS
    $url = $awsManager->getPublicUrl($doc_path);
    
    if ($url) {
        error_log("Constructed AWS URL: " . $url);
        header("Location: " . $url);
        exit();
    } else {
        error_log("Failed to get AWS URL for: " . $doc_path);
    }
}

// If AWS fails or not using AWS, try local file as fallback
$local_path = "../" . ltrim($doc_path, '/');
error_log("Local path: " . $local_path);

if (file_exists($local_path)) {
    header("Location: " . $local_path);
} else {
    error_log("File not found at: " . $local_path);
    header("Location: ../assets/images/document-not-found.jpg");
}
exit();