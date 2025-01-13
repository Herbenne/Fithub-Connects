<?php
session_start();

// Check if the user is logged in and is a superadmin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_role'] !== 'superadmin') {
    header("Location: Admin/admin_login_form.php");
    exit();
}

require 'db_connection.php'; // Include database connection

// Fetch all users
$query = "SELECT id, username, email, full_name, age, contact_number, profile_picture FROM users";
$result = $db_connection->query($query);

// Handle user edit
if (isset($_GET['edit_id'])) {
    $edit_id = $_GET['edit_id'];
    $user_query = "SELECT id, username, email, full_name, age, contact_number FROM users WHERE id = ?";
    $stmt = $db_connection->prepare($user_query);
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $stmt->bind_result($id, $username, $email, $full_name, $age, $contact_number);
    $stmt->fetch();
    $stmt->close();
}

// Update user details and password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $user_id = $_POST['id'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $full_name = $_POST['full_name'];
    $age = $_POST['age'];
    $contact_number = $_POST['contact_number'];
    $new_password = $_POST['new_password'] ?? '';

    // Update user details
    $update_query = "UPDATE users SET username = ?, email = ?, full_name = ?, age = ?, contact_number = ? WHERE id = ?";
    $update_stmt = $db_connection->prepare($update_query);
    $update_stmt->bind_param("sssiis", $username, $email, $full_name, $age, $contact_number, $user_id);
    $update_stmt->execute();

    // Update password if provided
    if (!empty($new_password)) {
        $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
        $password_update_query = "UPDATE users SET password = ? WHERE id = ?";
        $password_update_stmt = $db_connection->prepare($password_update_query);
        $password_update_stmt->bind_param("si", $hashed_password, $user_id);
        $password_update_stmt->execute();
    }

    $update_stmt->close();
    if (isset($password_update_stmt)) {
        $password_update_stmt->close();
    }

    // Redirect after updating
    header("Location: manage_users.php");
    exit();
}

// Handle delete user
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];

    // Delete the user from the database
    $delete_query = "DELETE FROM users WHERE id = ?";
    $delete_stmt = $db_connection->prepare($delete_query);
    $delete_stmt->bind_param("i", $delete_id);
    $delete_stmt->execute();
    $delete_stmt->close();

    // Redirect after deletion
    header("Location: manage_users.php");
    exit();
}

$db_connection->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users</title>
    <link rel="stylesheet" href="./superAdminCss/manageUsers.css">
    <script src="https://kit.fontawesome.com/b098b18a13.js" crossorigin="anonymous"></script>
</head>

<body>
    <div class="dashboard-container">
        <header>
            <h1>Manage Users</h1>
        </header>

        <nav>
            <a href="superadmin_dashboard.php"><i class="fa-solid fa-table-columns"></i>Dashboard</a>
            <a href="manage_admins.php"><i class="fa-solid fa-lock"></i>Manage Admins</a>
            <a href="manage_gyms.php"><i class="fa-solid fa-dumbbell"></i>Gyms</a>
            <a href="manage_gym_applications.php"><i class="fa-solid fa-paperclip"></i>Applications</a>
            <a href="manage_membership_plans.php"><i class="fa-solid fa-user"></i>Membership</a>
            <a href="paymentlist.php"><i class="fa-solid fa-money-bill"></i>View Payment</a>
            <a href="sadmin.php"><i class="fa-solid fa-gear"></i>Site Settings</a>
            <a href="backup_restore.php"><i class="fa-solid fa-file"></i>Backup & Restore</a>
            <a href="../Admin/admin_login_form.php"><i class="fa-solid fa-right-from-bracket"></i>Logout</a>
        </nav>

        <main>
            <div class="card">
                <h2 class="spanlabel">User List</h2>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Full Name</th>
                                <th>Age</th>
                                <th>Contact Number</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($user = $result->fetch_assoc()) : ?>
                                <tr>
                                    <td data-label="ID"><?= htmlspecialchars($user['id']) ?></td>
                                    <td data-label="Username"><?= htmlspecialchars($user['username']) ?></td>
                                    <td data-label="Email"><?= htmlspecialchars($user['email']) ?></td>
                                    <td data-label="Full Name"><?= htmlspecialchars($user['full_name']) ?></td>
                                    <td data-label="Age"><?= htmlspecialchars($user['age']) ?></td>
                                    <td data-label="Contact Number"><?= htmlspecialchars($user['contact_number']) ?></td>
                                    <td data-label="Actions">
                                        <a href="manage_users.php?edit_id=<?= $user['id'] ?>" class="btn btn-edit">Edit</a>
                                        <a href="manage_users.php?delete_id=<?= $user['id'] ?>" class="btn btn-delete" onclick="return confirm('Are you sure you want to delete this user?')">Delete</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if (isset($edit_id)) : ?>
                <div class="card">
                    <h2>Edit User</h2>
                    <form method="POST" action="manage_users.php">
                        <input type="hidden" name="id" value="<?= htmlspecialchars($id) ?>">

                        <div class="form-group">
                            <label for="username">Username:</label>
                            <input type="text" id="username" name="username" value="<?= htmlspecialchars($username) ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="email">Email:</label>
                            <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="full_name">Full Name:</label>
                            <input type="text" id="full_name" name="full_name" value="<?= htmlspecialchars($full_name) ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="age">Age:</label>
                            <input type="number" id="age" name="age" value="<?= htmlspecialchars($age) ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="contact_number">Contact Number:</label>
                            <input type="text" id="contact_number" name="contact_number" value="<?= htmlspecialchars($contact_number) ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="new_password">New Password (optional):</label>
                            <input type="password" id="new_password" name="new_password">
                        </div>

                        <button type="submit" name="update_user" class="btn btn-primary">Update User</button>
                    </form>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>

</html>