<?php
session_start();
include '../config/database.php';
require_once '../includes/AWSFileManager.php';

// Enhanced error reporting
ini_set('display_errors', 0);
error_reporting(E_ALL);
error_log("==== Starting gym application submission process ====");

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../pages/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
error_log("User ID: " . $user_id);

// Check if user already has a pending gym application
$check_query = "SELECT * FROM gyms WHERE owner_id = ? AND status = 'pending'";
$stmt = $db_connection->prepare($check_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    error_log("User already has a pending gym application");
    header("Location: ../pages/dashboard.php?error=existing_application");
    exit();
}

// Also check if user already has an approved gym
$check_query = "SELECT * FROM gyms WHERE owner_id = ? AND status = 'approved'";
$stmt = $db_connection->prepare($check_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    error_log("User already has an approved gym");
    header("Location: ../pages/dashboard.php?error=existing_approved_gym");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("Processing POST request for gym application");
    
    $gym_name = $_POST['gym_name'] ?? '';
    $gym_location = $_POST['gym_location'] ?? '';
    $gym_phone_number = $_POST['gym_phone_number'] ?? '';
    $gym_description = $_POST['gym_description'] ?? '';
    $gym_amenities = $_POST['gym_amenities'] ?? '';
    
    // Validate required fields
    if (empty($gym_name) || empty($gym_location) || empty($gym_phone_number) || 
        empty($gym_description) || empty($gym_amenities)) {
        error_log("Missing required fields in gym application");
        header("Location: ../pages/dashboard.php?error=missing_fields");
        exit();
    }
    
    // Validate legal documents
    $required_documents = ['business_permit', 'valid_id', 'tax_certificate'];
    $missing_documents = [];
    
    foreach ($required_documents as $doc_type) {
        if (!isset($_FILES[$doc_type]) || $_FILES[$doc_type]['error'] !== 0) {
            $missing_documents[] = str_replace('_', ' ', $doc_type);
        }
    }
    
    if (!empty($missing_documents)) {
        $message = "Missing required documents: " . implode(', ', $missing_documents);
        error_log($message);
        header("Location: ../pages/dashboard.php?error=missing_documents&message=" . urlencode($message));
        exit();
    }
    
    // Initialize AWS file manager
    $awsManager = new AWSFileManager();

    // Start transaction
    $db_connection->begin_transaction();
    
    try {
        // Create initial gym record
        $query = "INSERT INTO gyms (
            gym_name, gym_location, gym_phone_number, 
            gym_description, gym_amenities, owner_id, status,
            created_at, legal_documents
        ) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW(), NULL)";
        
        $stmt = $db_connection->prepare($query);
        if (!$stmt) {
            throw new Exception("Failed to prepare initial query: " . $db_connection->error);
        }
        
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
            throw new Exception("Failed to create initial gym record: " . $stmt->error);
        }
        
        $gym_id = $db_connection->insert_id;
        error_log("Created initial gym record with ID: " . $gym_id);
        
        // Upload the thumbnail if provided
        $gym_thumbnail = null;
        if (isset($_FILES['gym_thumbnail']) && $_FILES['gym_thumbnail']['error'] === 0) {
            $tmp_path = $_FILES['gym_thumbnail']['tmp_name'];
            $filename = $_FILES['gym_thumbnail']['name'];
            
            // Using AWS
            if (USE_AWS) {
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
                
                error_log("Updated gym record with thumbnail: " . $gym_thumbnail);
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
                    
                    // Update the record with the thumbnail path
                    $update_query = "UPDATE gyms SET gym_thumbnail = ? WHERE gym_id = ?";
                    $stmt = $db_connection->prepare($update_query);
                    $stmt->bind_param("si", $gym_thumbnail, $gym_id);
                    
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to update gym with thumbnail");
                    }
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
        
        // Update equipment images in database
        if (!empty($equipment_images)) {
            $equipment_json = json_encode($equipment_images);
            $update_query = "UPDATE gyms SET equipment_images = ? WHERE gym_id = ?";
            $stmt = $db_connection->prepare($update_query);
            $stmt->bind_param("si", $equipment_json, $gym_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to update gym with equipment images");
            }
            
            error_log("Updated gym record with equipment images: " . count($equipment_images));
        }
        
        // Handle legal document uploads - business permit, valid ID, tax certificate
        $legal_documents = [];
        
        foreach ($required_documents as $doc_type) {
            if (isset($_FILES[$doc_type]) && $_FILES[$doc_type]['error'] === 0) {
                $tmp_path = $_FILES[$doc_type]['tmp_name'];
                $filename = $_FILES[$doc_type]['name'];
                
                if (USE_AWS) {
                    // Upload to AWS pending documents folder
                    $result = $awsManager->uploadPendingLegalDocument($tmp_path, $user_id, $filename, $doc_type);
                    
                    if ($result) {
                        $legal_documents[$doc_type] = $result;
                        error_log("Uploaded legal document: $doc_type to AWS");
                    } else {
                        throw new Exception("Failed to upload {$doc_type}");
                    }
                } else {
                    // Legacy local file system upload
                    $upload_dir = "../uploads/legal_documents/pending/user_{$user_id}/";
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    $file_extension = pathinfo($_FILES[$doc_type]['name'], PATHINFO_EXTENSION);
                    $filename = $doc_type . '_' . time() . '.' . $file_extension;
                    $target_path = $upload_dir . $filename;
                    
                    if (move_uploaded_file($_FILES[$doc_type]['tmp_name'], $target_path)) {
                        $legal_documents[$doc_type] = 'uploads/legal_documents/pending/user_' . $user_id . '/' . $filename;
                        error_log("Uploaded legal document: $doc_type to local filesystem");
                    }
                }
            }
        }

        // Handle legal document uploads
        $legal_documents = [];
        foreach ($required_documents as $doc_type) {
            if (isset($_FILES[$doc_type]) && $_FILES[$doc_type]['error'] === 0) {
                $tmp_path = $_FILES[$doc_type]['tmp_name'];
                $filename = $_FILES[$doc_type]['name'];
                
                if (USE_AWS) {
                    // Use AWS storage
                    require_once '../includes/AWSFileManager.php';
                    $awsManager = new AWSFileManager();
                    
                    // Upload to AWS pending documents folder
                    $result = $awsManager->uploadPendingLegalDocument($tmp_path, $user_id, $filename, $doc_type);
                    
                    if ($result) {
                        $legal_documents[$doc_type] = $result;
                        error_log("Successfully uploaded legal document: {$doc_type} to AWS");
                    } else {
                        throw new Exception("Failed to upload {$doc_type} to AWS");
                    }
                } else {
                    $base_dir = $_SERVER['DOCUMENT_ROOT'] . '/Fithub-Connects/uploads/legal_documents/pending/';
                    $upload_dir = $base_dir . "user_{$user_id}/";
                    if (!file_exists($upload_dir)) {
                        if (!mkdir($upload_dir, 0777, true)) {
                            error_log("Failed to create directory: " . $upload_dir);
                            throw new Exception("Failed to create upload directory");
                        }
                    chmod($upload_dir, 0777); // Ensure it's writable
                }
                
                $file_extension = pathinfo($_FILES[$doc_type]['name'], PATHINFO_EXTENSION);
                $filename = $doc_type . '_' . time() . '.' . $file_extension;
                $target_path = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES[$doc_type]['tmp_name'], $target_path)) {
                    $legal_documents[$doc_type] = '../uploads/legal_documents/pending/user_' . $user_id . '/' . $filename;
                    error_log("Uploaded legal document: $doc_type to $target_path");
                } else {
                    throw new Exception("Failed to upload " . $doc_type);
                }
            }
        }
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
            
            error_log("Updated gym record with legal documents");
        }

        $db_connection->commit();
        error_log("Successfully submitted gym application with ID: " . $gym_id);
        header("Location: ../pages/dashboard.php?success=application_submitted");
        exit();
    } catch (Exception $e) {
        $db_connection->rollback();
        error_log("ERROR in gym application: " . $e->getMessage());
        header("Location: ../pages/dashboard.php?error=application_failed&message=" . urlencode($e->getMessage()));
        exit();
    }
}

header("Location: ../pages/dashboard.php");
exit();