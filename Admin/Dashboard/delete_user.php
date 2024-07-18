<?php
// Start the session
session_start();

// Check if the admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin_login.php");
    exit();
}

// Database connection code
$user = 'root';
$pass = ''; // Change this to the actual password if it's not empty
$db = 'gymdb';
$port = 3307;

$db_connection = new mysqli('localhost', $user, $pass, $db, $port);

if ($db_connection->connect_error) {
    die("Connection failed: " . $db_connection->connect_error);
}

// Delete user by ID
$id = $_GET['id'];
$stmt = $db_connection->prepare("DELETE FROM users WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    // Redirect to admin dashboard
    header("Location: admin_dashboard.php");
    exit();
} else {
    echo "Error: " . $stmt->error;
}

$stmt->close();
$db_connection->close();
?>
