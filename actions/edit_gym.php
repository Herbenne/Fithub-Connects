<?php
session_start();
include '../config/database.php';

// Verify superadmin access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: ../pages/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../pages/manage_gyms.php");
    exit();
}

$gym_id = $_POST['gym_id'] ?? null;

// Debug information
error_log("Processing gym edit for ID: " . $gym_id);
error_log("Files received: " . print_r($_FILES, true));

// Handle thumbnail upload
$gym_thumbnail = null;
if (!empty($_FILES['gym_thumbnail']['name'])) {
    $target_dir = "../assets/images/";
    $new_filename = 'Screenshot (' . uniqid() . ').' . pathinfo($_FILES['gym_thumbnail']['name'], PATHINFO_EXTENSION);
    $target_file = $target_dir . $new_filename;
    
    if (move_uploaded_file($_FILES['gym_thumbnail']['tmp_name'], $target_file)) {
        // Store path with ../
        $gym_thumbnail = '../assets/images/' . $new_filename;
        error_log("Thumbnail uploaded successfully: " . $gym_thumbnail);
    } else {
        error_log("Failed to move uploaded thumbnail. Upload error: " . $_FILES['gym_thumbnail']['error']);
    }
}

// Handle equipment images
$equipment_images = [];
if (!empty($_POST['existing_equipment'])) {
    $equipment_images = $_POST['existing_equipment'];
}

if (!empty($_FILES['equipment_images']['name'][0])) {
    $target_dir = "../assets/images/";
    
    foreach ($_FILES['equipment_images']['tmp_name'] as $key => $tmp_name) {
        $new_filename = 'Screenshot (' . uniqid() . ').' . pathinfo($_FILES['equipment_images']['name'][$key], PATHINFO_EXTENSION);
        $target_file = "../assets/images/" . $new_filename;
        
        if (move_uploaded_file($tmp_name, $target_file)) {
            // Store path with ../
            $equipment_images[] = '../assets/images/' . $new_filename;
            error_log("Equipment image uploaded successfully: " . $new_filename);
        } else {
            error_log("Failed to move equipment image. Upload error: " . $_FILES['equipment_images']['error'][$key]);
        }
    }
}

// Remove equipment images if requested
if (!empty($_POST['remove_equipment'])) {
    foreach ($_POST['remove_equipment'] as $index) {
        if (isset($equipment_images[$index])) {
            unset($equipment_images[$index]);
        }
    }
    $equipment_images = array_values($equipment_images);
}

// Prepare the update query
$update_query = "UPDATE gyms SET 
    gym_name = ?,
    gym_location = ?,
    gym_phone_number = ?,
    gym_description = ?,
    gym_amenities = ?,
    owner_id = ?";

$params = [
    $_POST['gym_name'],
    $_POST['gym_location'],
    $_POST['gym_phone_number'],
    $_POST['gym_description'],
    $_POST['gym_amenities'],
    $_POST['owner_id']
];
$types = "sssssi";

// Add thumbnail to update if new one was uploaded
if ($gym_thumbnail !== null) {
    $update_query .= ", gym_thumbnail = ?";
    $params[] = $gym_thumbnail;
    $types .= "s";
}

// Always update equipment_images
$update_query .= ", equipment_images = ?";
$params[] = json_encode(array_values($equipment_images));
$types .= "s";

// Add WHERE clause
$update_query .= " WHERE gym_id = ?";
$params[] = $gym_id;
$types .= "i";

// Execute the update
$stmt = $db_connection->prepare($update_query);
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    error_log("Gym updated successfully");
    header("Location: ../pages/manage_gyms.php?success=updated");
} else {
    error_log("Failed to update gym: " . $stmt->error);
    header("Location: ../pages/manage_gyms.php?error=update_failed");
}
exit();