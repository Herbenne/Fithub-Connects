<?php
session_start();
include '../config/database.php';

// Ensure user is superadmin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: ../login.php");
    exit();
}

// Get user ID from query string
if (!isset($_GET['id'])) {
    die("User ID is required.");
}

$user_id = intval($_GET['id']);

// Delete related reviews
$stmt = $db_connection->prepare("DELETE FROM gym_reviews WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->close();

// Delete the user
$stmt = $db_connection->prepare("DELETE FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);

if ($stmt->execute()) {
    header("Location: ../pages/manage_users.php?message=User deleted successfully");
    exit();
} else {
    die("Failed to delete user.");
}
?>