<?php
// Prevent any output before headers
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();
include '../config/database.php';

// Set proper headers
header('Content-Type: application/json');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $membership_id = intval($_POST['membership_id']);
    $plan_id = intval($_POST['plan_id']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    
    // Calculate status based on end date
    $status = (strtotime($end_date) >= strtotime('today')) ? 'active' : 'expired';
    
    $query = "UPDATE gym_members 
              SET plan_id = ?, 
                  start_date = ?, 
                  end_date = ?
              WHERE id = ?";
              
    $stmt = $db_connection->prepare($query);
    $stmt->bind_param("issi", $plan_id, $start_date, $end_date, $membership_id);
    
    if ($stmt->execute()) {
        // If membership is expired, remove user access
        if ($status === 'expired') {
            // You might want to add a status column to gym_members table
            $update_status = "UPDATE gym_members SET status = 'expired' WHERE id = ?";
            $status_stmt = $db_connection->prepare($update_status);
            $status_stmt->bind_param("i", $membership_id);
            $status_stmt->execute();
            $status_stmt->close();
        }
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update membership']);
    }
    
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

if (isset($db_connection)) {
    $db_connection->close();
}