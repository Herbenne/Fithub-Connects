<?php
// Include database connection
require 'db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gym_id = intval($_POST['gym_id']);
    $plan_name = $_POST['plan_name'];
    $duration = intval($_POST['duration']);
    $price = floatval($_POST['price']);

    // Insert the new plan into the database
    $insert_sql = "INSERT INTO membership_plans (gym_id, plan_name, duration, price) VALUES (?, ?, ?, ?)";
    $stmt = $db_connection->prepare($insert_sql);
    $stmt->bind_param("isid", $gym_id, $plan_name, $duration, $price);

    if ($stmt->execute()) {
        echo "New membership plan added successfully!";
        header("Location: gym_details.php?gym_id=" . $gym_id);
    } else {
        echo "Error adding plan: " . $stmt->error;
    }

    $stmt->close();
    $db_connection->close();
}
