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

// Handle gym creation form submission
$gym_creation_message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_gym'])) {
    $gym_name = $_POST['gym_name'];
    $gym_location = $_POST['gym_location'];
    $gym_phone_number = $_POST['gym_phone_number'];
    $gym_description = $_POST['gym_description'];
    $gym_amenities = $_POST['gym_amenities'];

    // Insert new gym into the database
    $stmt = $db_connection->prepare("INSERT INTO gyms (gym_name, gym_location, gym_phone_number, gym_description, gym_amenities) 
                                     VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $gym_name, $gym_location, $gym_phone_number, $gym_description, $gym_amenities);

    if ($stmt->execute()) {
        $gym_creation_message = "Gym added successfully!";
    } else {
        $gym_creation_message = "Error: " . $stmt->error;
    }

    $stmt->close();
}

// Fetch all gyms for display
$gyms_query = "SELECT * FROM gyms";
$gyms_result = $db_connection->query($gyms_query);

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
        <a href="paymentlist.php">View Payment</a>
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
                <li>Active Members: <?= $active_members ?></li>
                <li>Expired Members: <?= $expired_members ?></li>
                <li>Total Admins: <?= $total_admins ?></li>
                <li>Total Membership Plans: <?= $total_plans ?></li>
                <li>Total Attendance Logs: <?= $total_attendance ?></li>
            </ul>
        </div>

        <!-- Gym Management Section -->
        <div class="card">
            <h2>Manage Gyms</h2>

            <!-- Gym Creation Form -->
            <h3>Add a New Gym</h3>
            <form method="POST" action="">
                <label for="gym_name">Gym Name:</label>
                <input type="text" id="gym_name" name="gym_name" required>

                <label for="gym_location">Gym Location:</label>
                <input type="text" id="gym_location" name="gym_location" required>

                <label for="gym_phone_number">Gym Phone Number:</label>
                <input type="text" id="gym_phone_number" name="gym_phone_number" required>

                <label for="gym_description">Gym Description:</label>
                <textarea id="gym_description" name="gym_description" required></textarea>

                <label for="gym_amenities">Gym Amenities:</label>
                <textarea id="gym_amenities" name="gym_amenities" required></textarea>

                <button type="submit" name="add_gym">Add Gym</button>
            </form>
            <?php if (!empty($gym_creation_message)) : ?>
                <p><?= htmlspecialchars($gym_creation_message) ?></p>
            <?php endif; ?>

            <!-- Display Existing Gyms -->
            <h3>Existing Gyms</h3>
            <table border="1">
                <tr>
                    <th>Gym Name</th>
                    <th>Location</th>
                    <th>Phone Number</th>
                    <th>Description</th>
                    <th>Amenities</th>
                    <th>Actions</th>
                </tr>
                <?php while ($row = $gyms_result->fetch_assoc()) { ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['gym_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['gym_location']); ?></td>
                        <td><?php echo htmlspecialchars($row['gym_phone_number']); ?></td>
                        <td><?php echo htmlspecialchars($row['gym_description']); ?></td>
                        <td><?php echo htmlspecialchars($row['gym_amenities']); ?></td>
                        <td>
                            <a href="edit_gym.php?gym_id=<?php echo $row['gym_id']; ?>">Edit</a> |
                            <a href="delete_gym.php?gym_id=<?php echo $row['gym_id']; ?>">Delete</a>
                        </td>
                    </tr>
                <?php } ?>
            </table>
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

        <!-- Backup & Restore Section -->
        <div class="card">
            <h2>Backup & Restore</h2>
            <form method="POST" action="backup_restore.php">
                <button type="submit" name="backup" class="button">Backup Database</button>
            </form>
            <form method="POST" action="backup_restore.php" enctype="multipart/form-data">
                <label for="backup_file">Restore Database:</label>
                <input type="file" name="backup_file" id="backup_file" accept=".sql" required>
                <button type="submit" name="restore" class="button">Restore</button>
            </form>
        </div>
    </div>
</body>

</html>