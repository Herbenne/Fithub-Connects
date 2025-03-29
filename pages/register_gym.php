<?php
session_start();
include '../config/database.php';
include '../includes/auth.php';

// Add role check
if (!isset($_SESSION['role']) || $_SESSION['role'] === 'member') {
    die("Error: You don't have permission to register a gym.");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_SESSION['user_id'])) {
        die("Error: You must be logged in to apply for a gym.");
    }

    $gym_name = trim($_POST['gym_name']);
    $gym_location = trim($_POST['gym_location']); // Fixed column name
    $gym_phone_number = trim($_POST['gym_phone_number']);
    $gym_description = trim($_POST['gym_description']);
    $gym_amenities = trim($_POST['gym_amenities']);
    $user_id = $_SESSION['user_id'];

    if (empty($gym_name) || empty($gym_location) || empty($gym_phone_number) || empty($gym_description) || empty($gym_amenities)) {
        die("Error: All fields are required.");
    }

    // Prepare SQL statement
    $query = "INSERT INTO gyms (owner_id, gym_name, gym_location, gym_phone_number, gym_description, gym_amenities, status) 
              VALUES (?, ?, ?, ?, ?, ?, 'pending')";
    $stmt = $db_connection->prepare($query);

    if ($stmt === false) {
        die("Error: " . $db_connection->error);
    }

    $stmt->bind_param("isssss", $user_id, $gym_name, $gym_location, $gym_phone_number, $gym_description, $gym_amenities);

    if ($stmt->execute()) {
        echo "Gym registration request sent! Wait for approval.";
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Register Gym</title>
</head>

<body>
    <h2>Register Your Gym</h2>
    <form method="POST">
        <input type="text" name="gym_name" placeholder="Gym Name" required><br>
        <input type="text" name="gym_location" placeholder="Location" required><br>
        <input type="text" name="gym_phone_number" placeholder="Phone Number" required><br>
        <textarea name="gym_description" placeholder="Gym Description" required></textarea><br>
        <textarea name="gym_amenities" placeholder="Gym Amenities (comma-separated)" required></textarea><br>
        <button type="submit">Apply</button>
    </form>
    <a href="dashboard.php">Back to Dashboard</a>
</body>

</html>