<?php
session_start();
include '../config/database.php';

// Ensure user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../pages/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plan_id = $_POST['plan_id'];
    $gym_id = $_POST['gym_id'];
    $plan_name = $_POST['plan_name'];
    $duration = $_POST['duration'];
    $price = $_POST['price'];
    $description = $_POST['description'];

    // Verify this gym belongs to the admin
    $verify_query = "SELECT g.* FROM gyms g 
                    INNER JOIN membership_plans mp ON g.gym_id = mp.gym_id 
                    WHERE mp.plan_id = ? AND g.owner_id = ?";
    $stmt = $db_connection->prepare($verify_query);
    $stmt->bind_param("ii", $plan_id, $_SESSION['user_id']);
    $stmt->execute();
    
    if (!$stmt->get_result()->fetch_assoc()) {
        header("Location: ../pages/dashboard.php");
        exit();
    }

    // Update the plan
    $query = "UPDATE membership_plans 
              SET plan_name = ?, duration = ?, price = ?, description = ? 
              WHERE plan_id = ? AND gym_id = ?";
    
    $stmt = $db_connection->prepare($query);
    $stmt->bind_param("ssdsii", $plan_name, $duration, $price, $description, $plan_id, $gym_id);
    
    if ($stmt->execute()) {
        header("Location: ../pages/manage_plans.php?gym_id=$gym_id&success=update");
    } else {
        header("Location: ../pages/manage_plans.php?gym_id=$gym_id&error=update_failed");
    }
    exit();
}

header("Location: ../pages/dashboard.php");
exit();