<?php
session_start();
include '../config/database.php';

// Ensure user is superadmin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: ../login.php");
    exit();
}

// Get user ID from query string
if (!isset($_GET['id'])) {
    die("User ID is required.");
}

$user_id = intval($_GET['id']);

// Fetch user details
$stmt = $db_connection->prepare("SELECT id, unique_id, username, email, first_name, last_name, age, contact_number, gym_id, role FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    die("User not found.");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $unique_id = $_POST['unique_id'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $age = $_POST['age'];
    $contact_number = $_POST['contact_number'];
    $gym_id = $_POST['gym_id'];
    $new_role = $_POST['role'];

    $update_stmt = $db_connection->prepare("UPDATE users SET unique_id = ?, username = ?, email = ?, first_name = ?, last_name = ?, age = ?, contact_number = ?, gym_id = ?, role = ? WHERE id = ?");
    $update_stmt->bind_param("ssssisissi", $unique_id, $username, $email, $first_name, $last_name, $age, $contact_number, $gym_id, $new_role, $user_id);

    if ($update_stmt->execute()) {
        header("Location: manage_users.php?message=User updated successfully");
        exit();
    } else {
        $error = "Failed to update user.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit User</title>
    <link rel="stylesheet" href="../assets/css/mains.css">
</head>
<body>
    <h2>Edit User</h2>
    <?php if (isset($error)): ?>
        <p style="color: red;"><?php echo $error; ?></p>
    <?php endif; ?>
    <form method="POST">
        <label for="unique_id">Unique ID:</label>
        <input type="text" name="unique_id" id="unique_id" value="<?php echo htmlspecialchars($user['unique_id']); ?>" required>

        <label for="username">Username:</label>
        <input type="text" name="username" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>

        <label for="email">Email:</label>
        <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>

        <label for="first_name">First Name:</label>
        <input type="text" name="first_name" id="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>

        <label for="last_name">Last Name:</label>
        <input type="text" name="last_name" id="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>

        <label for="age">Age:</label>
        <input type="number" name="age" id="age" value="<?php echo htmlspecialchars($user['age']); ?>">

        <label for="contact_number">Contact Number:</label>
        <input type="text" name="contact_number" id="contact_number" value="<?php echo htmlspecialchars($user['contact_number']); ?>">

        <label for="gym_id">Gym ID:</label>
        <input type="number" name="gym_id" id="gym_id" value="<?php echo htmlspecialchars($user['gym_id']); ?>">

        <label for="role">Role:</label>
        <select name="role" id="role" required>
            <option value="superadmin" <?php echo $user['role'] === 'superadmin' ? 'selected' : ''; ?>>Superadmin</option>
            <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
            <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>User</option>
            <option value="member" <?php echo $user['role'] === 'member' ? 'selected' : ''; ?>>Member</option>
        </select>

        <button type="submit">Update</button>
    </form>
    <a href="manage_users.php">Back to Manage Users</a>
</body>
</html>