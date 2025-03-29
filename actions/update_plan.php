<?php
session_start();
include '../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: ../pages/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plan_id = $_POST['plan_id'];
    $gym_id = $_POST['gym_id'];
    $plan_name = $_POST['plan_name'];
    $description = $_POST['description'];
    $price = $_POST['price'];
    $duration = $_POST['duration'];

    $query = "UPDATE membership_plans 
              SET gym_id = ?, plan_name = ?, description = ?, price = ?, duration = ? 
              WHERE plan_id = ?";
    
    $stmt = $db_connection->prepare($query);
    $stmt->bind_param("issdii", $gym_id, $plan_name, $description, $price, $duration, $plan_id);
    
    if ($stmt->execute()) {
        header("Location: ../pages/manage_membership_plans.php?success=1");
    } else {
        header("Location: ../pages/manage_membership_plans.php?error=1");
    }
    exit();
}

header("Location: ../pages/manage_membership_plans.php");
exit();