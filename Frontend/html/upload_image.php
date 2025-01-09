<?php
session_start();
include('db_connection.php'); // Database connection

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['image'])) {
    $gym_id = $_GET['gym_id'] ?? null;

    // Check if gym_id is provided
    if (!$gym_id) {
        echo "Gym ID is missing!";
        exit();
    }

    // Image upload directory
    $target_dir = "uploads/";
    $target_file = $target_dir . basename($_FILES["image"]["name"]);
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

    // Validate file type (allow only image files)
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
        echo "The file " . htmlspecialchars(basename($_FILES["image"]["name"])) . " has been uploaded.";

        // Insert the image path into the database
        $image_path = $target_file;
        $insert_query = "INSERT INTO gym_equipment_images (gym_id, image_path) VALUES (?, ?)";
        $stmt = $db_connection->prepare($insert_query);
        $stmt->bind_param("is", $gym_id, $image_path);
        if ($stmt->execute()) {
            echo " Image path has been saved in the database.";
        } else {
            echo " Error saving image path in the database.";
        }
    } else {
        echo "Sorry, there was an error uploading your file.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Upload Image</title>
</head>

<body>

    <h2>Upload Equipment Image for Gym</h2>
    <form method="POST" enctype="multipart/form-data">
        <label for="image">Select image to upload:</label>
        <input type="file" name="image" id="image" required>
        <br><br>
        <input type="submit" value="Upload Image">
    </form>

</body>

</html>