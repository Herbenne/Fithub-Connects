<?php
// Database connection code
$user = 'root';
$pass = ''; // Change this to the actual password if it's not empty
$db = 'gymdb';
$port = 3307;

$db_connection = new mysqli('localhost', $user, $pass, $db, $port);

if ($db_connection->connect_error) {
    die("Connection failed: " . $db_connection->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve form data
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name = $_POST['full_name'];
    $age = $_POST['age'];
    $contact_number = $_POST['contact_number'];

    // Handle file upload
    $profile_picture = null;
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
        $target_dir = "uploads/";
        $target_file = $target_dir . basename($_FILES["profile_picture"]["name"]);
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        
        // Check if image file is an actual image
        $check = getimagesize($_FILES["profile_picture"]["tmp_name"]);
        if ($check !== false) {
            // Check file size (max 2MB)
            if ($_FILES["profile_picture"]["size"] > 2000000) {
                $error_message = "Sorry, your file is too large.";
            } else {
                // Allow certain file formats
                if ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif") {
                    $error_message = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
                } else {
                    // Upload file
                    if (move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $target_file)) {
                        $profile_picture = $target_file;
                    } else {
                        $error_message = "Sorry, there was an error uploading your file.";
                    }
                }
            }
        } else {
            $error_message = "File is not an image.";
        }
    }

    // Validate form data
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password) || empty($full_name) || empty($age) || empty($contact_number)) {
        $error_message = "All fields are required.";
    } elseif ($password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } elseif (!preg_match('/^(?=.*[A-Z])(?=.*\d).{8,}$/', $password)) {
        $error_message = "Password must be at least 8 characters long, contain at least one capital letter and one number.";
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $db_connection->prepare("INSERT INTO users (username, email, password, full_name, age, contact_number, profile_picture) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssis", $username, $email, $hashed_password, $full_name, $age, $contact_number, $profile_picture);

        if ($stmt->execute()) {
            // Redirect to confirmation page
            header("Location: confirmation.php");
            exit();
        } else {
            $error_message = "Error: " . $stmt->error;
        }

        $stmt->close();
    }
}

$db_connection->close();

// Include the form
include('register_form.php');
?>
