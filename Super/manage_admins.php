<?php
session_start();

// Check if the user is logged in and is a superadmin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_role'] !== 'superadmin') {
    header("Location: Admin/admin_login_form.php");
    exit();
}

require 'db_connection.php'; // Include database connection

// Fetch admins with gym_id from the database
$admins_query = "SELECT admins.id, admins.username, admins.email, admins.role, admins.gym_id, gyms.gym_name FROM admins LEFT JOIN gyms ON admins.gym_id = gyms.gym_id";
$admins_result = $db_connection->query($admins_query);

// Check if there are any admins in the database
if ($admins_result->num_rows > 0) {
    $admins = [];
    while ($admin = $admins_result->fetch_assoc()) {
        $admins[] = $admin;
    }
} else {
    $admins = [];
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="admin.css">   
    <title>Manage Admins</title>
<<<<<<< Updated upstream
    <link rel="stylesheet" href="styles.css"> <!-- Link to your CSS -->
=======
>>>>>>> Stashed changes
</head>

<body>
    <header>
        <h1>Manage Admins</h1>
    </header>

    <nav>
        <a href="superadmin_dashboard.php">Superadmin Dashboard</a>
        <a href="manage_users.php">Manage Users</a>
        <a href="manage_membership_plans.php">Manage Membership Plans</a>
        <a href="attendance_logs.php">View Attendance Logs</a>
        <a href="admin_login_form.php">Logout</a>
    </nav>

    <div class="container">
        <!-- Display success or error messages -->
        <?php if (isset($_GET['success'])): ?>
            <div class="success-message"><?= htmlspecialchars($_GET['success']) ?></div>
        <?php elseif (isset($_GET['error'])): ?>
            <div class="error-message"><?= htmlspecialchars($_GET['error']) ?></div>
        <?php endif; ?>

        <!-- Card for Admin Management Table -->
        <div class="card">
            <h2>Admin List</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Gym</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($admins)): ?>
                        <?php foreach ($admins as $admin): ?>
                            <tr>
                                <td><?= htmlspecialchars($admin['id']) ?></td>
                                <td><?= htmlspecialchars($admin['username']) ?></td>
                                <td><?= htmlspecialchars($admin['email']) ?></td>
                                <td><?= htmlspecialchars($admin['role']) ?></td>
                                <td><?= htmlspecialchars($admin['gym_name'] ?: 'None') ?></td>
                                <td>
                                    <a href="edit_admin.php?id=<?= $admin['id'] ?>">Edit</a>
                                    <a href="delete_admin.php?id=<?= $admin['id'] ?>" onclick="return confirm('Are you sure you want to delete this admin?');">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">No admins found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Card for Create New Admin Link -->
        <div class="card">
            <h2>Create New Admin</h2>
            <a href="create_admin.php" class="btn">Create New Admin</a>
        </div>
    </div>
</body>

</html>