<?php
session_start();
include '../config/database.php';

// Ensure user is a gym owner
if ($_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

$owner_id = $_SESSION['user_id'];

// Get gym ID of logged-in owner
$query = "SELECT gym_id FROM gyms WHERE owner_id = ?";
$stmt = $db_connection->prepare($query);
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$result = $stmt->get_result();
$gym = $result->fetch_assoc();

if (!$gym) {
    echo "You don't own a gym.";
    exit();
}

$gym_id = $gym['gym_id'];

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $plan_name = $_POST['plan_name'];
    $price = $_POST['price'];
    $description = $_POST['description'];

    $query = "INSERT INTO membership_plans (gym_id, plan_name, price, description) VALUES (?, ?, ?, ?)";
    $stmt = $db_connection->prepare($query);
    $stmt->bind_param("isss", $gym_id, $plan_name, $price, $description);

    if ($stmt->execute()) {
        echo "Membership plan added!";
    } else {
        echo "Error adding membership.";
    }
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Add Membership Plan</title>
    <link rel="stylesheet" href="../assets/css/unified-theme.css">
</head>

<body>
    <h2>Add a New Membership Plan</h2>
    <form method="POST">
        <label>Plan Name:</label>
        <input type="text" name="plan_name" required><br>

        <label>Price (PHP):</label>
        <input type="number" name="price" step="0.01" required><br>

        <label>Description:</label>
        <textarea name="description" required></textarea><br>

        <button type="submit">Add Membership</button>
    </form>
</body>

</html>