<?php
session_start();
include '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../pages/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle profile picture upload
if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === 0) {
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    $filename = $_FILES['profile_picture']['name'];
    $filetype = pathinfo($filename, PATHINFO_EXTENSION);

    if (in_array(strtolower($filetype), $allowed)) {
        // Create upload directory if it doesn't exist
        $upload_dir = '../uploads/profile_pictures';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        // Generate unique filename
        $new_filename = 'profile_' . $user_id . '_' . time() . '.' . $filetype;
        $upload_path = $upload_dir . '/' . $new_filename;
        
        if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
            // Remove old profile picture if exists
            if (!empty($user['profile_picture'])) {
                $old_file = '../' . $user['profile_picture'];
                if (file_exists($old_file)) {
                    unlink($old_file);
                }
            }
            
            // Store relative path in database
            $profile_picture = 'uploads/profile_pictures/' . $new_filename;
            
            $query = "UPDATE users SET profile_picture = ? WHERE id = ?";
            $stmt = $db_connection->prepare($query);
            $stmt->bind_param("si", $profile_picture, $user_id);
            $stmt->execute();
        }
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