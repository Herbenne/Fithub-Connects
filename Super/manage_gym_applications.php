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
    <script src="https://kit.fontawesome.com/b098b18a13.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="./superAdminCss/manageApplication.css">
</head>

<body>
    <div class="dashboard-container">
        <header>
            <h1>Manage Gym Applications</h1>
        </header>
        <nav>
            <a href="superadmin_dashboard.php"><i class="fa-solid fa-table-columns"></i>Dashboard</a>
            <a href="manage_admins.php"><i class="fa-solid fa-lock"></i>Manage Admins</a>
            <a href="manage_users.php"><i class="fa-solid fa-user"></i>Manage Users</a>
            <a href="paymentlist.php"><i class="fa-solid fa-money-bill"></i>View Payment</a>
            <a href="sadmin.php"><i class="fa-solid fa-gear"></i>Site Settings</a>
            <a href="manage_gyms.php"><i class="fa-solid fa-dumbbell"></i>Gyms</a>
            <a href="backup_restore.php"><i class="fa-solid fa-file"></i>Backup & Restore</a>
            <a href="../Admin/admin_login_form.php"><i class="fa-solid fa-right-from-bracket"></i>Logout</a>
        </nav>

        <main>

            <div class="card">
                <h2 class="spanlabel">Pending Gym Applications</h2>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Gym Name</th>
                                <th>Location</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($applications_result->num_rows > 0) {
                                while ($row = $applications_result->fetch_assoc()) { ?>
                                    <tr>
                                        <td data-label="Gym Name"><?php echo htmlspecialchars($row['gym_name']); ?></td>
                                        <td data-label="Location"><?php echo htmlspecialchars($row['gym_location']); ?></td>
                                        <td data-label="Status"><?php echo htmlspecialchars($row['status']); ?></td>
                                        <td data-label="Actions">
                                            <form method="POST" action="">
                                                <input type="hidden" name="gym_id" value="<?php echo $row['id']; ?>">
                                                <button type="submit" name="approve_gym" class="btn btn-primary">Approve</button>
                                                <button type="submit" name="reject_gym" class="btn btn-delete">Reject</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php }
                            } else { ?>
                                <tr>
                                    <td colspan="4">No pending applications.</td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>

</html>