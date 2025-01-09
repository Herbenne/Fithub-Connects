<?php
session_start();

// Check if the user is logged in and is a superadmin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_role'] !== 'superadmin') {
    header("Location: Admin/admin_login_form.php");
    exit();
}

require 'db_connection.php'; // Include database connection

// Retrieve the gym ID from the URL
if (isset($_GET['gym_id'])) {
    $gym_id = $_GET['gym_id'];

    // Fetch the gym details from the database
    $stmt = $db_connection->prepare("SELECT * FROM gyms WHERE gym_id = ?");
    $stmt->bind_param("i", $gym_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $gym = $result->fetch_assoc();
    $stmt->close();

    if (!$gym) {
        die("Gym not found.");
    }

    // Handle gym update form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_gym'])) {
        $gym_name = $_POST['gym_name'];
        $gym_location = $_POST['gym_location'];
        $gym_phone_number = $_POST['gym_phone_number'];
        $gym_description = $_POST['gym_description'];
        $gym_amenities = $_POST['gym_amenities'];

        // Update the gym details in the database
        $update_stmt = $db_connection->prepare("UPDATE gyms SET gym_name = ?, gym_location = ?, gym_phone_number = ?, gym_description = ?, gym_amenities = ? WHERE gym_id = ?");
        $update_stmt->bind_param("sssssi", $gym_name, $gym_location, $gym_phone_number, $gym_description, $gym_amenities, $gym_id);

        if ($update_stmt->execute()) {
            $update_message = "Gym updated successfully!";
        } else {
            $update_message = "Error: " . $update_stmt->error;
        }

        $update_stmt->close();
    }
} else {
    die("Gym ID is required.");
}

$db_connection->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Gym</title>
    <link rel="stylesheet" href="styles.css"> <!-- Link to CSS -->
</head>

<body>
    <header>
        <h1>Edit Gym</h1>
    </header>

    <nav>
        <a href="superadmin_dashboard.php">Back to Dashboard</a>
        <a href="manage_admins.php">Manage Admins</a>
        <a href="manage_users.php">Manage Users</a>
        <a href="manage_membership_plans.php">Manage Membership Plans</a>
        <a href="attendance_logs.php">View Attendance Logs</a>
        <a href="../Admin/admin_login_form.php">Logout</a>
    </nav>

    <div class="container">
        <div class="card">
            <h2>Edit Gym Details</h2>

            <form method="POST" action="edit_gym.php?gym_id=<?= $gym_id ?>">
                <label for="gym_name">Gym Name:</label>
                <input type="text" id="gym_name" name="gym_name" value="<?= htmlspecialchars($gym['gym_name']) ?>" required>

                <label for="gym_location">Gym Location:</label>
                <input type="text" id="gym_location" name="gym_location" value="<?= htmlspecialchars($gym['gym_location']) ?>" required>

                <label for="gym_phone_number">Gym Phone Number:</label>
                <input type="text" id="gym_phone_number" name="gym_phone_number" value="<?= htmlspecialchars($gym['gym_phone_number']) ?>" required>

                <label for="gym_description">Gym Description:</label>
                <textarea id="gym_description" name="gym_description" required><?= htmlspecialchars($gym['gym_description']) ?></textarea>

                <label for="gym_amenities">Gym Amenities:</label>
                <textarea id="gym_amenities" name="gym_amenities" required><?= htmlspecialchars($gym['gym_amenities']) ?></textarea>

                <button type="submit" name="update_gym">Update Gym</button>
            </form>

            <?php if (isset($update_message)) : ?>
                <p><?= htmlspecialchars($update_message) ?></p>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>