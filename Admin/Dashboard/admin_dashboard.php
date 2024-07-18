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

// Fetch all users
$sql = "SELECT id, username, email, full_name, age, contact_number FROM users";
$result = $db_connection->query($sql);

// Include the HTML for displaying the users
include('admin_dashboard_view.php');

// Close the connection
$db_connection->close();
?>
