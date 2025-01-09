<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_role'] !== 'superadmin') {
    header("Location: Admin/admin_login_form.php");
    exit();
}

require 'db_connection.php'; // Include database connection

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // Fetch admin data
    $stmt = $db_connection->prepare("SELECT username, email, role FROM admins WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($username, $email, $role);
    $stmt->fetch();
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $role = $_POST['role'];

    $stmt = $db_connection->prepare("UPDATE admins SET username = ?, email = ?, role = ? WHERE id = ?");
    $stmt->bind_param("sssi", $username, $email, $role, $id);

    if ($stmt->execute()) {
        header("Location: manage_admins.php?message=Admin updated successfully");
    } else {
        echo "Error: " . $db_connection->error;
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Admin</title>
    <link rel="stylesheet" href="styles.css">
</head>

<body>
    <header>
        <h1>Edit Admin</h1>
    </header>
    <div class="container">
        <form method="POST" action="edit_admin.php">
            <input type="hidden" name="id" value="<?= htmlspecialchars($id) ?>">

            <label for="username">Username:</label>
            <input type="text" id="username" name="username" value="<?= htmlspecialchars($username) ?>" required>

            <label for="email">Email:</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required>

            <label for="role">Role:</label>
            <select id="role" name="role" required>
                <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admin</option>
                <option value="superadmin" <?= $role === 'superadmin' ? 'selected' : '' ?>>Superadmin</option>
            </select>

            <button type="submit">Update Admin</button>
        </form>
    </div>
</body>

</html>