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
        $_SESSION['error_message'] = "All fields are required.";
        header("Location: admin_login_form.php");
        exit();
    }

    // Modified SQL query to also fetch gym_id
    $stmt = $db_connection->prepare("SELECT id, password, role, gym_id FROM admins WHERE email = ?");
    if (!$stmt) {
        $_SESSION['error_message'] = "Error preparing the SQL statement.";
        header("Location: admin_login_form.php");
        exit();
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($admin_id, $hashed_password, $role, $gym_id);
        $stmt->fetch();

        if (password_verify($password, $hashed_password)) {
            // Store session variables
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $admin_id;
            $_SESSION['admin_email'] = $email;
            $_SESSION['admin_role'] = $role;
            $_SESSION['gym_id'] = $gym_id; // Store the gym_id

            // Redirect based on role
            if ($role === 'superadmin') {
                header("Location: ../Super/superadmin_dashboard.php");
            } else {
                // Redirect regular admins to their gym's details page
                header("Location: ../Frontend/html/gym_details.php?gym_id=" . $gym_id);
            }
            exit();
        } else {
            $_SESSION['error_message'] = "Invalid password.";
        }
    } else {
        $_SESSION['error_message'] = "No admin found with that email.";
    }

    $stmt->close();
    header("Location: admin_login_form.php");
    exit();
}
