<?php
session_start();

// Check if the user is logged in and is a superadmin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_role'] !== 'superadmin') {
    header("Location: Admin/admin_login_form.php");
    exit();
}

require 'db_connection.php'; // Include database connection

// Retrieve the gym ID from the URL
if (isset($_GET['gym_id'])) {
    $gym_id = $_GET['gym_id'];

    // Delete the gym from the database
    $delete_stmt = $db_connection->prepare("DELETE FROM gyms WHERE gym_id = ?");
    $delete_stmt->bind_param("i", $gym_id);

    if ($delete_stmt->execute()) {
        $delete_message = "Gym deleted successfully!";
    } else {
        $delete_message = "Error: " . $delete_stmt->error;
    }

    $delete_stmt->close();
} else {
    die("Gym ID is required.");
}

$db_connection->close();

// Redirect to the gym management page after deletion
header("Location: superadmin_dashboard.php");
exit();
