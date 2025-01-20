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

    // Validate form data
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password) || empty($full_name) || empty($age) || empty($contact_number)) {
        $error_message = "All fields are required.";
    } elseif ($password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } elseif (!preg_match('/^(?=.*[A-Z])(?=.*\d).{8,}$/', $password)) {
        $error_message = "Password must be at least 8 characters long, contain at least one capital letter and one number.";
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Get the highest existing unique_id
        $stmt = $db_connection->prepare("SELECT MAX(CAST(SUBSTRING(unique_id, 4) AS UNSIGNED)) AS max_unique_id FROM users");
        if ($stmt) {
            $stmt->execute();
            $stmt->bind_result($max_unique_id);
            $stmt->fetch();
            $stmt->close();

            // Calculate the next unique ID
            $next_unique_id = $max_unique_id ? $max_unique_id + 1 : 1000; // Default to 1000 if no unique IDs exist
            $unique_id = "SG-" . str_pad($next_unique_id, 4, '0', STR_PAD_LEFT); // Format to 4 digits

            // Insert the new user
            $stmt = $db_connection->prepare("INSERT INTO users (username, email, password, full_name, age, contact_number, profile_picture, unique_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("ssssisss", $username, $email, $hashed_password, $full_name, $age, $contact_number, $profile_picture, $unique_id);

                if ($stmt->execute()) {
                    // Redirect to confirmation page
                    header("Location: confirmation.php");
                    exit();
                } else {
                    $error_message = "Error: " . $stmt->error;
                }

                $stmt->close();
            } else {
                $error_message = "Error preparing statement: " . $db_connection->error;
            }
        } else {
            $error_message = "Error preparing statement: " . $db_connection->error;
        }
    }
}

$db_connection->close();

// Include the form
include('register_form.php');
?>
