<?php
session_start();
include '../config/database.php';

// Ensure user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../pages/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gym_id = $_POST['gym_id'];
    $plan_name = $_POST['plan_name'];
    $duration = $_POST['duration'];
    $price = $_POST['price'];
    $description = $_POST['description'];

    // Verify this gym belongs to the admin
    $verify_query = "SELECT * FROM gyms WHERE gym_id = ? AND owner_id = ?";
    $stmt = $db_connection->prepare($verify_query);
    $stmt->bind_param("ii", $gym_id, $_SESSION['user_id']);
    $stmt->execute();
    
    if (!$stmt->get_result()->fetch_assoc()) {
        header("Location: ../pages/dashboard.php");
        exit();
    }

    // Format the duration string
    $months = $_POST['duration'];
    $duration = $months . ' ' . ($months == 1 ? 'month' : 'months');

    // Insert the new plan
    $query = "INSERT INTO membership_plans (gym_id, plan_name, description, price, duration) 
              VALUES (?, ?, ?, ?, ?)";
    
    $stmt = $db_connection->prepare($query);
    if ($stmt === false) {
        header("Location: ../pages/manage_plans.php?gym_id=$gym_id&error=prepare_failed");
        exit();
    }

    $stmt->bind_param("issds", $gym_id, $plan_name, $description, $price, $duration);
    
    if ($stmt->execute()) {
        header("Location: ../pages/manage_plans.php?gym_id=$gym_id&success=create");
    } else {
        header("Location: ../pages/manage_plans.php?gym_id=$gym_id&error=create_failed");
    }
    exit();
}

header("Location: ../pages/dashboard.php");
exit();