<?php
// Start the session
session_start();

// Check if the user is logged in as an admin
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin_login_form.php");
    exit();
}

require 'db_connection.php'; // Include database connection

// Check if the database connection is successful
if (!$db_connection) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Handle gym application form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gym_name = $_POST['gym_name'];
    $gym_location = $_POST['gym_location'];
    $gym_phone_number = $_POST['gym_phone_number'];
    $gym_description = $_POST['gym_description'];
    $gym_amenities = $_POST['gym_amenities'];

    // Prepare the SQL query for insertion into the gyms_applications table
    $stmt = $db_connection->prepare("INSERT INTO gyms_applications (gym_name, gym_location, gym_phone_number, gym_description, gym_amenities) VALUES (?, ?, ?, ?, ?)");

    // Check if the query preparation was successful
    if (!$stmt) {
        die("Prepare failed: " . $db_connection->error);
    }

    // Bind parameters to the prepared statement
    $stmt->bind_param("sssss", $gym_name, $gym_location, $gym_phone_number, $gym_description, $gym_amenities);

    // Execute the query
    if ($stmt->execute()) {
        echo "Gym application submitted successfully!";
    } else {
        echo "Error: " . $stmt->error;
    }

    // Close the statement
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gym Application Form</title>
    <link rel="stylesheet" href="../css/gymApplicationFrom.css"> <!-- Link to your CSS file -->
</head>

<body>
    <header>
        <h1>Gym Application Form</h1>
    </header>

    <form method="POST" action="../../Admin/admin_confirmation.php">
        <label for="gym_name">Gym Name:</label>
        <input type="text" id="gym_name" name="gym_name" required>

        <label for="gym_location">Gym Location:</label>
        <input type="text" id="gym_location" name="gym_location" required>

        <label for="gym_phone_number">Gym Phone Number:</label>
        <input type="text" id="gym_phone_number" name="gym_phone_number" required>

        <label for="gym_description">Gym Description:</label>
        <textarea id="gym_description" name="gym_description" required></textarea>

        <label for="gym_amenities">Gym Amenities:</label>
        <textarea id="gym_amenities" name="gym_amenities" required></textarea>

        <a href="../../Admin/admin_confirmation.php"><button type="submit">Submit Application</button></a>
    </form>

</body>

</html>