<?php
session_start();

// Check if the admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin_login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve form data
    $plan_name = $_POST['plan_name'];
    $duration = $_POST['duration'];
    $price = $_POST['price'];

    // Database connection code
    $user = 'root';
    $pass = '';
    $db = 'gymdb';
    $port = 3307;

    $db_connection = new mysqli('localhost', $user, $pass, $db, $port);

    if ($db_connection->connect_error) {
        die("Connection failed: " . $db_connection->connect_error);
    }

    // Insert the new membership plan
    $stmt = $db_connection->prepare("INSERT INTO membership_plans (plan_name, duration, price) VALUES (?, ?, ?)");
    $stmt->bind_param("sid", $plan_name, $duration, $price);

    if ($stmt->execute()) {
        echo "Membership plan added successfully!";
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
    $db_connection->close();

    // Redirect back to the admin dashboard
    header("Location: admin_dashboard.php");
    exit();
}
?>
