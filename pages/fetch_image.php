<?php
// In fetch_image.php - For profile pictures and other public images
session_start();
include '../config/database.php';

// Log requests for debugging
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

if (!$user || empty($user['profile_picture'])) {
    header("Location: ../assets/images/default-profile.jpg");
    exit();
}

// Handle S3 URLs
if (strpos($user['profile_picture'], 's3.') !== false || 
    strpos($user['profile_picture'], 'amazonaws.com') !== false) {
    
    require_once '../includes/AWSFileManager.php';
    $awsManager = new AWSFileManager();
    
    // For profile images - get public URL
    $url = $awsManager->getPublicUrl($user['profile_picture']);
    if ($url) {
        header("Location: " . $url);
        exit();
    }
}

// If we get here, use local file or default
$local_path = "../" . ltrim($user['profile_picture'], '/');
if (file_exists($local_path)) {
    header("Location: " . $local_path);
} else {
    header("Location: ../assets/images/default-profile.jpg");
}
exit();