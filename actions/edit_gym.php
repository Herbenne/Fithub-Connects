<?php
session_start();
include '../config/database.php';
require_once '../includes/AWSFileManager.php';

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

// Initialize AWS file manager
$awsManager = null;
if (USE_AWS) {
    $awsManager = new AWSFileManager();
}

// Start transaction
$db_connection->begin_transaction();

try {
    // First, get current gym data
    $get_gym_query = "SELECT * FROM gyms WHERE gym_id = ?";
    $stmt = $db_connection->prepare($get_gym_query);
    if (!$stmt) {
        throw new Exception("Error preparing statement: " . $db_connection->error);
    }
    $stmt->bind_param("i", $gym_id);
    $stmt->execute();
    $current_gym = $stmt->get_result()->fetch_assoc();
    
    if (!$current_gym) {
        throw new Exception("Gym not found");
    }
    
    $gym_name = $_POST['gym_name'];

    // Handle thumbnail upload
    $gym_thumbnail = $current_gym['gym_thumbnail'];
    if (!empty($_FILES['gym_thumbnail']['name']) && $_FILES['gym_thumbnail']['error'] === UPLOAD_ERR_OK) {
        $tmp_path = $_FILES['gym_thumbnail']['tmp_name'];
        $filename = $_FILES['gym_thumbnail']['name'];
        
        if (USE_AWS && $awsManager) {
            // Create gym folder structure if it doesn't exist
            $awsManager->createGymFolder($gym_id, $gym_name);
            
            // Upload thumbnail to AWS S3
            $new_thumbnail = $awsManager->uploadGymThumbnail($tmp_path, $gym_id, $filename);
            
            if ($new_thumbnail) {
                // If there was an old thumbnail, delete it
                if (!empty($gym_thumbnail)) {
                    $awsManager->deleteFile($gym_thumbnail);
                }
                $gym_thumbnail = $new_thumbnail;
                error_log("Thumbnail uploaded successfully to AWS: " . $gym_thumbnail);
            } else {
                throw new Exception("Failed to upload thumbnail to AWS S3");
            }
        } else {
            // Legacy code for local storage
            $target_dir = "../assets/images/";
            $new_filename = 'Screenshot (' . uniqid() . ').' . pathinfo($filename, PATHINFO_EXTENSION);
            $target_file = $target_dir . $new_filename;
            
            if (!move_uploaded_file($tmp_path, $target_file)) {
                throw new Exception("Failed to move uploaded thumbnail locally");
            }
            
            $gym_thumbnail = '../assets/images/' . $new_filename;
            error_log("Thumbnail uploaded successfully locally: " . $gym_thumbnail);
        }
    }

    // Handle equipment images
    $equipment_images = [];
    if (!empty($current_gym['equipment_images'])) {
        $equipment_images = json_decode($current_gym['equipment_images'], true) ?: [];
    }
    
    // Add existing equipment images if specified
    if (!empty($_POST['existing_equipment']) && is_array($_POST['existing_equipment'])) {
        $equipment_images = $_POST['existing_equipment'];
    }

    // Handle new equipment images
    if (!empty($_FILES['equipment_images']['name'][0])) {
        foreach ($_FILES['equipment_images']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['equipment_images']['error'][$key] === UPLOAD_ERR_OK) {
                $filename = $_FILES['equipment_images']['name'][$key];
                
                if (USE_AWS && $awsManager) {
                    // Upload equipment image to AWS S3
                    $new_image = $awsManager->uploadEquipmentImages($tmp_name, $gym_id, $filename);
                    
                    if ($new_image) {
                        $equipment_images[] = $new_image;
                        error_log("Equipment image uploaded successfully to AWS: " . $new_image);
                    } else {
                        error_log("Failed to upload equipment image to AWS S3");
                    }
                } else {
                    // Legacy code for local storage
                    $target_dir = "../assets/images/";
                    $new_filename = 'Screenshot (' . uniqid() . ').' . pathinfo($filename, PATHINFO_EXTENSION);
                    $target_file = $target_dir . $new_filename;
                    
                    if (move_uploaded_file($tmp_name, $target_file)) {
                        $equipment_images[] = '../assets/images/' . $new_filename;
                        error_log("Equipment image uploaded successfully locally: " . '../assets/images/' . $new_filename);
                    } else {
                        error_log("Failed to move equipment image locally. Upload error: " . $_FILES['equipment_images']['error'][$key]);
                    }
                }
            }
        }
    }

    // Remove equipment images if requested
    if (!empty($_POST['remove_equipment']) && is_array($_POST['remove_equipment'])) {
        foreach ($_POST['remove_equipment'] as $index) {
            if (isset($equipment_images[$index])) {
                // Delete from AWS if using AWS
                if (USE_AWS && $awsManager) {
                    $awsManager->deleteFile($equipment_images[$index]);
                    error_log("Deleted equipment image from AWS: " . $equipment_images[$index]);
                }
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

    // Add thumbnail to update if changed
    if ($gym_thumbnail !== $current_gym['gym_thumbnail']) {
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
    if (!$stmt) {
        throw new Exception("Error preparing update statement: " . $db_connection->error);
    }
    $stmt->bind_param($types, ...$params);

    if (!$stmt->execute()) {
        throw new Exception("Error executing update: " . $stmt->error);
    }

    $db_connection->commit();
    error_log("Gym updated successfully");
    header("Location: ../pages/manage_gyms.php?success=updated");
} catch (Exception $e) {
    $db_connection->rollback();
    error_log("Failed to update gym: " . $e->getMessage());
    header("Location: ../pages/manage_gyms.php?error=update_failed&message=" . urlencode($e->getMessage()));
}
exit();