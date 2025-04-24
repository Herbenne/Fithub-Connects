<?php
session_start();
include '../config/database.php';
require_once '../includes/AWSFileManager.php';

// Log every request for debugging
ini_set('display_errors', 1);
error_log("Starting fetch, REQUEST_URI: " . $_SERVER['REQUEST_URI']);
error_log("GET parameters: " . print_r($_GET, true));
error_log("Fetching image for user: " . ($_GET['user_id'] ?? $_SESSION['user_id']));

// Get user ID
$user_id = $_GET['user_id'] ?? $_SESSION['user_id'];

// Get user data
$query = "SELECT profile_picture FROM users WHERE id = ?";
$stmt = $db_connection->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

error_log("Profile picture path: " . ($user['profile_picture'] ?? "none"));

if (!$user || empty($user['profile_picture'])) {
    header("Location: ../assets/images/default-profile.jpg");
    exit();
}

// Initialize AWS file manager
$awsManager = new AWSFileManager();

// If it's an AWS URL (starts with http)
if (strpos($user['profile_picture'], 'http') === 0) {
    error_log("Redirecting to AWS URL: " . $user['profile_picture']);
    header("Location: " . $user['profile_picture']);
    exit();
}

// If it's a path to AWS without http
if (USE_AWS) {
    // Get the public URL from AWS
    $url = $awsManager->getPublicUrl($user['profile_picture']);
    
    if ($url) {
        error_log("Constructed AWS URL: " . $url);
        header("Location: " . $url);
        exit();
    } else {
        error_log("Failed to get AWS URL for: " . $user['profile_picture']);
    }
}

// If AWS fails or not using AWS, try local file as fallback
$local_path = "../" . ltrim($user['profile_picture'], '/');
error_log("Local path: " . $local_path);

if (file_exists($local_path)) {
    header("Location: " . $local_path);
} else {
    error_log("File not found at: " . $local_path);
    header("Location: ../assets/images/default-profile.jpg");
}
exit();