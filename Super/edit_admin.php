<?php
session_start();

// Check if the user is logged in and is a superadmin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_role'] !== 'superadmin') {
    header("Location: Admin/admin_login_form.php");
    exit();
}

require 'db_connection.php'; // Include database connection

// Check if the admin ID is passed in the URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage_admins.php"); // Redirect if no valid ID
    exit();
}

$admin_id = $_GET['id'];

// Fetch the admin details from the database
$admin_query = $db_connection->prepare("SELECT * FROM admins WHERE id = ?");
$admin_query->bind_param("i", $admin_id);
$admin_query->execute();
$admin_result = $admin_query->get_result();

if ($admin_result->num_rows === 0) {
    header("Location: manage_admins.php"); // Redirect if admin does not exist
    exit();
}

$admin_data = $admin_result->fetch_assoc();

// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the updated form data
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $gym_id = $_POST['gym_id'];
    $password = $_POST['password'];

    // Validate form data
    if (empty($username) || empty($email)) {
        $error_message = "Username and email are required!";
    } else {
        // If gym_id is '0' (None selected), set gym_id to null
        if ($gym_id === '0') {
            $gym_id = null;
        }

        // Check for duplicate username or email (excluding current admin)
        $username_check = $db_connection->prepare("SELECT id FROM admins WHERE username = ? AND id != ?");
        $username_check->bind_param("si", $username, $admin_id);
        $username_check->execute();
        $username_check_result = $username_check->get_result();

        $email_check = $db_connection->prepare("SELECT id FROM admins WHERE email = ? AND id != ?");
        $email_check->bind_param("si", $email, $admin_id);
        $email_check->execute();
        $email_check_result = $email_check->get_result();

        if ($username_check_result->num_rows > 0) {
            $error_message = "Username already exists!";
        } elseif ($email_check_result->num_rows > 0) {
            $error_message = "Email is already in use!";
        } else {
            // If password is provided, hash it, otherwise keep the existing password
            $hashed_password = empty($password) ? $admin_data['password'] : password_hash($password, PASSWORD_DEFAULT);

            // Update admin details in the database (role remains 'admin' by default)
            $update_query = $db_connection->prepare("UPDATE admins SET username = ?, email = ?, password = ?, gym_id = ? WHERE id = ?");
            $update_query->bind_param("sssii", $username, $email, $hashed_password, $gym_id, $admin_id);

            if ($update_query->execute()) {
                // Redirect to manage admins page after successful update
                header("Location: manage_admins.php");
                exit();
            } else {
                $error_message = "Error updating admin: " . $db_connection->error;
            }
        }
    }
}

// Fetch all gyms for the gym_id dropdown
$gyms_query = "SELECT gym_id, gym_name FROM gyms";
$gyms_result = $db_connection->query($gyms_query);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Admin</title>
    <link rel="stylesheet" href="styles.css"> <!-- Link to CSS -->
</head>

<body>
    <header>
        <h1>Edit Admin</h1>
    </header>

    <nav>
        <a href="superadmin_dashboard.php">Superadmin Dashboard</a>
        <a href="manage_admins.php">Manage Admins</a>
        <a href="manage_users.php">Manage Users</a>
        <a href="manage_membership_plans.php">Manage Membership Plans</a>
        <a href="attendance_logs.php">View Attendance Logs</a>
        <a href="admin_login_form.php">Logout</a>
    </nav>

    <div class="container">
        <!-- Card for Edit Admin Form -->
        <div class="card">
            <h2>Edit Admin</h2>

            <?php if (isset($error_message)): ?>
                <div class="error-message"><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>

            <form method="POST" action="edit_admin.php?id=<?= $admin_id ?>">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" value="<?= htmlspecialchars($admin_data['username']) ?>" required>

                <label for="email">Email:</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($admin_data['email']) ?>" required>

                <!-- Role is hardcoded to 'admin' -->
                <input type="hidden" name="role" value="admin">

                <label for="gym_id">Gym:</label>
                <select id="gym_id" name="gym_id" required>
                    <option value="0" <?= is_null($admin_data['gym_id']) ? 'selected' : '' ?>>None</option> <!-- Default "None" option -->
                    <?php while ($gym = $gyms_result->fetch_assoc()): ?>
                        <option value="<?= $gym['gym_id'] ?>" <?= $admin_data['gym_id'] == $gym['gym_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($gym['gym_name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>

                <label for="password">New Password (leave blank to keep current password):</label>
                <input type="password" id="password" name="password">

                <button type="submit">Update Admin</button>
            </form>
        </div>
    </div>
</body>

</html>