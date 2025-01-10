<?php
session_start();
include('db_connection.php'); // Include your database connection

// Ensure the admin is logged in
if (!isset($_SESSION['admin_id'])) {
    echo "Access denied. Please log in.";
    exit();
}

// Fetch gym_id for the logged-in admin
$admin_id = $_SESSION['admin_id'];
$query = "SELECT gym_id, role FROM admins WHERE id = ?";
$stmt = $db_connection->prepare($query);

if ($stmt) {
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $stmt->bind_result($gym_id, $role);
    $stmt->fetch();
    $stmt->close();

    // Check if the admin is associated with a gym
    if ($role !== 'superadmin' && (!$gym_id || $gym_id == 0)) {
        echo "You do not have permission to manage gyms.";
        exit();
    }
} else {
    echo "Error fetching admin data.";
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_FILES['image']) || empty($_FILES['image']['name'])) {
        echo "Image file not provided!";
        exit();
    }

    // Validate file upload
    if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        echo "File upload error: " . $_FILES['image']['error'];
        exit();
    }

    // Define the upload directory
    $target_dir = "uploads/";
    $target_file = $target_dir . basename($_FILES["image"]["name"]);
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

    // Validate file type
    $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
    if (!in_array($imageFileType, $allowed_types)) {
        echo "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
        exit();
    }

    // Check if the file already exists
    if (file_exists($target_file)) {
        echo "Sorry, the file already exists.";
        exit();
    }

    // Upload the image
    if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
        // Insert the image path into the database
        $image_path = $target_file;
        $insert_query = "INSERT INTO gym_equipment_images (gym_id, image_path) VALUES (?, ?)";
        $stmt = $db_connection->prepare($insert_query);

        if ($stmt) {
            $stmt->bind_param("is", $gym_id, $image_path);

            if ($stmt->execute()) {
                echo "The file " . htmlspecialchars(basename($_FILES["image"]["name"])) . " has been uploaded and saved in the database.";
            } else {
                echo "Error saving image path in the database.";
            }

            $stmt->close();
        } else {
            echo "Error preparing the database query.";
        }
    } else {
        echo "Sorry, there was an error uploading your file.";
    }
} else {
    echo "Invalid request!";
}
