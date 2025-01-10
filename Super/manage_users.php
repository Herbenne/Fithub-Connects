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
    <link rel="stylesheet" href="styles.css"> <!-- Link to CSS -->
</head>

<body>
    <header>
        <h1>Manage Users</h1>
    </header>

    <nav>
        <a href="superadmin_dashboard.php">Dashboard</a>
        <a href="manage_admins.php">Manage Admins</a>
        <a href="manage_gyms.php">Manage Gyms</a>
        <a href="backup_restore.php">Backup & Restore</a>
        <a href="../Admin/admin_login_form.php">Logout</a>
    </nav>

    <div class="container">
        <!-- User List Table -->
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
                        <td><?= htmlspecialchars($user['id']) ?></td>
                        <td><?= htmlspecialchars($user['username']) ?></td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td><?= htmlspecialchars($user['full_name']) ?></td>
                        <td><?= htmlspecialchars($user['age']) ?></td>
                        <td><?= htmlspecialchars($user['contact_number']) ?></td>
                        <td>
                            <a href="manage_users.php?edit_id=<?= $user['id'] ?>">Edit</a> |
                            <a href="manage_users.php?delete_id=<?= $user['id'] ?>" onclick="return confirm('Are you sure you want to delete this user?')">Delete</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <!-- Edit User Form -->
        <?php if (isset($edit_id)) : ?>
            <div class="card">
                <h2>Edit User</h2>
                <form method="POST" action="manage_users.php">
                    <input type="hidden" name="id" value="<?= htmlspecialchars($id) ?>">

                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" value="<?= htmlspecialchars($username) ?>" required>

                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required>

                    <label for="full_name">Full Name:</label>
                    <input type="text" id="full_name" name="full_name" value="<?= htmlspecialchars($full_name) ?>" required>

                    <label for="age">Age:</label>
                    <input type="number" id="age" name="age" value="<?= htmlspecialchars($age) ?>" required>

                    <label for="contact_number">Contact Number:</label>
                    <input type="text" id="contact_number" name="contact_number" value="<?= htmlspecialchars($contact_number) ?>" required>

                    <label for="new_password">New Password (optional):</label>
                    <input type="password" id="new_password" name="new_password">

                    <button type="submit" name="update_user">Update User</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>