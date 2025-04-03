<?php
// Prevent any output before headers
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();
include '../config/database.php';

// Set proper headers
header('Content-Type: application/json');

try {
    // Check authorization
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
        throw new Exception('Unauthorized access');
    }

    // Validate input
    $required_fields = ['membership_id', 'plan_id', 'start_date', 'end_date', 'status'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    // Sanitize inputs
    $membership_id = filter_var($_POST['membership_id'], FILTER_VALIDATE_INT);
    $plan_id = filter_var($_POST['plan_id'], FILTER_VALIDATE_INT);
    $start_date = date('Y-m-d', strtotime($_POST['start_date']));
    $end_date = date('Y-m-d', strtotime($_POST['end_date']));
    $status = in_array($_POST['status'], ['active', 'expired']) ? $_POST['status'] : 'expired';

    if (!$membership_id || !$plan_id) {
        throw new Exception('Invalid membership or plan ID');
    }

    // Validate dates
    if (!$start_date || !$end_date || strtotime($end_date) < strtotime($start_date)) {
        throw new Exception('Invalid date range');
    }

    // Begin transaction
    $db_connection->begin_transaction();

    // Update membership
    $update_query = "UPDATE gym_members 
                    SET plan_id = ?,
                        start_date = ?,
                        end_date = ?,
                        status = ?
                    WHERE id = ?";
    
    $stmt = $db_connection->prepare($update_query);
    if (!$stmt) {
        throw new Exception($db_connection->error);
    }

    $stmt->bind_param("isssi", 
        $plan_id,
        $start_date,
        $end_date,
        $status,
        $membership_id
    );
    
    if (!$stmt->execute()) {
        throw new Exception($stmt->error);
    }

    // Commit transaction
    $db_connection->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Membership updated successfully'
    ]);

} catch (Exception $e) {
    // Rollback transaction if started
    if ($db_connection->ping()) {
        $db_connection->rollback();
    }

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Close connections
if (isset($stmt)) {
    $stmt->close();
}
if (isset($db_connection)) {
    $db_connection->close();
}