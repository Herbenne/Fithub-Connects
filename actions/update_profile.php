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

    if (empty($first_name) || empty($last_name) || empty($email) || empty($username)) {
        header("Location: ../pages/profile.php?error=empty_fields");
        exit();
    }

    // Check for duplicate email or username
    $check_query = "SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?";
    $stmt = $db_connection->prepare($check_query);
    $stmt->bind_param("ssi", $username, $email, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        header("Location: ../pages/profile.php?error=duplicate");
        exit();
    }

    // Get existing profile picture
    $old_picture_path = null;
    $stmt = $db_connection->prepare("SELECT profile_picture FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $old_result = $stmt->get_result();
    if ($old_row = $old_result->fetch_assoc()) {
        $old_picture_path = $old_row['profile_picture'];
    }

    $profile_picture = null;
    if (!empty($_FILES['profile_picture']['name'])) {
        $file_tmp = $_FILES['profile_picture']['tmp_name'];
        $file_name = $_FILES['profile_picture']['name'];
        $file_size = $_FILES['profile_picture']['size'];
        $file_type = $_FILES['profile_picture']['type'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];

        if (!in_array($file_type, $allowed_types)) {
            header("Location: ../pages/profile.php?error=invalid_file_type");
            exit();
        }

        if ($file_size > 5 * 1024 * 1024) {
            header("Location: ../pages/profile.php?error=file_too_large");
            exit();
        }

        if (USE_AWS) {
            try {
                $awsManager = new AWSFileManager();
                if (!empty($old_picture_path)) {
                    $awsManager->deleteFile($old_picture_path);
                    error_log("Deleted old AWS picture: $old_picture_path");
                }
                $profile_picture = $awsManager->uploadProfilePicture($file_tmp, $user_id, $file_name);
                if (!$profile_picture) {
                    header("Location: ../pages/profile.php?error=aws_upload_error");
                    exit();
                }
            } catch (Exception $e) {
                error_log("AWS error: " . $e->getMessage());
                header("Location: ../pages/profile.php?error=aws_upload_error");
                exit();
            }
        } else {
            $upload_dir = "../uploads/profile_pictures/user_{$user_id}/";
            if (!file_exists($upload_dir)) {
                if (!mkdir($upload_dir, 0777, true)) {
                    header("Location: ../pages/profile.php?error=upload_dir_not_writable");
                    exit();
                }
            }

            if (!empty($old_picture_path)) {
                $old_local_path = "../" . ltrim($old_picture_path, '/');
                if (file_exists($old_local_path)) {
                    unlink($old_local_path);
                    error_log("Deleted old local picture: $old_local_path");
                }
            }

            $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);
            $new_file_name = "profile_" . time() . "." . $file_ext;
            $upload_path = $upload_dir . $new_file_name;

            if (move_uploaded_file($file_tmp, $upload_path)) {
                $profile_picture = "uploads/profile_pictures/user_{$user_id}/" . $new_file_name;
            } else {
                header("Location: ../pages/profile.php?error=move_upload_failed");
                exit();
            }
        }
    }

    // Handle password logic
    $password_sql = '';
    $password_param = null;
    $types = "ssss"; // first, last, email, username

    if (!empty($new_password)) {
        if ($new_password !== $confirm_password) {
            header("Location: ../pages/profile.php?error=password_mismatch");
            exit();
        }
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $password_sql = ", password = ?";
        $password_param = $hashed_password;
        $types .= "s";
    }

    // Profile picture SQL logic
    $picture_sql = '';
    $picture_param = null;
    if ($profile_picture) {
        $picture_sql = ", profile_picture = ?";
        $picture_param = $profile_picture;
        $types .= "s";
    }

    $phone_sql = ", contact_number = ?";
    $types .= "s"; // phone

    $update_query = "UPDATE users SET 
                    first_name = ?, 
                    last_name = ?, 
                    email = ?, 
                    username = ?
                    $password_sql
                    $picture_sql
                    $phone_sql
                    WHERE id = ?";

    $types .= "i"; // user_id
    $params = [$first_name, $last_name, $email, $username];
    if ($password_param) $params[] = $password_param;
    if ($picture_param) $params[] = $picture_param;
    $params[] = $phone;
    $params[] = $user_id;

    $stmt = $db_connection->prepare($update_query);
    if (!$stmt) {
        header("Location: ../pages/profile.php?error=db_prepare_error");
        exit();
    }

    $stmt->bind_param($types, ...$params);

    if (!$stmt->execute()) {
        error_log("Profile update failed: " . $stmt->error);
        header("Location: ../pages/profile.php?error=db_execute_error");
        exit();
    }

    header("Location: ../pages/profile.php?success=1");
    exit();
} else {
    header("Location: ../pages/profile.php");
    exit();
}
