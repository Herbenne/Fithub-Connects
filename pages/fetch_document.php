<?php
// In fetch_document.php - For legal documents (private)
session_start();
include '../config/database.php';

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

if (!$gym || empty($gym['legal_documents'])) {
    error_log("No legal documents found for gym: " . $gym_id);
    header('HTTP/1.0 404 Not Found');
    echo "No documents found";
    exit();
}

// Parse legal documents JSON
$legal_docs = json_decode($gym['legal_documents'], true);
if (!isset($legal_docs[$doc_type])) {
    error_log("Document type not found: " . $doc_type);
    header('HTTP/1.0 404 Not Found');
    echo "Document not found";
    exit();
}

$doc_path = $legal_docs[$doc_type];

// Handle S3 paths - generate temporary access
if (strpos($doc_path, 's3.') !== false || 
    strpos($doc_path, 'amazonaws.com') !== false) {
    
    require_once '../includes/AWSFileManager.php';
    $awsManager = new AWSFileManager();
    
    // Generate secure URL for legal documents
    $url = $awsManager->getSecureUrl($doc_path);
    if ($url) {
        header("Location: " . $url);
        exit();
    }
}

// If we get here, use local file or default
$local_path = "../" . ltrim($doc_path, '/');
if (file_exists($local_path)) {
    header("Location: " . $local_path);
} else {
    header("Location: ../assets/images/document-not-found.jpg");
}
exit();