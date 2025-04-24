<?php
session_start();
include '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../pages/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Initialize upload directory paths
$upload_dir = '../assets/images/profile_pictures/';
$success = false;
$error_message = "";

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

    if (USE_AWS) {
        try {
            require_once '../includes/AWSFileManager.php';
            $awsManager = new AWSFileManager();
            
            // Get current profile picture path
            $get_current = "SELECT profile_picture FROM users WHERE id = ?";
            $stmt = $db_connection->prepare($get_current);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $current_pic = $stmt->get_result()->fetch_assoc()['profile_picture'];
            
            // Upload new picture
            $tmp_path = $file['tmp_name'];
            $filename = $file['name'];
            $relative_path = $awsManager->uploadProfilePicture($tmp_path, $user_id, $filename);
            
            if (!$relative_path) {
                error_log("AWS upload failed for profile picture");
                header("Location: ../pages/profile.php?error=upload_failed");
                exit();
            }
            
            // Update database with new path
            $update_query = "UPDATE users SET profile_picture = ? WHERE id = ?";
            $stmt = $db_connection->prepare($update_query);
            $stmt->bind_param("si", $relative_path, $user_id);
            
            if (!$stmt->execute()) {
                error_log("Database update error: " . $stmt->error);
                header("Location: ../pages/profile.php?error=db_execute_error");
                exit();
            }
    
        } catch (Exception $e) {
            error_log("AWS upload error: " . $e->getMessage());
            header("Location: ../pages/profile.php?error=aws_upload_error");
            exit();
        }
    } else {
        // Local storage logic
        $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/Fithub-Connects/assets/images/profile_pictures/';
    
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0777, true)) {
                error_log("Failed to create upload directory: " . $upload_dir);
                header("Location: ../pages/profile.php?error=upload_dir_creation_failed");
                exit();
            }
            chmod($upload_dir, 0777);
        }
    
        if (!is_writable($upload_dir)) {
            chmod($upload_dir, 0777);
            if (!is_writable($upload_dir)) {
                header("Location: ../pages/profile.php?error=upload_dir_not_writable");
                exit();
            }
        }
    
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'profile_' . $user_id . '_' . time() . '.' . $extension;
        $filepath = $upload_dir . $filename;
    
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            $relative_path = 'assets/images/profile_pictures/' . $filename;
            error_log("Local upload successful, path: " . $relative_path);
    
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
    
        } else {
            $upload_error = error_get_last();
            error_log("Failed to move uploaded file: " . ($upload_error ? $upload_error['message'] : 'Unknown error'));
            header("Location: ../pages/profile.php?error=move_upload_failed");
            exit();
        }
    } 
}
// Update other user information
$first_name = $_POST['first_name'] ?? '';
$last_name = $_POST['last_name'] ?? '';
$email = $_POST['email'] ?? '';
$username = $_POST['username'] ?? '';
$phone = $_POST['phone'] ?? '';
$new_password = $_POST['new_password'] ?? '';

// Basic validation
if (empty($first_name) || empty($last_name) || empty($email) || empty($username)) {
    header("Location: ../pages/profile.php?error=empty_fields");
    exit();
}

// Check if username or email already exists
$check_query = "SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?";
$stmt = $db_connection->prepare($check_query);
if (!$stmt) {
    error_log("Check query preparation error: " . $db_connection->error);
    header("Location: ../pages/profile.php?error=db_error");
    exit();
}

$stmt->bind_param("ssi", $username, $email, $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
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
    
    // Redirect with success message
    header("Location: ../pages/profile.php?success=1");
    exit();
} else {
    error_log("Database execute error for user update: " . $stmt->error);
    header("Location: ../pages/profile.php?error=update_failed");
    exit();
}