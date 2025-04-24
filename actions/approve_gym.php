<?php
session_start();
include '../config/database.php';
include '../includes/auth.php';
require_once '../includes/AWSFileManager.php';

// Check if user is superadmin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    die("Access Denied");
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['gym_id'])) {
    $gym_id = $_POST['gym_id'];

    // Initialize AWS file manager
    $awsManager = null;
    if (USE_AWS) {
        $awsManager = new AWSFileManager();
    }

    // Start transaction
    $db_connection->begin_transaction();

    try {
        // Get gym details before update
        $gym_query = "SELECT gym_name, owner_id, legal_documents FROM gyms WHERE gym_id = ? AND status = 'pending'";
        $stmt = $db_connection->prepare($gym_query);
        $stmt->bind_param("i", $gym_id);
        $stmt->execute();
        $gym_result = $stmt->get_result();
        
        if ($gym_result->num_rows === 0) {
            throw new Exception("Gym not found or already approved");
        }
        
        $gym_data = $gym_result->fetch_assoc();
        $gym_name = $gym_data['gym_name'];
        $owner_id = $gym_data['owner_id'];
        $legal_documents = json_decode($gym_data['legal_documents'] ?? '{}', true);

        // Create gym folder structure
        if (USE_AWS && $awsManager) {
            // Create gym folder structure
            $folderPath = $awsManager->createGymFolder($gym_id, $gym_name);
            
            if (!$folderPath) {
                throw new Exception("Failed to create gym folders in AWS");
            }
            
            // Move legal documents from pending to approved location
            if (!empty($legal_documents)) {
                // First, create a copy of the documents in the new location
                $approved_documents = [];
                
                foreach ($legal_documents as $doc_type => $doc_path) {
                    // Create the destination path in the approved gym folder
                    $destination_path = "uploads/gyms/gym{$gym_id}_{$gym_name}/legal_documents/" . basename($doc_path);
                    
                    // Copy the document to the new location
                    $result = $awsManager->copyObject($doc_path, $destination_path);
                    
                    if ($result) {
                        // Add the new path to approved documents
                        $approved_documents[$doc_type] = $destination_path;
                        
                        // Delete the original document from pending location
                        $awsManager->deleteFile($doc_path);
                    } else {
                        // Log warning but continue
                        error_log("Warning: Could not move legal document {$doc_type}. Original path: {$doc_path}");
                    }
                }
                
                // Update the gym record with the new document paths
                if (!empty($approved_documents)) {
                    $approved_docs_json = json_encode($approved_documents);
                    $update_docs = "UPDATE gyms SET legal_documents = ? WHERE gym_id = ?";
                    $stmt = $db_connection->prepare($update_docs);
                    $stmt->bind_param("si", $approved_docs_json, $gym_id);
                    $stmt->execute();
                }
                
                // Remove the pending folder for this user
                $awsManager->deleteFolder("uploads/legal_documents/pending/user_{$owner_id}/");
            }
            
            // Log success
            error_log("Created AWS folder structure for gym: $gym_id - $gym_name");
        } else if (!USE_AWS) {
            // For local filesystem: Create necessary folders and move documents
            $base_dir = "../uploads/gyms/gym{$gym_id}_{$gym_name}/";
            $legal_dir = $base_dir . "legal_documents/";
            $pending_dir = "../uploads/legal_documents/pending/user_{$owner_id}/";
            
            // Create directories if they don't exist
            if (!file_exists($base_dir)) {
                mkdir($base_dir, 0777, true);
            }
            if (!file_exists($legal_dir)) {
                mkdir($legal_dir, 0777, true);
            }
            
            // Move legal documents if they exist
            if (file_exists($pending_dir)) {
                $files = glob($pending_dir . "*");
                foreach ($files as $file) {
                    if (is_file($file)) {
                        $filename = basename($file);
                        $new_path = $legal_dir . $filename;
                        rename($file, $new_path);
                    }
                }
                
                // Remove empty pending directory
                rmdir($pending_dir);
            }
        }

        // Update gym status
        $update_gym = "UPDATE gyms SET status = 'approved' WHERE gym_id = ? AND status = 'pending'";
        $stmt = $db_connection->prepare($update_gym);
        $stmt->bind_param("i", $gym_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Error updating gym status");
        }

        // Update user role to admin and assign gym
        $update_user = "UPDATE users SET role = 'admin' WHERE id = ? AND role != 'admin'";
        $stmt = $db_connection->prepare($update_user);
        $stmt->bind_param("i", $owner_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update user role");
        }

        // Verify the role was updated
        $check_role = "SELECT role FROM users WHERE id = ?";
        $stmt = $db_connection->prepare($check_role);
        $stmt->bind_param("i", $owner_id);
        $stmt->execute();
        $user_role = $stmt->get_result()->fetch_assoc();

        if ($user_role['role'] !== 'admin') {
            throw new Exception("Failed to verify user role update");
        }

        $db_connection->commit();
        header("Location: ../pages/manage_gyms.php?success=1");
        exit();

    } catch (Exception $e) {
        $db_connection->rollback();
        error_log("Error approving gym: " . $e->getMessage());
        header("Location: ../pages/manage_gyms.php?error=" . urlencode($e->getMessage()));
        exit();
    }
} else {
    header("Location: ../pages/manage_gyms.php?error=invalid_request");
    exit();
}