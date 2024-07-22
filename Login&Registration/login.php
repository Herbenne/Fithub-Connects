<?php
session_start();

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
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Validate form data
    if (empty($email) || empty($password)) {
        $error_message = "All fields are required.";
    } else {
        // Prepare and bind
        $stmt = $db_connection->prepare("SELECT id, password FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        // Check if email exists
        if ($stmt->num_rows > 0) {
            // Fetch the hashed password
            $stmt->bind_result($user_id, $hashed_password);
            $stmt->fetch();

            // Verify the password
            if (password_verify($password, $hashed_password)) {
                $_SESSION['user_id'] = $user_id;

                // Debugging: Check if the session is set
                if (!isset($_SESSION['user_id'])) {
                    die("Session user_id is not set.");
                }

                // Redirect to profile page
                header("Location: profile.php");
                exit();
            } else {
                $error_message = "Invalid password.";
            }
        } else {
            $error_message = "No user found with that email.";
        }

        // Close the statement
        $stmt->close();
    }
}

$db_connection->close();

// Include the form
include('login_form.php');
?>
