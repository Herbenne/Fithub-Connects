<?php
session_start();
require_once __DIR__ . "/../config/database.php";

// Debug output (disabled in production)
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

// Check admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../pages/manage_members.php?error=access_denied");
    exit();
}

// Verify POST request and member_id
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['member_id'])) {
    header("Location: ../pages/manage_members.php?error=invalid_request");
    exit();
}

$member_id = intval($_POST['member_id']);

if ($member_id <= 0) {
    header("Location: ../pages/manage_members.php?error=invalid_member");
    exit();
}

// Verify that this member belongs to the admin's gym
$verify_query = "SELECT gm.user_id, gm.gym_id FROM gym_members gm 
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

$member = $result->fetch_assoc();
$user_id = $member['user_id'];
$gym_id = $member['gym_id'];

// Begin transaction
$db_connection->begin_transaction();

try {
    // Delete the membership
    $delete_query = "DELETE FROM gym_members WHERE id = ?";
    $stmt = $db_connection->prepare($delete_query);
    
    if (!$stmt) {
        throw new Exception("Failed to prepare delete statement: " . $db_connection->error);
    }
    
    $stmt->bind_param("i", $member_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to delete membership: " . $stmt->error);
    }

    // Check if the user has any remaining active memberships
    $check_query = "SELECT COUNT(*) as active_count FROM gym_members WHERE user_id = ? AND status = 'active'";
    $stmt = $db_connection->prepare($check_query);
    
    if (!$stmt) {
        throw new Exception("Failed to prepare count statement: " . $db_connection->error);
    }
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $active_count = $stmt->get_result()->fetch_assoc()['active_count'];

    // If no more active memberships, change user role back to 'user'
    if ($active_count == 0) {
        $update_query = "UPDATE users SET role = 'user' WHERE id = ? AND role = 'member'";
        $stmt = $db_connection->prepare($update_query);
        
        if (!$stmt) {
            throw new Exception("Failed to prepare update statement: " . $db_connection->error);
        }
        
        $stmt->bind_param("i", $user_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update user role: " . $stmt->error);
        }
    }

    $db_connection->commit();
    header("Location: ../pages/manage_members.php?success=member_removed");
    exit();

} catch (Exception $e) {
    $db_connection->rollback();
    header("Location: ../pages/manage_members.php?error=" . urlencode($e->getMessage()));
    exit();
}