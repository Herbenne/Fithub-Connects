<?php
session_start();
require_once '../config/database.php';
require_once '../includes/AWSFileManager.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../pages/login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $user_id = $_SESSION['user_id'];
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);
    $phone = trim($_POST['phone']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate inputs
    if (empty($first_name) || empty($last_name) || empty($email) || empty($username)) {
        header("Location: ../pages/profile.php?error=empty_fields");
        exit();
    }

    // Check if username or email already exists (excluding current user)
    $check_query = "SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?";
    $stmt = $db_connection->prepare($check_query);
    $stmt->bind_param("ssi", $username, $email, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        header("Location: ../pages/profile.php?error=duplicate");
        exit();
    }

    // Handle profile picture upload
    $profile_picture = null;
    if (!empty($_FILES['profile_picture']['name'])) {
        $file_tmp = $_FILES['profile_picture']['tmp_name'];
        $file_name = $_FILES['profile_picture']['name'];
        $file_size = $_FILES['profile_picture']['size'];
        $file_type = $_FILES['profile_picture']['type'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];

        // Validate file type
        if (!in_array($file_type, $allowed_types)) {
            header("Location: ../pages/profile.php?error=invalid_file_type");
            exit();
        }

        // Validate file size (5MB max)
        if ($file_size > 5 * 1024 * 1024) {
            header("Location: ../pages/profile.php?error=file_too_large");
            exit();
        }

        // Process file upload (AWS vs local)
        if (USE_AWS) {
            try {
                $awsManager = new AWSFileManager();
                $profile_picture = $awsManager->uploadProfilePicture($file_tmp, $user_id, $file_name);
                
                if (!$profile_picture) {
                    header("Location: ../pages/profile.php?error=aws_upload_error");
                    exit();
                }
                
                // Log the profile picture path
                error_log("AWS Profile picture path: " . $profile_picture);
            } catch (Exception $e) {
                error_log("AWS upload error: " . $e->getMessage());
                header("Location: ../pages/profile.php?error=aws_upload_error");
                exit();
            }
        } else {
            // Local file storage
            $upload_dir = "../uploads/profile_pictures/user_{$user_id}/";
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                if (!mkdir($upload_dir, 0777, true)) {
                    header("Location: ../pages/profile.php?error=upload_dir_not_writable");
                    exit();
                }
            }
            
            $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);
            $new_file_name = "profile_" . time() . "." . $file_ext;
            $upload_path = $upload_dir . $new_file_name;
            
            if (move_uploaded_file($file_tmp, $upload_path)) {
                $profile_picture = "uploads/profile_pictures/user_{$user_id}/" . $new_file_name;
                error_log("Local profile picture path: " . $profile_picture);
            } else {
                header("Location: ../pages/profile.php?error=move_upload_failed");
                exit();
            }
        }
    }

    // Handle password update if provided
    $password_sql = '';
    $password_param = null;
    $types = "ssss"; // For first_name, last_name, email, username

    if (!empty($new_password)) {
        // Check if passwords match
        if ($new_password !== $confirm_password) {
            header("Location: ../pages/profile.php?error=password_mismatch");
            exit();
        }

        // Hash the new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $password_sql = ", password = ?";
        $password_param = $hashed_password;
        $types .= "s"; // Add string type for password
    }

    // Handle profile picture update if provided
    $picture_sql = '';
    $picture_param = null;
    if ($profile_picture) {
        $picture_sql = ", profile_picture = ?";
        $picture_param = $profile_picture;
        $types .= "s"; // Add string type for profile_picture
    }

    // Handle phone update
    $phone_sql = ', contact_number = ?';
    $types .= "s"; // Add string type for contact_number

    // Build update query
    $update_query = "UPDATE users SET 
                    first_name = ?, 
                    last_name = ?, 
                    email = ?, 
                    username = ?
                    $password_sql
                    $picture_sql
                    $phone_sql
                    WHERE id = ?";

    // Add user_id parameter and its type
    $types .= "i"; // For the user_id in WHERE clause

    // Prepare and execute query with proper error handling
    $stmt = $db_connection->prepare($update_query);
    
    if (!$stmt) {
        header("Location: ../pages/profile.php?error=db_prepare_error");
        exit();
    }

    // Build parameters array in the right order
    $params = [$first_name, $last_name, $email, $username];
    if ($password_param) {
        $params[] = $password_param;
    }
    if ($picture_param) {
        $params[] = $picture_param;
    }
    // Always add phone
    $params[] = $phone;
    // Finally add user_id
    $params[] = $user_id;

    $stmt->bind_param($types, ...$params);
    
    if (!$stmt->execute()) {
        error_log("Profile update failed: " . $stmt->error);
        header("Location: ../pages/profile.php?error=db_execute_error");
        exit();
    }

    // Update successful
    header("Location: ../pages/profile.php?success=1");
    exit();
} else {
    // Not a POST request
    header("Location: ../pages/profile.php");
    exit();
}