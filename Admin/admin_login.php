<?php
session_start();

$user = 'root';
$pass = ''; // Change this to the actual password if it's not empty
$db = 'gymdb';
$port = 3307;

$db_connection = new mysqli('localhost', $user, $pass, $db, $port);

if ($db_connection->connect_error) {
    die("Connection failed: " . $db_connection->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error_message = "All fields are required.";
    } else {
        $stmt = $db_connection->prepare("SELECT password FROM admins WHERE email = ?");
        
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $stmt->bind_result($hashed_password);
                $stmt->fetch();

                if (password_verify($password, $hashed_password)) {
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_email'] = $email;

                    header("Location: ../Admin/Dashboard/admin_dashboard.php");
                    exit();
                } else {
                    $error_message = "Invalid password.";
                }
            } else {
                $error_message = "No admin found with that email.";
            }

            $stmt->close();
        } else {
            $error_message = "Error preparing the SQL statement.";
        }
    }
}

$db_connection->close();
?>