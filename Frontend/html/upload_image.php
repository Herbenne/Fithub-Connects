<?php
session_start();
include('db_connection.php'); // Include your database connection

// Ensure the admin is logged in
if (!isset($_SESSION['admin_id'])) {
    die("Access denied. Please log in.");
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

    if ($role !== 'superadmin' && (!$gym_id || $gym_id == 0)) {
        die("You do not have permission to manage gyms.");
    }
} else {
    die("Error fetching admin data.");
}

// DEBUG: Check if gym_id is retrieved correctly
if (!$gym_id) {
    die("Gym ID is missing! Please ensure it's correctly assigned.");
}

$uploadMessage = ""; // Variable to store success or error message

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_FILES['image']) || empty($_FILES['image']['name'])) {
        $uploadMessage = "Image file not provided!";
    } elseif ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        $uploadMessage = "File upload error: " . $_FILES['image']['error'];
    } else {
        // Define the upload directory
        $target_dir = "uploads/";
        $target_file = $target_dir . basename($_FILES["image"]["name"]);
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        // Validate file type
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($imageFileType, $allowed_types)) {
            $uploadMessage = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
        } elseif (file_exists($target_file)) {
            $uploadMessage = "Sorry, the file already exists.";
        } elseif (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            // Insert the image path into the database
            $image_path = $target_file;
            $insert_query = "INSERT INTO gym_equipment_images (gym_id, image_path) VALUES (?, ?)";
            $stmt = $db_connection->prepare($insert_query);

            if ($stmt) {
                $stmt->bind_param("is", $gym_id, $image_path);
                if ($stmt->execute()) {
                    $uploadMessage = "The file has been uploaded and saved in the database.";
                } else {
                    $uploadMessage = "Error saving image path in the database.";
                }
                $stmt->close();
            } else {
                $uploadMessage = "Error preparing the database query.";
            }
        } else {
            $uploadMessage = "Sorry, there was an error uploading your file.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://kit.fontawesome.com/b098b18a13.js" crossorigin="anonymous"></script>
    <title>Upload Gym Equipment Image</title>
</head>
<style>
    body {
        font-family: Poppins, sans-serif;
        margin: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .container {
        position: relative;
        min-width: 50%;
        background-color: #f1f1f1;
    }
    h1 {
        color: #fab12f;
        padding: 30px;
        text-align: center;
    }

    p {
        color: #3498db;
        text-align: center;
    }

    .href .fa-right-from-bracket {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        background-color: #fab12f;
        color: white;
        border-radius: 50%;
        position: absolute;
        top: 50px;
        left: 20px;
        text-decoration: none;
        transition: all 0.3s ease;
    }
</style>
<body>
    <div class="container">
        <h1>Upload Gym Equipment Image</h1>
        <a class="href" href="./gym_details.php?gym_id=<?php echo $gym_id; ?>"> 
            <i class="fa-solid fa-right-from-bracket"></i>
        </a>
        <?php if (!empty($uploadMessage)): ?>
            <p><?php echo htmlspecialchars($uploadMessage); ?></p>
        <?php endif; ?>
    </div>
</body>
</html>
