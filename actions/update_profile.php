<?php
session_start();
include '../config/database.php';
require_once '../includes/AWSFileManager.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../pages/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Initialize AWS file manager if using AWS
$awsManager = null;
if (USE_AWS) {
    $awsManager = new AWSFileManager();
}

// Handle profile picture upload
if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    $file = $_FILES['profile_picture'];
    
    // Validate file type and size
    if (!in_array($file['type'], $allowed_types)) {
        header("Location: ../pages/profile.php?error=invalid_file_type");
        exit();
    }
    
    if ($file['size'] > $max_size) {
        header("Location: ../pages/profile.php?error=file_too_large");
        exit();
    }
    
    // Handle file upload based on storage mode (AWS or local)
    $relative_path = "";
    
    if (USE_AWS) {
        // AWS S3 upload
        $tmp_path = $file['tmp_name'];
        $filename = $file['name'];
        
        $relative_path = $awsManager->uploadProfilePicture($tmp_path, $user_id, $filename);
        
        if (!$relative_path) {
            header("Location: ../pages/profile.php?error=upload_failed");
            exit();
        }
    } else {
        // Local filesystem upload
        $upload_dir = '../assets/images/profile_pictures/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'profile_' . $user_id . '_' . time() . '.' . $extension;
        $filepath = $upload_dir . $filename;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            $relative_path = 'assets/images/profile_pictures/' . $filename;
        } else {
            header("Location: ../pages/profile.php?error=upload_failed");
            exit();
        }
    }
    
    // Update database with new profile picture path
    if (!empty($relative_path)) {
        $update_query = "UPDATE users SET profile_picture = ? WHERE id = ?";
        $stmt = $db_connection->prepare($update_query);
        $stmt->bind_param("si", $relative_path, $user_id);
        $stmt->execute();
    }
}

// Update other user information
$first_name = $_POST['first_name'];
$last_name = $_POST['last_name'];
$email = $_POST['email'];
$username = $_POST['username'];
$phone = $_POST['phone'];
$new_password = $_POST['new_password'];

// Basic validation
if (empty($first_name) || empty($last_name) || empty($email) || empty($username)) {
    header("Location: ../pages/profile.php?error=empty_fields");
    exit();
}

// Check if username or email already exists
$check_query = "SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?";
$stmt = $db_connection->prepare($check_query);
$stmt->bind_param("ssi", $username, $email, $user_id);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    header("Location: ../pages/profile.php?error=duplicate");
    exit();
}

// Update query
$query = "UPDATE users SET 
          first_name = ?, 
          last_name = ?, 
          email = ?, 
          username = ?, 
          contact_number = ?";
$params = [$first_name, $last_name, $email, $username, $phone];
$types = "sssss";

// Add password update if provided
if (!empty($new_password)) {
    if ($new_password !== $_POST['confirm_password']) {
        header("Location: ../pages/profile.php?error=password_mismatch");
        exit();
    }
    $query .= ", password = ?";
    $params[] = password_hash($new_password, PASSWORD_DEFAULT);
    $types .= "s";
}

$query .= " WHERE id = ?";
$params[] = $user_id;
$types .= "i";

$stmt = $db_connection->prepare($query);
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    // Update session variables
    $_SESSION['username'] = $username;
    header("Location: ../pages/profile.php?success=1");
} else {
    header("Location: ../pages/profile.php?error=update_failed");
}
exit();