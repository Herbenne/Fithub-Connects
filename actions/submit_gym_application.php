<?php
session_start();
include '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../pages/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $gym_name = $_POST['gym_name'];
    $gym_location = $_POST['gym_location'];
    $gym_phone_number = $_POST['gym_phone_number'];
    $gym_description = $_POST['gym_description'];
    $gym_amenities = $_POST['gym_amenities'];
    
    // Start transaction
    $db_connection->begin_transaction();

    try {
        // Handle gym thumbnail upload
        $gym_thumbnail = null;
        if (isset($_FILES['gym_thumbnail']) && $_FILES['gym_thumbnail']['error'] === 0) {
            $upload_dir = '../uploads/gym_thumbnails/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $file_extension = pathinfo($_FILES['gym_thumbnail']['name'], PATHINFO_EXTENSION);
            $filename = 'gym_' . time() . '_' . uniqid() . '.' . $file_extension;
            $target_path = $upload_dir . $filename;

            if (move_uploaded_file($_FILES['gym_thumbnail']['tmp_name'], $target_path)) {
                $gym_thumbnail = 'uploads/gym_thumbnails/' . $filename;
            }
        }

        // Handle equipment images
        $equipment_images = [];
        if (isset($_FILES['equipment_images'])) {
            $upload_dir = '../uploads/equipment_images/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            foreach ($_FILES['equipment_images']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['equipment_images']['error'][$key] === 0) {
                    $file_extension = pathinfo($_FILES['equipment_images']['name'][$key], PATHINFO_EXTENSION);
                    $filename = 'equipment_' . time() . '_' . uniqid() . '_' . $key . '.' . $file_extension;
                    $target_path = $upload_dir . $filename;

                    if (move_uploaded_file($tmp_name, $target_path)) {
                        $equipment_images[] = 'uploads/equipment_images/' . $filename;
                    }
                }
            }
        }

        // Insert gym application
        $query = "INSERT INTO gyms (
                    gym_name, gym_location, gym_phone_number, 
                    gym_description, gym_amenities, owner_id, 
                    gym_thumbnail, equipment_images, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')";

        $stmt = $db_connection->prepare($query);
        $equipment_json = json_encode($equipment_images);
        
        $stmt->bind_param(
            "ssssssss",
            $gym_name,
            $gym_location,
            $gym_phone_number,
            $gym_description,
            $gym_amenities,
            $user_id,
            $gym_thumbnail,
            $equipment_json
        );

        if ($stmt->execute()) {
            $db_connection->commit();
            header("Location: ../pages/dashboard.php?success=application_submitted");
        } else {
            throw new Exception("Failed to submit application");
        }

    } catch (Exception $e) {
        $db_connection->rollback();
        error_log("Error submitting gym application: " . $e->getMessage());
        header("Location: ../pages/dashboard.php?error=application_failed");
    }
    exit();
}

header("Location: ../pages/dashboard.php");
exit();