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

$total_admins_query = "SELECT COUNT(*) FROM admins";
$total_admins_result = $db_connection->query($total_admins_query);
$total_admins = $total_admins_result->fetch_row()[0];
$total_admins_result->free();

$total_gyms_query = "SELECT COUNT(*) FROM gyms";
$total_gyms_result = $db_connection->query($total_gyms_query);
$total_gyms = $total_gyms_result->fetch_row()[0];
$total_gyms_result->free();

$total_gyms_applications_query = "SELECT COUNT(*) FROM gyms_applications";
$total_gyms_applications_result = $db_connection->query($total_gyms_applications_query);
$total_gyms_applications = $total_gyms_applications_result->fetch_row()[0];
$total_gyms_applications_result->free();

// Handle password change form submission
$password_change_message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['current_password']) && isset($_POST['new_password'])) {
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
		<link rel="stylesheet" href="./admin.css">
</head>

<body>
    <header>
        <h1>Welcome, Superadmin</h1>
    </header>

    <nav>
        <a href="manage_admins.php">Manage Admins</a>
        <a href="manage_users.php">Manage Users</a>
        <a href="manage_gym_applications.php">Applications</a>
        <a href="paymentlist.php">View Payment</a>
        <a href="sadmin.php">Site Settings</a>
        <a href="manage_gyms.php">Gyms</a>
        <a href="backup_restore.php">Backup & Restore</a>
        <a href="../Admin/admin_login_form.php">Logout</a>
    </nav>

    <div class="container">
        <!-- Display Admin Information -->
        <div class="card">
            <h2>Admin Email: <?php echo htmlspecialchars($admin_email); ?></h2>
            <p>Use the navigation menu to access various sections of the dashboard.</p>
        </div>

        <!-- Display System Statistics -->
        <div class="card">
            <h2>System Statistics</h2>
            <ul>
                <li>Total Users: <?= $total_users ?></li>
                <li>Total Admins: <?= $total_admins ?></li>
                <li>Total Gyms: <?= $total_gyms ?></li>
                <li>Total Gym Applications: <?= $total_gyms_applications ?></li>
            </ul>
        </div>

        <!-- Change Password Section -->
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

    </div>
</body>

</html>