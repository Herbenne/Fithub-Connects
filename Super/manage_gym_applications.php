<?php
session_start();

// Check if the user is logged in and is a superadmin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_role'] !== 'superadmin') {
    header("Location: Admin/admin_login_form.php");
    exit();
}

require 'db_connection.php'; // Include database connection

// Handle gym application actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve_gym'])) {
        $gym_id = $_POST['gym_id'];

        // Approve gym application
        $stmt = $db_connection->prepare("UPDATE gyms_applications SET status = 'approved' WHERE id = ?");
        $stmt->bind_param("i", $gym_id);
        $stmt->execute();

        // Add approved gym to the gyms table
        $gym_data_query = "SELECT * FROM gyms_applications WHERE id = $gym_id";
        $gym_data_result = $db_connection->query($gym_data_query);
        if (!$gym_data_result) {
            die("Query failed: " . $db_connection->error);
        }
        $gym_data = $gym_data_result->fetch_assoc();

        $insert_stmt = $db_connection->prepare("INSERT INTO gyms (gym_name, gym_location, gym_phone_number, gym_description, gym_amenities) VALUES (?, ?, ?, ?, ?)");
        $insert_stmt->bind_param("sssss", $gym_data['gym_name'], $gym_data['gym_location'], $gym_data['gym_phone_number'], $gym_data['gym_description'], $gym_data['gym_amenities']);
        $insert_stmt->execute();

        $stmt->close();
        $insert_stmt->close();
    } elseif (isset($_POST['reject_gym'])) {
        $gym_id = $_POST['gym_id'];

        // Reject gym application
        $stmt = $db_connection->prepare("UPDATE gyms_applications SET status = 'rejected' WHERE id = ?");
        $stmt->bind_param("i", $gym_id);
        $stmt->execute();
        $stmt->close();
    }
}

// Fetch all pending gym applications
$applications_query = "SELECT * FROM gyms_applications WHERE status = 'pending'";
$applications_result = $db_connection->query($applications_query);

// Check for SQL query errors
if (!$applications_result) {
    die("Query failed: " . $db_connection->error);
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Gym Applications</title>
    <link rel="stylesheet" href="styles.css">
</head>

<body>
    <header>
        <h1>Manage Gym Applications</h1>
    </header>

    <nav>
        <a href="superadmin_dashboard.php">Back to Dashboard</a>
        <a href="../Admin/admin_login_form.php">Logout</a>
    </nav>

    <div class="container">
        <h2>Pending Gym Applications</h2>
        <table border="1">
            <tr>
                <th>Gym Name</th>
                <th>Location</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
            <?php if ($applications_result->num_rows > 0) {
                while ($row = $applications_result->fetch_assoc()) { ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['gym_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['gym_location']); ?></td>
                        <td><?php echo htmlspecialchars($row['status']); ?></td>
                        <td>
                            <form method="POST" action="">
                                <input type="hidden" name="gym_id" value="<?php echo $row['id']; ?>">
                                <button type="submit" name="approve_gym">Approve</button>
                                <button type="submit" name="reject_gym">Reject</button>
                            </form>
                        </td>
                    </tr>
                <?php }
            } else { ?>
                <tr>
                    <td colspan="4">No pending applications.</td>
                </tr>
            <?php } ?>
        </table>
    </div>
</body>

</html>