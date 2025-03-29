<?php
session_start();
require_once __DIR__ . "/../config/database.php";

// Debug output
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

// Get user_id from gym_members
$query = "SELECT user_id FROM gym_members WHERE id = ?";
$stmt = $db_connection->prepare($query);
$stmt->bind_param("i", $member_id);
$stmt->execute();
$result = $stmt->get_result();
$member = $result->fetch_assoc();

if ($member) {
    $db_connection->begin_transaction();
    
    try {
        // Delete membership
        $delete_query = "DELETE FROM gym_members WHERE id = ?";
        $stmt = $db_connection->prepare($delete_query);
        $stmt->bind_param("i", $member_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to delete membership");
        }

        // Update user role
        $update_query = "UPDATE users SET role = 'user' WHERE id = ?";
        $stmt = $db_connection->prepare($update_query);
        $stmt->bind_param("i", $member['user_id']);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update user role");
        }

        $db_connection->commit();
        header("Location: ../pages/manage_members.php?success=member_removed");
        exit();

    } catch (Exception $e) {
        $db_connection->rollback();
        header("Location: ../pages/manage_members.php?error=" . urlencode($e->getMessage()));
        exit();
    }
} else {
    header("Location: ../pages/manage_members.php?error=member_not_found");
    exit();
}
