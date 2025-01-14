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

// Handle gym application confirmation
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
        // Display a confirmation message
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
    <title>Gym Application Confirmation</title>
    <link rel="stylesheet" href="../css/gymApplicationConfirmation.css"> <!-- Optional CSS -->
</head>

<style>
@import url('https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap');
    body{
        background-color: #f0f0f0;
        font-family: Poppins;
        margin: 0;
        padding: 0;
        display: flex;
        justify-content: center;
        align-items: center;
        flex-direction: column;
    }
    h1{
        text-align: center;
        color: #fab12f;
    }
    p{
        text-align: center;
        font-size: 1.2rem;
    }
    a button{
        margin: auto;
        padding: 15px 20px;
        background-color: #3498db;
        border: none;
        border-radius: 4px;
        transition: background-color 0.3s;
        color: white;
        font-size: 16px
    }
</style>

<body>
    <header>
        <h1>Gym Application Confirmation</h1>
    </header>
    <p>Thank you for submitting your gym application. The information has been successfully added to the database.</p>
    <a href="../../Admin/admin_login_form.php"><button>Proceed to login</button></a>
</body>

</html>