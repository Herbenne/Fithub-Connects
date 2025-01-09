<?php
session_start();

// Ensure the admin is logged in
if (!isset($_SESSION['admin_logged_in']) || !isset($_SESSION['gym_id'])) {
    echo "Access Denied: You need to log in first.";
    exit();
}

// Fetch the gym ID and image ID from the URL
$gym_id = $_GET['gym_id'] ?? null;
$image_id = $_GET['image_id'] ?? null;

if (!$gym_id || !$image_id) {
    echo "Gym ID or Image ID is missing! Please ensure both are passed in the query string.";
    exit();
}

// Connect to the database
$user = 'root';
$pass = ''; // Change this to the actual password if it's not empty
$db = 'gymdb';
$port = 3307;

$db_connection = new mysqli('localhost', $user, $pass, $db, $port);

if ($db_connection->connect_error) {
    die("Connection failed: " . $db_connection->connect_error);
}

// Fetch the gym details only if the gym_id matches the logged-in admin's gym_id
$admin_gym_id = $_SESSION['gym_id'];  // Gym ID of the logged-in admin

// If the admin's gym_id does not match the gym_id from the URL, deny access
if ($admin_gym_id != $gym_id && $_SESSION['admin_role'] != 'superadmin') {
    echo "Access Denied: You are not authorized to delete this image.";
    exit();
}

// Fetch the image path from the database
$image_query = "SELECT image_path FROM gym_equipment_images WHERE id = ? AND gym_id = ?";
$image_stmt = $db_connection->prepare($image_query);
$image_stmt->bind_param("ii", $image_id, $gym_id);
$image_stmt->execute();
$image_result = $image_stmt->get_result();

// If the image is found, delete it
if ($image_result->num_rows > 0) {
    $image = $image_result->fetch_assoc();
    $image_path = $image['image_path'];

    // Delete the image file from the server
    if (file_exists($image_path)) {
        unlink($image_path);
    }

    // Delete the image record from the database
    $delete_query = "DELETE FROM gym_equipment_images WHERE id = ? AND gym_id = ?";
    $delete_stmt = $db_connection->prepare($delete_query);
    $delete_stmt->bind_param("ii", $image_id, $gym_id);

    if ($delete_stmt->execute()) {
        echo "Image deleted successfully.";
    } else {
        echo "Failed to delete the image from the database.";
    }
} else {
    echo "Image not found or you don't have permission to delete this image.";
}

// Close the database connection
$image_stmt->close();
$delete_stmt->close();
$db_connection->close();

// Redirect back to the gym details page
header("Location: gym_details.php?gym_id=" . $gym_id);
exit();
