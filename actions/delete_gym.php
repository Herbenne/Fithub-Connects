<?php
session_start();
include '../config/database.php';

// Ensure user is superadmin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: ../pages/login.php");
    exit();
}

if (isset($_GET['gym_id'])) {
    $gym_id = $_GET['gym_id'];

    // Start transaction
    $db_connection->begin_transaction();

    try {
        // Delete associated records first
        // Delete reviews
        $db_connection->query("DELETE FROM gym_reviews WHERE gym_id = $gym_id");
        
        // Delete membership plans
        $db_connection->query("DELETE FROM membership_plans WHERE gym_id = $gym_id");
        
        // Delete gym members
        $db_connection->query("DELETE FROM gym_members WHERE gym_id = $gym_id");
        
        // Finally delete the gym
        $query = "DELETE FROM gyms WHERE gym_id = ?";
        $stmt = $db_connection->prepare($query);
        $stmt->bind_param("i", $gym_id);
        
        if ($stmt->execute()) {
            // If everything succeeded, commit the transaction
            $db_connection->commit();
            header("Location: ../pages/manage_gyms.php?success=delete");
        } else {
            throw new Exception("Failed to delete gym");
        }
    } catch (Exception $e) {
        // If anything failed, rollback the transaction
        $db_connection->rollback();
        error_log("Error deleting gym: " . $e->getMessage());
        header("Location: ../pages/manage_gyms.php?error=delete");
    }
} else {
    header("Location: ../pages/manage_gyms.php?error=invalid");
}
exit();
?>