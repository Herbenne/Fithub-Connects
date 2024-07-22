<?php
session_start();

// Check if the admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin_login.php");
    exit();
}

// Database connection
$user = 'root';
$pass = '';
$db = 'gymdb';
$port = 3307;

$db_connection = new mysqli('localhost', $user, $pass, $db, $port);

if ($db_connection->connect_error) {
    die("Connection failed: " . $db_connection->connect_error);
}

if (isset($_GET['id'])) {
    $admin_id = $_GET['id'];
    
    // Delete admin
    $stmt = $db_connection->prepare("DELETE FROM admins WHERE id = ?");
    $stmt->bind_param("i", $admin_id);

    if ($stmt->execute()) {
        echo "Admin deleted successfully!";
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
}

$db_connection->close();

header("Location: admin_dashboard.php");
exit();
?>
