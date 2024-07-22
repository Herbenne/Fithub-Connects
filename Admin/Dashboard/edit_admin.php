<?php
session_start();

// Check if the admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin_login.php");
    exit();
}

// Database connection
$user = 'root';
$pass = '';
$db = 'gymdb';
$port = 3307;

$db_connection = new mysqli('localhost', $user, $pass, $db, $port);

if ($db_connection->connect_error) {
    die("Connection failed: " . $db_connection->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_admin'])) {
    $admin_id = $_POST['admin_id'];
    $admin_username = $_POST['admin_username'];
    $admin_email = $_POST['admin_email'];

    // Optional: Hash new password if provided
    $admin_password = !empty($_POST['admin_password']) ? password_hash($_POST['admin_password'], PASSWORD_BCRYPT) : null;

    // Update admin details
    $query = "UPDATE admins SET username = ?, email = ?" . ($admin_password ? ", password = ?" : "") . " WHERE id = ?";
    $stmt = $db_connection->prepare($query);

    if ($admin_password) {
        $stmt->bind_param("sssi", $admin_username, $admin_email, $admin_password, $admin_id);
    } else {
        $stmt->bind_param("ssi", $admin_username, $admin_email, $admin_id);
    }

    if ($stmt->execute()) {
        echo "Admin updated successfully!";
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
}

// Fetch admin details for editing
$admin_id = $_GET['id'] ?? 0;
$stmt = $db_connection->prepare("SELECT username, email FROM admins WHERE id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$stmt->bind_result($username, $email);
$stmt->fetch();
$stmt->close();

$db_connection->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Admin</title>
</head>
<body>
    <h2>Edit Admin</h2>
    <form action="edit_admin.php" method="post">
        <input type="hidden" name="admin_id" value="<?php echo htmlspecialchars($admin_id); ?>">
        
        <label for="admin_username">Username:</label>
        <input type="text" id="admin_username" name="admin_username" value="<?php echo htmlspecialchars($username); ?>" required><br><br>

        <label for="admin_email">Email:</label>
        <input type="email" id="admin_email" name="admin_email" value="<?php echo htmlspecialchars($email); ?>" required><br><br>

        <label for="admin_password">New Password (leave blank to keep current):</label>
        <input type="password" id="admin_password" name="admin_password"><br><br>

        <input type="submit" name="edit_admin" value="Update Admin">
    </form>

    <br>
    <a href="admin_dashboard.php">Back to Dashboard</a>
</body>
</html>
