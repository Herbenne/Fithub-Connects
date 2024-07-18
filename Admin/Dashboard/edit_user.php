<?php
// Start the session
session_start();

// Check if the admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin_login.php");
    exit();
}

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
    $id = $_POST['id'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $full_name = $_POST['full_name'];
    $age = $_POST['age'];
    $contact_number = $_POST['contact_number'];

    // Validate form data
    if (empty($username) || empty($email) || empty($full_name) || empty($age) || empty($contact_number)) {
        $error_message = "All fields are required.";
    } else {
        // Update user information
        $stmt = $db_connection->prepare("UPDATE users SET username = ?, email = ?, full_name = ?, age = ?, contact_number = ? WHERE id = ?");
        $stmt->bind_param("sssisi", $username, $email, $full_name, $age, $contact_number, $id);

        if ($stmt->execute()) {
            // Redirect to admin dashboard
            header("Location: admin_dashboard.php");
            exit();
        } else {
            $error_message = "Error: " . $stmt->error;
        }

        $stmt->close();
    }
} else {
    // Fetch user data for the given ID
    $id = $_GET['id'];
    $stmt = $db_connection->prepare("SELECT id, username, email, full_name, age, contact_number FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($id, $username, $email, $full_name, $age, $contact_number);
    $stmt->fetch();
    $stmt->close();
}

$db_connection->close();

// Include the form
include('edit_user_form.php');
?>
