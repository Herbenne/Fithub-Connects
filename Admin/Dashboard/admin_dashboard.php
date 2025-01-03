<?php
session_start();

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if the admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../Admin/admin_login.php"); // Fixed location path
    exit();
}

// Database connection details
$user = 'root';
$pass = '';
$db = 'gymdb';
$port = 3307;

$db_connection = new mysqli('localhost', $user, $pass, $db, $port);

if ($db_connection->connect_error) {
    die("Connection failed: " . $db_connection->connect_error);
}

// Add or Update Membership for Existing User
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_user'])) {
    $username = $_POST['username'];
    $membership_plan_id = $_POST['membership_plan_id'];
    $current_date = date('Y-m-d');

    // Check if the user already exists
    $stmt = $db_connection->prepare("SELECT id FROM users WHERE username = ?");
    if ($stmt) {
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->bind_result($user_id);
        $stmt->fetch();
        $stmt->close();

        // If user exists, update their membership details
        if ($user_id) {
            // Fetch the duration of the selected membership plan
            $stmt = $db_connection->prepare("SELECT duration FROM membership_plans WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $membership_plan_id);
                $stmt->execute();
                $stmt->bind_result($duration);
                $stmt->fetch();
                $stmt->close();

                if ($duration) {
                    $end_date = date('Y-m-d', strtotime($current_date . ' + ' . $duration . ' days'));

                    // Update user's membership details
                    $stmt = $db_connection->prepare("UPDATE users SET membership_start_date = ?, membership_end_date = ?, membership_status = 'active' WHERE id = ?");
                    $stmt->bind_param("ssi", $current_date, $end_date, $user_id);

                    if ($stmt->execute()) {
                        echo "Membership updated successfully for existing user!";
                    } else {
                        echo "Error updating membership: " . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    echo "Error: Invalid membership plan selected.";
                }
            } else {
                error_log("Error preparing statement: " . $db_connection->error);
            }
        } else {
            // User doesn't exist, add a new user
            // Get the next unique ID
            $stmt = $db_connection->prepare("SELECT MAX(CAST(SUBSTRING(unique_id, 4) AS UNSIGNED)) AS max_unique_id FROM users");
            $stmt->execute();
            $stmt->bind_result($max_unique_id);
            $stmt->fetch();
            $stmt->close();

            // Increment the unique ID starting from 1000
            $next_unique_id = $max_unique_id ? $max_unique_id + 1 : 1000;
            $unique_id = "SG-" . str_pad($next_unique_id, 4, '0', STR_PAD_LEFT);

            // Fetch membership plan duration
            $stmt = $db_connection->prepare("SELECT duration FROM membership_plans WHERE id = ?");
            $stmt->bind_param("i", $membership_plan_id);
            $stmt->execute();
            $stmt->bind_result($duration);
            $stmt->fetch();
            $stmt->close();

            if ($duration) {
                $end_date = date('Y-m-d', strtotime($current_date . ' + ' . $duration . ' days'));

                // Insert new user with membership details
                $stmt = $db_connection->prepare("INSERT INTO users (unique_id, username, membership_start_date, membership_end_date, membership_status) VALUES (?, ?, ?, ?, 'active')");
                $stmt->bind_param("ssss", $unique_id, $username, $current_date, $end_date);

                if ($stmt->execute()) {
                    echo "User added successfully!";
                } else {
                    echo "Error: " . $stmt->error;
                }
                $stmt->close();
            } else {
                echo "Error: Invalid membership plan selected.";
            }
        }
    } else {
        error_log("Error preparing statement: " . $db_connection->error);
    }
}

// Add Admin logic
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_admin'])) {
    $admin_username = $_POST['admin_username'];
    $admin_email = $_POST['admin_email'];
    $admin_password = password_hash($_POST['admin_password'], PASSWORD_BCRYPT);

    // Insert new admin
    $stmt = $db_connection->prepare("INSERT INTO admins (username, email, password, created_at) VALUES (?, ?, ?, NOW())");
    if ($stmt) {
        $stmt->bind_param("sss", $admin_username, $admin_email, $admin_password);

        if ($stmt->execute()) {
            echo "Admin added successfully!";
        } else {
            echo "Error: " . $stmt->error;
        }
        $stmt->close();
    } else {
        error_log("Error preparing statement: " . $db_connection->error);
    }
}

// Fetch users and membership plans for display
$user_query = "SELECT id, unique_id, username, email, full_name, age, contact_number, membership_start_date, membership_end_date, membership_status FROM users";
$result = $db_connection->query($user_query);

// Fetch membership plans
$plans_query = "SELECT id, plan_name, duration, price FROM membership_plans";
$plans_result = $db_connection->query($plans_query);

if ($plans_result === false) {
    echo "Error fetching membership plans: " . $db_connection->error;
}

// Fetch admins for display
$admins_query = "SELECT id, username, email, created_at FROM admins";
$admins_result = $db_connection->query($admins_query);

if ($admins_result === false) {
    echo "Error fetching admins: " . $db_connection->error;
}

// Include the HTML for displaying the admin dashboard
include('admin_dashboard_view.php');

// Close the connection at the end
$db_connection->close();
