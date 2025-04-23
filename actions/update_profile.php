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

// Create upload directory if it doesn't exist (for local storage)
if (!USE_AWS) {
    $upload_dir = '../assets/images/profile_pictures/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Check if directory is writable
    if (!is_writable($upload_dir)) {
        error_log("Upload directory is not writable: " . $upload_dir);
        header("Location: ../pages/profile.php?error=upload_dir_not_writable");
        exit();
    }
}

// Handle profile picture upload
if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    $file = $_FILES['profile_picture'];
    
    // Log file information for debugging
    error_log("Processing profile picture upload: " . print_r($file, true));
    
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
        try {
            $tmp_path = $file['tmp_name'];
            $filename = $file['name'];
            
            $relative_path = $awsManager->uploadProfilePicture($tmp_path, $user_id, $filename);
            
            if (!$relative_path) {
                error_log("AWS upload failed for profile picture");
                header("Location: ../pages/profile.php?error=upload_failed");
                exit();
            }
            
            error_log("AWS upload successful, path: " . $relative_path);
        } catch (Exception $e) {
            error_log("AWS upload error: " . $e->getMessage());
            header("Location: ../pages/profile.php?error=aws_upload_error");
            exit();
        }
    } else {
        // Local filesystem upload
        try {
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
                error_log("Local upload successful, path: " . $relative_path);
            } else {
                $upload_error = error_get_last();
                error_log("Failed to move uploaded file: " . ($upload_error ? $upload_error['message'] : 'Unknown error'));
                header("Location: ../pages/profile.php?error=move_upload_failed");
                exit();
            }
        } catch (Exception $e) {
            error_log("Local upload error: " . $e->getMessage());
            header("Location: ../pages/profile.php?error=local_upload_error");
            exit();
        }
    }
    
    // Update database with new profile picture path
    if (!empty($relative_path)) {
        $update_query = "UPDATE users SET profile_picture = ? WHERE id = ?";
        $stmt = $db_connection->prepare($update_query);
        
        if (!$stmt) {
            error_log("Database prepare error: " . $db_connection->error);
            header("Location: ../pages/profile.php?error=db_prepare_error");
            exit();
        }
        
        $stmt->bind_param("si", $relative_path, $user_id);
        
        if (!$stmt->execute()) {
            error_log("Database execute error: " . $stmt->error);
            header("Location: ../pages/profile.php?error=db_execute_error");
            exit();
        }
        
        error_log("Profile picture updated in database for user " . $user_id . " with path " . $relative_path);
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

if (!$stmt) {
    error_log("Database prepare error for user update: " . $db_connection->error);
    header("Location: ../pages/profile.php?error=db_prepare_error");
    exit();
}

$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    // Update session variables
    $_SESSION['username'] = $username;
    header("Location: ../pages/profile.php?success=1");
} else {
    error_log("Database execute error for user update: " . $stmt->error);
    header("Location: ../pages/profile.php?error=update_failed");
}
exit();