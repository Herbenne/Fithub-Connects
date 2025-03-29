<?php
session_start();
include '../config/database.php';
include '../includes/auth.php';

// Check if user is superadmin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    die("Access Denied");
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['gym_id'])) {
    $gym_id = $_POST['gym_id'];

    // Start transaction
    $db_connection->begin_transaction();

    try {
        // Update gym status
        $update_gym = "UPDATE gyms SET status = 'approved' WHERE gym_id = ? AND status = 'pending'";
        $stmt = $db_connection->prepare($update_gym);
        $stmt->bind_param("i", $gym_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Error updating gym status");
        }

        // Get owner_id from gym
        $get_owner = "SELECT owner_id FROM gyms WHERE gym_id = ?";
        $stmt = $db_connection->prepare($get_owner);
        $stmt->bind_param("i", $gym_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $gym = $result->fetch_assoc();

        // Update user role to admin and assign gym
        $update_user = "UPDATE users SET role = 'admin' WHERE id = ? AND role != 'admin'";
        $stmt = $db_connection->prepare($update_user);
        $stmt->bind_param("i", $gym['owner_id']);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update user role");
        }

        // Verify the role was updated
        $check_role = "SELECT role FROM users WHERE id = ?";
        $stmt = $db_connection->prepare($check_role);
        $stmt->bind_param("i", $gym['owner_id']);
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
        header("Location: ../pages/manage_gyms.php?error=" . urlencode($e->getMessage()));
        exit();
    }
} else {
    header("Location: ../pages/manage_gyms.php?error=invalid_request");
    exit();
}
