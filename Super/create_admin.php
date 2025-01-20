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
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $gym_id = $_POST['gym_id'];
    $password = $_POST['password'];

    if (empty($username) || empty($email) || empty($password)) {
        $error_message = "Username, email, and password are required!";
    } else {
        if ($gym_id === '0') {
            $gym_id = 0;
        }

        $username_check = $db_connection->prepare("SELECT id FROM admins WHERE username = ?");
        $username_check->bind_param("s", $username);
        $username_check->execute();
        $username_check_result = $username_check->get_result();

        $email_check = $db_connection->prepare("SELECT id FROM admins WHERE email = ?");
        $email_check->bind_param("s", $email);
        $email_check->execute();
        $email_check_result = $email_check->get_result();

        if ($username_check_result->num_rows > 0) {
            $error_message = "Username already exists!";
        } elseif ($email_check_result->num_rows > 0) {
            $error_message = "Email is already in use!";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $role = 'admin';
            $stmt = $db_connection->prepare("INSERT INTO admins (username, email, password, role, gym_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssi", $username, $email, $hashed_password, $role, $gym_id);

            if ($stmt->execute()) {
                header("Location: manage_admins.php"); // Redirect to the manage admins page
                exit();
            } else {
                $error_message = "Error creating admin: " . $db_connection->error;
            }
        }
    }
}

$gyms_query = "SELECT gym_id, gym_name FROM gyms";
$gyms_result = $db_connection->query($gyms_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Admin</title>
    <script src="https://kit.fontawesome.com/b098b18a13.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="./superAdminCss/createAdmin.css">
</head>

<body>
    <div class="dashboard-container">
        <header>
            <h1>Create New Admin</h1>
        </header>

        <nav>
            <a href="superadmin_dashboard.php"><i class="fa-solid fa-table-columns"></i>Dashboard</a>
            <a href="manage_admins.php"><i class="fa-solid fa-user"></i>Manage Admins</a>
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
            

            <div class="card">
                <!-- Display error message -->
            <?php if (isset($error_message)): ?>
                <p class="error-message"><?= htmlspecialchars($error_message) ?></p>
            <?php endif; ?>
                <h2 class="spanlabel">Admin</h2>
                <form method="POST" action="create_admin.php">
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" required>

                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" required>

                    <input type="hidden" name="role" value="admin">

                    <label for="gym_id">Gym:</label>
                    <select id="gym_id" name="gym_id">
                        <option value="0">None</option>
                        <?php while ($gym = $gyms_result->fetch_assoc()): ?>
                            <option value="<?= $gym['gym_id'] ?>"> <?= htmlspecialchars($gym['gym_name']) ?> </option>
                        <?php endwhile; ?>
                    </select>

                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required>

                    <button type="submit" class="btn btn-primary">Create Admin</button>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
