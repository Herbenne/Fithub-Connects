<?php
session_start();
include '../config/database.php';

// Log every request for debugging
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

// If it's an AWS URL (starts with http)
if (strpos($user['profile_picture'], 'http') === 0) {
    error_log("Redirecting to AWS URL: " . $user['profile_picture']);
    header("Location: " . $user['profile_picture']);
    exit();
}

// If it's a path to AWS without http
if (strpos($user['profile_picture'], 's3.') !== false || 
    strpos($user['profile_picture'], 'amazonaws.com') !== false) {
    // Construct full URL
    $full_url = "https://fithubconnect-bucket.s3.ap-southeast-1.amazonaws.com/" . ltrim($user['profile_picture'], '/');
    error_log("Constructed AWS URL: " . $full_url);
    header("Location: " . $full_url);
    exit();
}

// If it's a relative path
$local_path = "../" . ltrim($user['profile_picture'], '/');
error_log("Local path: " . $local_path);

if (file_exists($local_path)) {
    header("Location: " . $local_path);
} else {
    error_log("File not found at: " . $local_path);
    header("Location: ../assets/images/default-profile.jpg");
}
exit();