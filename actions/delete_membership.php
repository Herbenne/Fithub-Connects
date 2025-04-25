<?php
session_start();
include '../config/database.php';

// Set proper headers for JSON response
header('Content-Type: application/json');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if (isset($_POST['id'])) {
    $membership_id = intval($_POST['id']);
    
    // Start transaction
    $db_connection->begin_transaction();
    
    try {
        // Get user_id from gym_members before deleting
        $get_user_query = "SELECT user_id FROM gym_members WHERE id = ?";
        $stmt = $db_connection->prepare($get_user_query);
        if (!$stmt) {
            throw new Exception("Error preparing user query: " . $db_connection->error);
        }
        $stmt->bind_param("i", $membership_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_data = $result->fetch_assoc();
        $stmt->close();
        
        if (!$user_data) {
            throw new Exception("Membership not found");
        }
        
        $user_id = $user_data['user_id'];
        
        // Delete the membership
        $delete_query = "DELETE FROM gym_members WHERE id = ?";
        $stmt = $db_connection->prepare($delete_query);
        if (!$stmt) {
            throw new Exception("Error preparing delete query: " . $db_connection->error);
        }
        $stmt->bind_param("i", $membership_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to delete membership: " . $stmt->error);
        }
        $stmt->close();
        
        // Check if the user has any remaining memberships
        $check_query = "SELECT COUNT(*) as count FROM gym_members WHERE user_id = ?";
        $stmt = $db_connection->prepare($check_query);
        if (!$stmt) {
            throw new Exception("Error preparing count query: " . $db_connection->error);
        }
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $count_data = $result->fetch_assoc();
        $stmt->close();
        
        // If no more memberships, change user role back to 'user'
        if ($count_data['count'] == 0) {
            $update_user = "UPDATE users SET role = 'user' WHERE id = ? AND role = 'member'";
            $stmt = $db_connection->prepare($update_user);
            if (!$stmt) {
                throw new Exception("Error preparing user update: " . $db_connection->error);
            }
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();
        }
        
        $db_connection->commit();
        echo json_encode(['success' => true, 'message' => 'Membership deleted successfully']);
        
    } catch (Exception $e) {
        $db_connection->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'No membership ID provided']);
}

// Close database connection
if (isset($db_connection)) {
    $db_connection->close();
}