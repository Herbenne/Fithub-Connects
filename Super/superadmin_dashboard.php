<?php
session_start();

// Check if the user is logged in and is a superadmin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_role'] !== 'superadmin') {
    header("Location: Admin/admin_login_form.php");
    exit();
}

require 'db_connection.php'; // Include database connection

// Retrieve admin details
$admin_email = $_SESSION['admin_email'];

// Fetch stats from the database
$total_users_query = "SELECT COUNT(*) FROM users";
$total_users_result = $db_connection->query($total_users_query);
$total_users = $total_users_result->fetch_row()[0];
$total_users_result->free();

$active_members_query = "SELECT COUNT(*) FROM users WHERE membership_status = 'active'";
$active_members_result = $db_connection->query($active_members_query);
$active_members = $active_members_result->fetch_row()[0];
$active_members_result->free();

$expired_members_query = "SELECT COUNT(*) FROM users WHERE membership_status = 'expired'";
$expired_members_result = $db_connection->query($expired_members_query);
$expired_members = $expired_members_result->fetch_row()[0];
$expired_members_result->free();

$total_admins_query = "SELECT COUNT(*) FROM admins";
$total_admins_result = $db_connection->query($total_admins_query);
$total_admins = $total_admins_result->fetch_row()[0];
$total_admins_result->free();

$total_plans_query = "SELECT COUNT(*) FROM membership_plans";
$total_plans_result = $db_connection->query($total_plans_query);
$total_plans = $total_plans_result->fetch_row()[0];
$total_plans_result->free();

$total_attendance_query = "SELECT COUNT(*) FROM attendance";
$total_attendance_result = $db_connection->query($total_attendance_query);
$total_attendance = $total_attendance_result->fetch_row()[0];
$total_attendance_result->free();

// Handle password change form submission
$password_change_message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];

    // Fetch current superadmin's password from the database
    $stmt = $db_connection->prepare("SELECT password FROM admins WHERE email = ?");
    if (!$stmt) {
        die("Prepare failed: " . $db_connection->error);
    }

    $stmt->bind_param("s", $_SESSION['admin_email']);
    $stmt->execute();
    $stmt->bind_result($hashed_password);
    $stmt->fetch();
    $stmt->close();

    if ($hashed_password && password_verify($current_password, $hashed_password)) {
        // Hash new password and update in the database
        $new_hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
        $update_stmt = $db_connection->prepare("UPDATE admins SET password = ? WHERE email = ?");
        if (!$update_stmt) {
            die("Prepare failed: " . $db_connection->error);
        }
        $update_stmt->bind_param("ss", $new_hashed_password, $_SESSION['admin_email']);
        $update_stmt->execute();
        $update_stmt->close();

        $password_change_message = "Password updated successfully!";
    } else {
        $password_change_message = "Incorrect current password!";
    }
}

$db_connection->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Superadmin Dashboard</title>
    <link rel="stylesheet" href="styles.css"> <!-- Link to CSS -->
</head>

<body>
    <header>
        <h1>Welcome, Superadmin</h1>
    </header>

    <nav>
        <a href="manage_admins.php">Manage Admins</a>
        <a href="manage_users.php">Manage Users</a>
        <a href="manage_membership_plans.php">Manage Membership Plans</a>
        <a href="attendance_logs.php">View Attendance Logs</a>
        <a href="../Admin/admin_login_form.php">Logout</a>
    </nav>

    <div class="container">


        <div class="card">
            <h2>Admin Email: <?php echo htmlspecialchars($admin_email); ?></h2>
            <p>Use the navigation menu to access various sections of the dashboard.</p>
        </div>

        <div class="card">
            <h2>System Statistics</h2>
            <ul>
                <li>Total Users: <?= $total_users ?></li>
                <li>Active Members: <?= $active_members ?></li>
                <li>Expired Members: <?= $expired_members ?></li>
                <li>Total Admins: <?= $total_admins ?></li>
                <li>Total Membership Plans: <?= $total_plans ?></li>
                <li>Total Attendance Logs: <?= $total_attendance ?></li>
            </ul>
        </div>

        <div class="card">
            <h2>Change Password</h2>
            <form method="POST" action="superadmin_dashboard.php">
                <label for="current_password">Current Password:</label>
                <input type="password" id="current_password" name="current_password" required>

                <label for="new_password">New Password:</label>
                <input type="password" id="new_password" name="new_password" required>

                <button type="submit">Change Password</button>
            </form>
            <?php if (!empty($password_change_message)) : ?>
                <p><?= htmlspecialchars($password_change_message) ?></p>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2>Backup & Restore</h2>

            <!-- Backup Section -->
            <form method="POST" action="backup_restore.php">
                <button type="submit" name="backup" class="button">Backup Database</button>
            </form>

            <!-- Restore Section -->
            <form method="POST" action="backup_restore.php" enctype="multipart/form-data">
                <a href="backup_restore.php">Restore Backup</a>
                <a href="sadmin.php">Site Settings</a>
            </form>
        </div>
    </div>

</body>

</html>