<?php
session_start();
include '../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: ../pages/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gym_id = $_POST['gym_id'];
    $plan_name = $_POST['plan_name'];
    $description = $_POST['description'];
    $price = $_POST['price'];
    $duration = $_POST['duration'];

    $query = "INSERT INTO membership_plans (gym_id, plan_name, description, price, duration) 
              VALUES (?, ?, ?, ?, ?)";
    
    $stmt = $db_connection->prepare($query);
    $stmt->bind_param("issdi", $gym_id, $plan_name, $description, $price, $duration);
    
    if ($stmt->execute()) {
        header("Location: ../pages/manage_membership_plans.php?success=1");
    } else {
        header("Location: ../pages/manage_membership_plans.php?error=1");
    }
    exit();
}

header("Location: ../pages/manage_membership_plans.php");
exit();