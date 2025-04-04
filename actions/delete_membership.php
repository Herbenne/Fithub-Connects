<?php
session_start();
include '../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if (isset($_POST['id'])) {
    $membership_id = intval($_POST['id']);
    
    $query = "DELETE FROM gym_members WHERE id = ?";
    $stmt = $db_connection->prepare($query);
    $stmt->bind_param("i", $membership_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete membership']);
    }
    
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'No membership ID provided']);
}