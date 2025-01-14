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
    <title>Manage Admins</title>
    <script src="https://kit.fontawesome.com/b098b18a13.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="./superAdminCss/manageAdmins.css">
</head>

<body>
    <div class="dashboard-container">
        <header>
            <h1>Manage Admins</h1>
        </header>

        <nav>
            <a href="superadmin_dashboard.php"><i class="fa-solid fa-table-columns"></i>Dashboard</a>
            <a href="manage_users.php"><i class="fa-solid fa-user"></i>Manage Users</a>
            <a href="manage_gyms.php"><i class="fa-solid fa-dumbbell"></i>Gyms</a>
            <a href="manage_gym_applications.php"><i class="fa-solid fa-paperclip"></i>Applications</a>
            <a href="manage_membership_plans.php"><i class="fa-solid fa-user"></i>Membership</a>
            <a href="paymentlist.php"><i class="fa-solid fa-money-bill"></i>View Payment</a>
            <a href="sadmin.php"><i class="fa-solid fa-gear"></i>Site Settings</a>
            <a href="backup_restore.php"><i class="fa-solid fa-file"></i>Backup & Restore</a>
            <a href="../Admin/admin_login_form.php"><i class="fa-solid fa-right-from-bracket"></i>Logout</a>
        </nav>

        <main>
            <!-- Display success or error messages -->
            <?php if (isset($_GET['success'])): ?>
                <div class="message success"><?= htmlspecialchars($_GET['success']) ?></div>
            <?php elseif (isset($_GET['error'])): ?>
                <div class="message error"><?= htmlspecialchars($_GET['error']) ?></div>
            <?php endif; ?>

            <!-- Card for Admin Management Table -->
            <div class="card">
                <h2 class="spanlabel">Admin List</h2>
                <div class="table-responsive">
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
                                        <td data-label="ID"><?= htmlspecialchars($admin['id']) ?></td>
                                        <td data-label="Username"><?= htmlspecialchars($admin['username']) ?></td>
                                        <td data-label="Email"><?= htmlspecialchars($admin['email']) ?></td>
                                        <td data-label="Role"><?= htmlspecialchars($admin['role']) ?></td>
                                        <td data-label="Gym"><?= htmlspecialchars($admin['gym_name'] ?: 'None') ?></td>
                                        <td data-label="Actions">
                                            <a href="edit_admin.php?id=<?= $admin['id'] ?>" class="btn btn-edit">Edit</a>
                                            <a href="delete_admin.php?id=<?= $admin['id'] ?>" class="btn btn-delete" onclick="return confirm('Are you sure you want to delete this admin?');">Delete</a>
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
            </div>
            <!-- Card for Create New Admin Link -->
            <div class="card">
                <h2>Create New Admin</h2>
                <a href="create_admin.php" class="btn btn-primary">Create New Admin</a>
            </div>
        </main>
    </div>
</body>

</html>