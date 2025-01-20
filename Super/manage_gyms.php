<?php
session_start();

// Check if the user is logged in and is a superadmin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_role'] !== 'superadmin') {
    header("Location: Admin/admin_login_form.php");
    exit();
}

require 'db_connection.php'; // Include database connection

// Handle gym creation form submission
$gym_creation_message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_gym'])) {
    $gym_name = $_POST['gym_name'];
    $gym_location = $_POST['gym_location'];
    $gym_phone_number = $_POST['gym_phone_number'];
    $gym_description = $_POST['gym_description'];
    $gym_amenities = $_POST['gym_amenities'];

    // Insert new gym into the database
    $stmt = $db_connection->prepare("INSERT INTO gyms (gym_name, gym_location, gym_phone_number, gym_description, gym_amenities) 
                                     VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $gym_name, $gym_location, $gym_phone_number, $gym_description, $gym_amenities);

    if ($stmt->execute()) {
        $gym_creation_message = "Gym added successfully!";
    } else {
        $gym_creation_message = "Error: " . $stmt->error;
    }

    $stmt->close();
}

// Fetch all gyms for display
$gyms_query = "SELECT * FROM gyms";
$gyms_result = $db_connection->query($gyms_query);

// Close the database connection after all queries are done
$db_connection->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Gyms</title>
    <script src="https://kit.fontawesome.com/b098b18a13.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="./superAdminCss/manageGyms.css">
</head>

<body>
    <div class="dashboard-container">
        <header>
            <h1>Manage Gyms</h1>
        </header>

        <nav>
            <a href="superadmin_dashboard.php"><i class="fa-solid fa-table-columns"></i>Dashboard</a>
            <a href="manage_admins.php"><i class="fa-solid fa-lock"></i>Manage Admins</a>
            <a href="manage_users.php"><i class="fa-solid fa-user"></i>Manage Users</a>
            <a href="manage_gym_applications.php"><i class="fa-solid fa-paperclip"></i>Applications</a>
            <a href="manage_membership_plans.php"><i class="fa-solid fa-user"></i>Membership</a>
            <a href="paymentlist.php"><i class="fa-solid fa-money-bill"></i>View Payment</a>
            <a href="sadmin.php"><i class="fa-solid fa-gear"></i>Site Settings</a>
            <a href="backup_restore.php"><i class="fa-solid fa-file"></i>Backup & Restore</a>
            <a href="../Admin/admin_login_form.php"><i class="fa-solid fa-right-from-bracket"></i>Logout</a>
        </nav>

        <main>


        <div class="card">
                <h2 class="spanlabel">Existing Gyms</h2>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Gym Name</th>
                                <th>Location</th>
                                <th>Phone Number</th>
                                <th>Description</th>
                                <th>Amenities</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $gyms_result->fetch_assoc()) { ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['gym_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['gym_location']); ?></td>
                                    <td><?php echo htmlspecialchars($row['gym_phone_number']); ?></td>
                                    <td><?php echo htmlspecialchars($row['gym_description']); ?></td>
                                    <td><?php echo htmlspecialchars($row['gym_amenities']); ?></td>
                                    <td class="actions">
                                        <a href="edit_gym.php?gym_id=<?php echo $row['gym_id']; ?>" class="btn btn-edit">Edit</a>
                                        <a href="delete_gym.php?gym_id=<?php echo $row['gym_id']; ?>" class="btn btn-delete" onclick="return confirm('Are you sure?');">Delete</a>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <h2><span class="spanlabel">Add New Gym</span></h2>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="gym_name">Gym Name:</label>
                        <input type="text" id="gym_name" name="gym_name" required>
                    </div>
                    <div class="form-group">
                        <label for="gym_location">Gym Location:</label>
                        <input type="text" id="gym_location" name="gym_location" required>
                    </div>
                    <div class="form-group">
                        <label for="gym_phone_number">Gym Phone Number:</label>
                        <input type="text" id="gym_phone_number" name="gym_phone_number" required>
                    </div>
                    <div class="form-group">
                        <label for="gym_description">Gym Description:</label>
                        <textarea id="gym_description" name="gym_description" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="gym_amenities">Gym Amenities:</label>
                        <textarea id="gym_amenities" name="gym_amenities" required></textarea>
                    </div>
                    <div class="gym-btn-container">
                        <button class="btn btn-primary" type="submit" name="add_gym">Add Gym</button>
                    </div>
                </form>
                <?php if (!empty($gym_creation_message)) : ?>
                    <p class="message"><?= htmlspecialchars($gym_creation_message) ?></p>
                <?php endif; ?>
            </div>

            
        </main>
    </div>
</body>

</html>