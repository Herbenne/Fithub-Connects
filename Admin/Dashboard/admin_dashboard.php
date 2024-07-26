<?php
session_start();

// Check if the admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin_login.php");
    exit();
}

// Database connection code
$user = 'root';
$pass = '';
$db = 'gymdb';
$port = 3307;

$db_connection = new mysqli('localhost', $user, $pass, $db, $port);

if ($db_connection->connect_error) {
    die("Connection failed: " . $db_connection->connect_error);
}

// Handle the form submission for adding users with memberships
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_user'])) {
    $username = $_POST['username'];
    $membership_plan_id = $_POST['membership_plan_id'];
    $current_date = date('Y-m-d');

    // Fetch the membership plan details
    $stmt = $db_connection->prepare("SELECT duration FROM membership_plans WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $membership_plan_id);
        $stmt->execute();
        $stmt->bind_result($duration);
        $stmt->fetch();
        $stmt->close();
    
        $end_date = date('Y-m-d', strtotime($current_date . ' + ' . $duration . ' days'));
    
        // Insert or update user with membership details
        $stmt = $db_connection->prepare("INSERT INTO users (username, membership_start_date, membership_end_date, membership_status) VALUES (?, ?, ?, 'active') ON DUPLICATE KEY UPDATE membership_start_date = VALUES(membership_start_date), membership_end_date = VALUES(membership_end_date), membership_status = VALUES(membership_status)");
        if ($stmt) {
            $stmt->bind_param("sss", $username, $current_date, $end_date);
    
            if ($stmt->execute()) {
                echo "User added successfully!";
            } else {
                echo "Error: " . $stmt->error;
            }
    
            $stmt->close();
        } else {
            echo "Error preparing statement: " . $db_connection->error;
        }
    } else {
        echo "Error preparing statement: " . $db_connection->error;
    }
}

// Handle the form submission for adding admins
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_admin'])) {
    $admin_username = $_POST['admin_username'];
    $admin_email = $_POST['admin_email'];
    $admin_password = password_hash($_POST['admin_password'], PASSWORD_BCRYPT); // Hash the password for security

    // Insert new admin
    $stmt = $db_connection->prepare("INSERT INTO admins (username, email, password) VALUES (?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("sss", $admin_username, $admin_email, $admin_password);

        if ($stmt->execute()) {
            echo "Admin added successfully!";
        } else {
            echo "Error: " . $stmt->error;
        }

        $stmt->close();
    } else {
        echo "Error preparing statement: " . $db_connection->error;
    }
}

// Fetch users and membership plans for display
$sql = "SELECT id, unique_id, username, email, full_name, age, contact_number, membership_start_date, membership_end_date, membership_status FROM users";
$result = $db_connection->query($sql);

// Fetch membership plans for display
$sql_plans = "SELECT id, plan_name, duration, price FROM membership_plans";
$plans_result = $db_connection->query($sql_plans);

// Fetch admins for display
$sql_admins = "SELECT id, username, email, created_at FROM admins";
$admins_result = $db_connection->query($sql_admins);

if ($plans_result === false) {
    echo "Error fetching membership plans: " . $db_connection->error;
}

// Include the HTML for displaying the admin dashboard
include('admin_dashboard_view.php');

// Close the connection at the end
$db_connection->close();
?>
