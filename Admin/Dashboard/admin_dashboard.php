<?php
session_start();

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

    // Check if the username already exists
    $stmt = $db_connection->prepare("SELECT COUNT(*) AS count FROM users WHERE username = ?");
    if ($stmt) {
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->bind_result($user_count);
        $stmt->fetch();
        $stmt->close();

        if ($user_count > 0) {
            echo "Error: Username already exists!";
        } else {
            // Generate unique ID based on the highest existing ID
            $stmt = $db_connection->prepare("SELECT MAX(id) AS max_id FROM users");
            if ($stmt) {
                $stmt->execute();
                $stmt->bind_result($max_id);
                $stmt->fetch();
                $stmt->close();

                // Calculate the next ID
                $next_id = $max_id ? $max_id + 1 : 1000; // Default to 1000 if no IDs exist
                $unique_id = "SG-" . str_pad($next_id, 4, '0', STR_PAD_LEFT); // Format to 4 digits

                // Check if unique_id already exists (should be unnecessary, but good practice)
                $stmt = $db_connection->prepare("SELECT COUNT(*) AS id_count FROM users WHERE unique_id = ?");
                if ($stmt) {
                    $stmt->bind_param("s", $unique_id);
                    $stmt->execute();
                    $stmt->bind_result($id_count);
                    $stmt->fetch();
                    $stmt->close();

                    if ($id_count > 0) {
                        echo "Error: Unique ID already exists!";
                    } else {
                        // Fetch the membership plan details
                        $stmt = $db_connection->prepare("SELECT duration FROM membership_plans WHERE id = ?");
                        if ($stmt) {
                            $stmt->bind_param("i", $membership_plan_id);
                            $stmt->execute();
                            $stmt->bind_result($duration);
                            $stmt->fetch();
                            $stmt->close();

                            $end_date = date('Y-m-d', strtotime($current_date . ' + ' . $duration . ' days'));

                            // Insert new user with membership details
                            $stmt = $db_connection->prepare("INSERT INTO users (unique_id, username, membership_start_date, membership_end_date, membership_status) VALUES (?, ?, ?, ?, 'active')");
                            if ($stmt) {
                                $stmt->bind_param("ssss", $unique_id, $username, $current_date, $end_date);

                                if ($stmt->execute()) {
                                    echo "User added successfully!";
                                } else {
                                    echo "Error: " . $stmt->error;
                                }

                                $stmt->close();
                            } else {
                                // Log the SQL error
                                error_log("Error preparing statement: " . $db_connection->error);
                            }
                        } else {
                            // Log the SQL error
                            error_log("Error preparing statement: " . $db_connection->error);
                        }
                    }
                } else {
                    // Log the SQL error
                    error_log("Error preparing statement: " . $db_connection->error);
                }
            } else {
                // Log the SQL error
                error_log("Error preparing statement: " . $db_connection->error);
            }
        }
    } else {
        // Log the SQL error
        error_log("Error preparing statement: " . $db_connection->error);
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
        // Log the SQL error
        error_log("Error preparing statement: " . $db_connection->error);
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
