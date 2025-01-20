<?php
session_start();

// Check if the user is logged in and is a superadmin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_role'] !== 'superadmin') {
    header("Location: Admin/admin_login_form.php");
    exit();
}

require 'db_connection.php'; // Include database connection

// Check if the admin ID is passed in the URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage_admins.php"); // Redirect if no valid ID
    exit();
}

$admin_id = $_GET['id'];

// Prevent deletion of the superadmin
if ($admin_id == 1) {
    header("Location: manage_admins.php?error=Cannot delete SuperAdmin");
    exit();
}

// Delete the admin from the database
$delete_query = $db_connection->prepare("DELETE FROM admins WHERE id = ?");
$delete_query->bind_param("i", $admin_id);

if ($delete_query->execute()) {
    // Redirect to manage admins page after successful deletion
    header("Location: manage_admins.php?success=Admin deleted successfully");
    exit();
} else {
    // Redirect with error message if deletion failed
    header("Location: manage_admins.php?error=Error deleting admin");
    exit();
}
