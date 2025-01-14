<?php
session_start();

// Check if the user is logged in and is a superadmin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_role'] !== 'superadmin') {
    header("Location: Admin/admin_login_form.php");
    exit();
}

require 'db_connection.php'; // Include database connection

// Check if the admin ID is passed in the URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage_admins.php"); // Redirect if no valid ID
    exit();
}

$admin_id = $_GET['id'];

// Fetch the admin details from the database
$admin_query = $db_connection->prepare("SELECT * FROM admins WHERE id = ?");
$admin_query->bind_param("i", $admin_id);
$admin_query->execute();
$admin_result = $admin_query->get_result();

if ($admin_result->num_rows === 0) {
    header("Location: manage_admins.php"); // Redirect if admin does not exist
    exit();
}

$admin_data = $admin_result->fetch_assoc();

// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $gym_id = $_POST['gym_id'];
    $password = $_POST['password'];

    if (empty($username) || empty($email)) {
        $error_message = "Username and email are required!";
    } else {
        if ($gym_id === '0') {
            $gym_id = null;
        }

        // Check for duplicate username or email (excluding current admin)
        $username_check = $db_connection->prepare("SELECT id FROM admins WHERE username = ? AND id != ?");
        $username_check->bind_param("si", $username, $admin_id);
        $username_check->execute();
        $username_check_result = $username_check->get_result();

        $email_check = $db_connection->prepare("SELECT id FROM admins WHERE email = ? AND id != ?");
        $email_check->bind_param("si", $email, $admin_id);
        $email_check->execute();
        $email_check_result = $email_check->get_result();

        if ($username_check_result->num_rows > 0) {
            $error_message = "Username already exists!";
        } elseif ($email_check_result->num_rows > 0) {
            $error_message = "Email is already in use!";
        } else {
            $hashed_password = empty($password) ? $admin_data['password'] : password_hash($password, PASSWORD_DEFAULT);
            $update_query = $db_connection->prepare("UPDATE admins SET username = ?, email = ?, password = ?, gym_id = ? WHERE id = ?");
            $update_query->bind_param("sssii", $username, $email, $hashed_password, $gym_id, $admin_id);

            if ($update_query->execute()) {
                header("Location: manage_admins.php");
                exit();
            } else {
                $error_message = "Error updating admin: " . $db_connection->error;
            }
        }
    }
}

$gyms_query = "SELECT gym_id, gym_name FROM gyms";
$gyms_result = $db_connection->query($gyms_query);

$db_connection->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Admin</title>
    <script src="https://kit.fontawesome.com/b098b18a13.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="./superAdminCss/editAdmin.css">
</head>
<body>
    <div class="dashboard-container">
        <header>
            <h1>Edit Admin</h1>
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
                <?php if (isset($error_message)): ?>
                    <p class="error-message"> <?= htmlspecialchars($error_message) ?> </p>
                <?php endif; ?>

                <h2 class="spanlabel">Admin Details</h2>
                <form method="POST" action="edit_admin.php?id=<?= $admin_id ?>">
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" value="<?= htmlspecialchars($admin_data['username']) ?>" required>

                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($admin_data['email']) ?>" required>

                    <label for="gym_id">Gym:</label>
                    <select id="gym_id" name="gym_id" required>
                        <option value="0" <?= is_null($admin_data['gym_id']) ? 'selected' : '' ?>>None</option>
                        <?php while ($gym = $gyms_result->fetch_assoc()): ?>
                            <option value="<?= $gym['gym_id'] ?>" <?= $admin_data['gym_id'] == $gym['gym_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($gym['gym_name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>

                    <label for="password">New Password (leave blank to keep current password):</label>
                    <input type="password" id="password" name="password" required>

                    <button type="submit" class="btn btn-primary">Update Admin</button>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
