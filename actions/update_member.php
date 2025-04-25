<?php
session_start();
require_once __DIR__ . "/../config/database.php";

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../pages/login.php?error=unauthorized");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $member_id = isset($_POST['member_id']) ? intval($_POST['member_id']) : 0;
    $plan_id = isset($_POST['plan_id']) ? intval($_POST['plan_id']) : 0;
    $end_date = isset($_POST['end_date']) ? $_POST['end_date'] : null;
    $status = isset($_POST['status']) ? $_POST['status'] : 'active';

    // Validate input
    if (!$member_id || !$plan_id || !$end_date) {
        header("Location: ../pages/manage_members.php?error=missing_fields");
        exit();
    }

    // Verify that the member belongs to the admin's gym
    $verify_query = "SELECT gm.* FROM gym_members gm 
                    JOIN gyms g ON gm.gym_id = g.gym_id 
                    WHERE gm.id = ? AND g.owner_id = ?";
    $stmt = $db_connection->prepare($verify_query);
    
    if (!$stmt) {
        header("Location: ../pages/manage_members.php?error=" . urlencode($db_connection->error));
        exit();
    }
    
    $stmt->bind_param("ii", $member_id, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        header("Location: ../pages/manage_members.php?error=access_denied");
        exit();
    }
    
    // Get the membership data
    $member_data = $result->fetch_assoc();
    
    // Start transaction
    $db_connection->begin_transaction();
    
    try {
        // Update membership
        $update_query = "UPDATE gym_members 
                      SET plan_id = ?, 
                          end_date = ?,
                          status = ?
                      WHERE id = ?";
        
        $stmt = $db_connection->prepare($update_query);
        
        if (!$stmt) {
            throw new Exception($db_connection->error);
        }
        
        $stmt->bind_param("issi", $plan_id, $end_date, $status, $member_id);
        
        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }
        
        // If status is changed to inactive, handle user role changes if needed
        if ($status === 'inactive' && $member_data['status'] === 'active') {
            // Check if this is the user's only active membership
            $check_query = "SELECT COUNT(*) as active_count FROM gym_members 
                          WHERE user_id = ? AND status = 'active' AND id != ?";
            
            $stmt = $db_connection->prepare($check_query);
            
            if (!$stmt) {
                throw new Exception($db_connection->error);
            }
            
            $stmt->bind_param("ii", $member_data['user_id'], $member_id);
            $stmt->execute();
            $active_result = $stmt->get_result();
            $active_count = $active_result->fetch_assoc()['active_count'];
            
            // If this was their only active membership, change role back to user
            if ($active_count === 0) {
                $update_user_query = "UPDATE users SET role = 'user' WHERE id = ? AND role = 'member'";
                $stmt = $db_connection->prepare($update_user_query);
                
                if (!$stmt) {
                    throw new Exception($db_connection->error);
                }
                
                $stmt->bind_param("i", $member_data['user_id']);
                $stmt->execute();
            }
        } 
        // If status is changed to active from inactive, make sure user role is 'member'
        else if ($status === 'active' && $member_data['status'] !== 'active') {
            $update_user_query = "UPDATE users SET role = 'member' WHERE id = ? AND role = 'user'";
            $stmt = $db_connection->prepare($update_user_query);
            
            if (!$stmt) {
                throw new Exception($db_connection->error);
            }
            
            $stmt->bind_param("i", $member_data['user_id']);
            $stmt->execute();
        }
        
        $db_connection->commit();
        header("Location: ../pages/manage_members.php?success=1");
        exit();
        
    } catch (Exception $e) {
        $db_connection->rollback();
        header("Location: ../pages/manage_members.php?error=" . urlencode($e->getMessage()));
        exit();
    }
} else {
    header("Location: ../pages/manage_members.php");
    exit();
}