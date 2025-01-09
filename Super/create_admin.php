<?php
session_start();

// Check if the user is logged in and is a superadmin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_role'] !== 'superadmin') {
    header("Location: Admin/admin_login_form.php");
    exit();
}

require 'db_connection.php'; // Include database connection

// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $gym_id = $_POST['gym_id']; // If "None" is selected, this will be '0'
    $password = $_POST['password'];

    // Validate form data
    if (empty($username) || empty($email) || empty($password)) {
        $error_message = "Username, email, and password are required!";
    } else {
        // Set gym_id to 0 if "None" is selected
        if ($gym_id === '0') {
            $gym_id = 0;
        }

        // Check for existing username
        $username_check = $db_connection->prepare("SELECT id FROM admins WHERE username = ?");
        $username_check->bind_param("s", $username);
        $username_check->execute();
        $username_check_result = $username_check->get_result();

        // Check for existing email
        $email_check = $db_connection->prepare("SELECT id FROM admins WHERE email = ?");
        $email_check->bind_param("s", $email);
        $email_check->execute();
        $email_check_result = $email_check->get_result();

        if ($username_check_result->num_rows > 0) {
            $error_message = "Username already exists!";
        } elseif ($email_check_result->num_rows > 0) {
            $error_message = "Email is already in use!";
        } else {
            // Hash the password before saving
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insert new admin into the database with role as "admin"
            $role = 'admin'; // Set role to admin
            $stmt = $db_connection->prepare("INSERT INTO admins (username, email, password, role, gym_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssi", $username, $email, $hashed_password, $role, $gym_id);

            if ($stmt->execute()) {
                // Redirect to the admin management page after successful creation
                header("Location: manage_admins.php");
                exit();
            } else {
                $error_message = "Error creating admin: " . $db_connection->error;
            }
        }
    }
}

// Fetch all gyms for the gym_id dropdown
$gyms_query = "SELECT gym_id, gym_name FROM gyms";
$gyms_result = $db_connection->query($gyms_query);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Admin</title>
    <link rel="stylesheet" href="styles.css"> <!-- Link to CSS -->
</head>

<body>
    <header>
        <h1>Create New Admin</h1>
    </header>

    <nav>
        <a href="superadmin_dashboard.php">Superadmin Dashboard</a>
        <a href="manage_admins.php">Manage Admins</a>
        <a href="manage_users.php">Manage Users</a>
        <a href="manage_membership_plans.php">Manage Membership Plans</a>
        <a href="attendance_logs.php">View Attendance Logs</a>
        <a href="admin_login_form.php">Logout</a>
    </nav>

    <div class="container">
        <!-- Card for Create New Admin Form -->
        <div class="card">
            <h2>Create New Admin</h2>

            <?php if (isset($error_message)): ?>
                <div class="error-message"><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>

            <form method="POST" action="create_admin.php">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required>

                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>

                <!-- Role is hardcoded to 'admin' -->
                <input type="hidden" name="role" value="admin">

                <label for="gym_id">Gym:</label>
                <select id="gym_id" name="gym_id">
                    <option value="0">None</option> <!-- Default "None" option -->
                    <?php while ($gym = $gyms_result->fetch_assoc()): ?>
                        <option value="<?= $gym['gym_id'] ?>"><?= htmlspecialchars($gym['gym_name']) ?></option>
                    <?php endwhile; ?>
                </select>

                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>

                <button type="submit">Create Admin</button>
            </form>
        </div>
    </div>
</body>

</html>