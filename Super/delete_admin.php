<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_role'] !== 'superadmin') {
    header("Location: Admin/admin_login_form.php");
    exit();
}

require 'db_connection.php'; // Include database connection

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    $stmt = $db_connection->prepare("DELETE FROM admins WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        header("Location: manage_admins.php?message=Admin deleted successfully");
    } else {
        echo "Error: " . $db_connection->error;
    }
    $stmt->close();
}
