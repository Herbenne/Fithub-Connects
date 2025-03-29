<?php
session_start();
include '../config/database.php';
include '../includes/auth.php';

// Ensure only superadmin can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: ../pages/manage_gyms.php?error=access_denied");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gym_id'])) {
    $gym_id = $_POST['gym_id'];

    // Start transaction
    $db_connection->begin_transaction();

    try {
        // Update gym status to rejected
        $query = "UPDATE gyms SET status = 'rejected' WHERE gym_id = ? AND status = 'pending'";
        $stmt = $db_connection->prepare($query);
        $stmt->bind_param("i", $gym_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Error rejecting gym application");
        }

        $db_connection->commit();
        header("Location: ../pages/manage_gyms.php?success=rejected");
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
