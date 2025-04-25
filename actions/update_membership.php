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
    $status = $_POST['status']; // Get status from the form
    
    // Update query with status column
    $query = "UPDATE gym_members 
              SET plan_id = ?, 
                  start_date = ?, 
                  end_date = ?,
                  status = ?
              WHERE id = ?";
              
    $stmt = $db_connection->prepare($query);
    $stmt->bind_param("isssi", $plan_id, $start_date, $end_date, $status, $membership_id);
    
    try {
        
        if (!$stmt) {
            throw new Exception("Error preparing statement: " . $db_connection->error);
        }
        
        $stmt->bind_param("isssi", $plan_id, $start_date, $end_date, $status, $membership_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Error executing statement: " . $stmt->error);
        }
        
        // If all went well, commit the transaction
        $db_connection->commit();
        
        echo json_encode(['success' => true, 'message' => 'Membership updated successfully']);
    } catch (Exception $e) {
        // If there was an error, roll back the transaction
        $db_connection->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    
    if (isset($stmt)) {
        $stmt->close();
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

if (isset($db_connection)) {
    $db_connection->close();
}