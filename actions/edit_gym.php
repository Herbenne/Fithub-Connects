<?php
session_start();
include '../config/database.php';

// Ensure user is superadmin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: ../pages/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gym_id = $_POST['gym_id'];
    $gym_name = $_POST['gym_name'];
    $gym_location = $_POST['gym_location'];
    $gym_phone_number = $_POST['gym_phone_number'];
    $gym_description = $_POST['gym_description'];
    $gym_amenities = $_POST['gym_amenities'];
    $owner_id = $_POST['owner_id'];

    // Start building the update query
    $query = "UPDATE gyms SET 
              gym_name = ?, 
              gym_location = ?, 
              gym_phone_number = ?, 
              gym_description = ?, 
              gym_amenities = ?, 
              owner_id = ?";
    
    $params = [$gym_name, $gym_location, $gym_phone_number, 
               $gym_description, $gym_amenities, $owner_id];
    $types = "sssssi";

    // Handle gym thumbnail upload if provided
    if (isset($_FILES['gym_thumbnail']) && $_FILES['gym_thumbnail']['error'] === 0) {
        $upload_dir = '../uploads/gym_thumbnails/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_extension = pathinfo($_FILES['gym_thumbnail']['name'], PATHINFO_EXTENSION);
        $filename = 'gym_' . $gym_id . '_' . time() . '.' . $file_extension;
        $target_path = $upload_dir . $filename;

        if (move_uploaded_file($_FILES['gym_thumbnail']['tmp_name'], $target_path)) {
            $query .= ", gym_thumbnail = ?";
            $params[] = 'uploads/gym_thumbnails/' . $filename;
            $types .= "s";
        }
    }

    // Handle equipment images
    $equipment_images = [];

    // Keep existing images that weren't removed
    if (isset($_POST['existing_equipment'])) {
        foreach ($_POST['existing_equipment'] as $index => $image) {
            if (!isset($_POST['remove_equipment']) || 
                !in_array($index, $_POST['remove_equipment'])) {
                $equipment_images[] = $image;
            }
        }
    }

    // Handle new equipment images
    if (isset($_FILES['equipment_images'])) {
        $upload_dir = '../uploads/equipment_images/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        foreach ($_FILES['equipment_images']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['equipment_images']['error'][$key] === 0) {
                $file_extension = pathinfo($_FILES['equipment_images']['name'][$key], PATHINFO_EXTENSION);
                $filename = 'equipment_' . $gym_id . '_' . time() . '_' . $key . '.' . $file_extension;
                $target_path = $upload_dir . $filename;

                if (move_uploaded_file($tmp_name, $target_path)) {
                    $equipment_images[] = 'uploads/equipment_images/' . $filename;
                }
            }
        }
    }

    // Add equipment images to update query
    $equipment_json = json_encode($equipment_images);
    $query .= ", equipment_images = ?";
    $params[] = $equipment_json;
    $types .= "s";

    // Complete the query with WHERE clause
    $query .= " WHERE gym_id = ?";
    $params[] = $gym_id;
    $types .= "i";

    // Execute the update
    $stmt = $db_connection->prepare($query);
    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        header("Location: ../pages/manage_gyms.php?success=update");
    } else {
        error_log("Failed to update gym: " . $db_connection->error);
        header("Location: ../pages/manage_gyms.php?error=update");
    }
    exit();
}

header("Location: ../pages/manage_gyms.php");
exit();