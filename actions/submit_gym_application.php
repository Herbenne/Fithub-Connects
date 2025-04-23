<?php
// File: actions/submit_gym_application.php (Updated with AWS Integration)
session_start();
include '../config/database.php';
require_once '../includes/AWSFileManager.php';

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
    
    // Initialize AWS file manager
    $awsManager = new AWSFileManager();
    
    // Start transaction
    $db_connection->begin_transaction();

    try {
        // Handle gym thumbnail upload
        $gym_thumbnail = null;
        if (isset($_FILES['gym_thumbnail']) && $_FILES['gym_thumbnail']['error'] === 0) {
            // Using AWS
            if (USE_AWS) {
                $tmp_path = $_FILES['gym_thumbnail']['tmp_name'];
                $filename = $_FILES['gym_thumbnail']['name'];
                
                // First, create placeholder record to get gym_id
                $initial_query = "INSERT INTO gyms (
                    gym_name, gym_location, gym_phone_number, 
                    gym_description, gym_amenities, owner_id, status
                ) VALUES (?, ?, ?, ?, ?, ?, 'pending')";
                
                $stmt = $db_connection->prepare($initial_query);
                $stmt->bind_param(
                    "sssssi",
                    $gym_name,
                    $gym_location,
                    $gym_phone_number,
                    $gym_description,
                    $gym_amenities,
                    $user_id
                );
                
                if (!$stmt->execute()) {
                    throw new Exception("Failed to create initial gym record");
                }
                
                $gym_id = $db_connection->insert_id;
                
                // Create gym folder structure
                $awsManager->createGymFolder($gym_id, $gym_name);
                
                // Upload thumbnail
                $gym_thumbnail = $awsManager->uploadGymThumbnail($tmp_path, $gym_id, $filename);
                
                if (!$gym_thumbnail) {
                    throw new Exception("Failed to upload gym thumbnail");
                }
                
                // Update the record with the thumbnail path
                $update_query = "UPDATE gyms SET gym_thumbnail = ? WHERE gym_id = ?";
                $stmt = $db_connection->prepare($update_query);
                $stmt->bind_param("si", $gym_thumbnail, $gym_id);
                
                if (!$stmt->execute()) {
                    throw new Exception("Failed to update gym with thumbnail");
                }
            } else {
                // Legacy file upload to local filesystem
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
        }

        // Handle equipment images
        $equipment_images = [];
        if (isset($_FILES['equipment_images'])) {
            if (USE_AWS) {
                foreach ($_FILES['equipment_images']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['equipment_images']['error'][$key] === 0) {
                        $filename = $_FILES['equipment_images']['name'][$key];
                        $result = $awsManager->uploadEquipmentImages($tmp_name, $gym_id, $filename);
                        
                        if ($result) {
                            $equipment_images[] = $result;
                        }
                    }
                }
            } else {
                // Legacy local file system upload
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
        }
        
        // Handle legal document uploads - business permit, valid ID, etc.
        $legal_documents = [];
        $required_documents = ['business_permit', 'valid_id', 'tax_certificate'];
        
        foreach ($required_documents as $doc_type) {
            if (isset($_FILES[$doc_type]) && $_FILES[$doc_type]['error'] === 0) {
                if (USE_AWS) {
                    $tmp_path = $_FILES[$doc_type]['tmp_name'];
                    $filename = $_FILES[$doc_type]['name'];
                    
                    // These are encrypted and private
                    $result = $awsManager->uploadLegalDocument($tmp_path, $gym_id, $filename, $doc_type);
                    
                    if ($result) {
                        $legal_documents[$doc_type] = $result;
                    } else {
                        throw new Exception("Failed to upload {$doc_type}");
                    }
                } else {
                    // Legacy local file system upload
                    $upload_dir = "../uploads/legal_documents/gym_{$gym_id}/";
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    $file_extension = pathinfo($_FILES[$doc_type]['name'], PATHINFO_EXTENSION);
                    $filename = $doc_type . '_' . time() . '.' . $file_extension;
                    $target_path = $upload_dir . $filename;
                    
                    if (move_uploaded_file($_FILES[$doc_type]['tmp_name'], $target_path)) {
                        $legal_documents[$doc_type] = 'uploads/legal_documents/gym_' . $gym_id . '/' . $filename;
                    }
                }
            }
        }
        
        // If we're using AWS but don't have a gym_id yet (no thumbnail was uploaded)
        if (USE_AWS && !isset($gym_id)) {
            // Create gym record
            $query = "INSERT INTO gyms (
                gym_name, gym_location, gym_phone_number, 
                gym_description, gym_amenities, owner_id, 
                gym_thumbnail, equipment_images, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
            
            $equipment_json = json_encode($equipment_images);
            
            $stmt = $db_connection->prepare($query);
            $stmt->bind_param(
                "sssssiss",
                $gym_name,
                $gym_location,
                $gym_phone_number,
                $gym_description,
                $gym_amenities,
                $user_id,
                $gym_thumbnail,
                $equipment_json
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to submit application");
            }
            
            $gym_id = $db_connection->insert_id;
        } else if (!USE_AWS) {
            // Insert gym application with local filesystem
            $query = "INSERT INTO gyms (
                gym_name, gym_location, gym_phone_number, 
                gym_description, gym_amenities, owner_id, 
                gym_thumbnail, equipment_images, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')";

            $equipment_json = json_encode($equipment_images);
            
            $stmt = $db_connection->prepare($query);
            $stmt->bind_param(
                "sssssiss",
                $gym_name,
                $gym_location,
                $gym_phone_number,
                $gym_description,
                $gym_amenities,
                $user_id,
                $gym_thumbnail,
                $equipment_json
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to submit application");
            }
            
            $gym_id = $db_connection->insert_id;
        }
        
        // Store legal documents information
        if (!empty($legal_documents)) {
            $legal_json = json_encode($legal_documents);
            $legal_query = "UPDATE gyms SET legal_documents = ? WHERE gym_id = ?";
            $stmt = $db_connection->prepare($legal_query);
            $stmt->bind_param("si", $legal_json, $gym_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to store legal documents information");
            }
        }

        $db_connection->commit();
        header("Location: ../pages/dashboard.php?success=application_submitted");
        exit();
    } catch (Exception $e) {
        $db_connection->rollback();
        error_log("Error submitting gym application: " . $e->getMessage());
        header("Location: ../pages/dashboard.php?error=application_failed&message=" . urlencode($e->getMessage()));
        exit();
    }
}

header("Location: ../pages/dashboard.php");
exit();