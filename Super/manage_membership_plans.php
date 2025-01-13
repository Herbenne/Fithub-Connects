<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: Admin/admin_login_form.php");
    exit();
}

require 'db_connection.php'; // Include database connection

// Handle form submission for adding a new membership plan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_plan'])) {
    $gym_id = $_POST['gym_id'];
    $plan_name = $_POST['plan_name'];
    $duration = $_POST['duration'];
    $price = $_POST['price'];

    $query = "INSERT INTO membership_plans (gym_id, plan_name, duration, price) VALUES (?, ?, ?, ?)";
    $stmt = $db_connection->prepare($query);
    $stmt->bind_param("isid", $gym_id, $plan_name, $duration, $price);
    $stmt->execute();
    $stmt->close();
}

// Handle form submission for editing an existing membership plan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_plan'])) {
    $plan_id = $_POST['plan_id'];
    $gym_id = $_POST['gym_id'];
    $plan_name = $_POST['plan_name'];
    $duration = $_POST['duration'];
    $price = $_POST['price'];

    $query = "UPDATE membership_plans SET gym_id = ?, plan_name = ?, duration = ?, price = ? WHERE id = ?";
    $stmt = $db_connection->prepare($query);
    $stmt->bind_param("isidi", $gym_id, $plan_name, $duration, $price, $plan_id);
    $stmt->execute();
    $stmt->close();
}

// Handle deletion of a membership plan
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    $query = "DELETE FROM membership_plans WHERE id = ?";
    $stmt = $db_connection->prepare($query);
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $stmt->close();
}

// Fetch membership plans from the database
$query = "SELECT * FROM membership_plans";
$result = $db_connection->query($query);
$membership_plans = $result->fetch_all(MYSQLI_ASSOC);

$db_connection->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Membership Plans</title>
    <link rel="stylesheet" href="./superAdminCss/manageMembershipPlans.css">
</head>

<body>

    <nav>
        <a href="superadmin_dashboard.php"><i class="fa-solid fa-table-columns"></i>Dashboard</a>
        <a href="manage_users.php"><i class="fa-solid fa-user"></i>Manage Users</a>
        <a href="manage_gyms.php"><i class="fa-solid fa-dumbbell"></i>Gyms</a>
        <a href="manage_gym_applications.php"><i class="fa-solid fa-paperclip"></i>Applications</a>
        <a href="paymentlist.php"><i class="fa-solid fa-money-bill"></i>View Payment</a>
        <a href="sadmin.php"><i class="fa-solid fa-gear"></i>Site Settings</a>
        <a href="backup_restore.php"><i class="fa-solid fa-file"></i>Backup & Restore</a>
        <a href="../Admin/admin_login_form.php"><i class="fa-solid fa-right-from-bracket"></i>Logout</a>
    </nav>

    <div class="container">
        <h1>Manage Membership Plans</h1>

        <h2>Add New Membership Plan</h2>
        <form method="POST" action="">
            <label for="gym_id">Gym ID:</label>
            <input type="number" id="gym_id" name="gym_id" required>

            <label for="plan_name">Plan Name:</label>
            <input type="text" id="plan_name" name="plan_name" required>

            <label for="duration">Duration (days):</label>
            <input type="number" id="duration" name="duration" required>

            <label for="price">Price:</label>
            <input type="number" step="0.01" id="price" name="price" required>

            <button type="submit" name="add_plan">Add Plan</button>
        </form>

        <h2>Existing Membership Plans</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Gym ID</th>
                    <th>Plan Name</th>
                    <th>Duration</th>
                    <th>Price</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($membership_plans as $plan): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($plan['id']); ?></td>
                        <td><?php echo htmlspecialchars($plan['gym_id']); ?></td>
                        <td><?php echo htmlspecialchars($plan['plan_name']); ?></td>
                        <td><?php echo htmlspecialchars($plan['duration']); ?></td>
                        <td><?php echo htmlspecialchars($plan['price']); ?></td>
                        <td>
                            <form method="POST" action="" style="display: inline-block;">
                                <input type="hidden" name="plan_id" value="<?php echo htmlspecialchars($plan['id']); ?>">
                                <input type="hidden" name="gym_id" value="<?php echo htmlspecialchars($plan['gym_id']); ?>">
                                <input type="hidden" name="plan_name" value="<?php echo htmlspecialchars($plan['plan_name']); ?>">
                                <input type="hidden" name="duration" value="<?php echo htmlspecialchars($plan['duration']); ?>">
                                <input type="hidden" name="price" value="<?php echo htmlspecialchars($plan['price']); ?>">
                                <button type="button" onclick="editPlan(<?php echo htmlspecialchars(json_encode($plan)); ?>)">Edit</button>
                            </form>
                            <a href="?delete_id=<?php echo $plan['id']; ?>" onclick="return confirm('Are you sure you want to delete this plan?');">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div id="editModal" class="modal" style="display:none;">
            <div class="modal-content">
                <h2>Edit Membership Plan</h2>
                <form method="POST" action="">
                    <input type="hidden" id="edit_plan_id" name="plan_id">

                    <label for="edit_gym_id">Gym ID:</label>
                    <input type="number" id="edit_gym_id" name="gym_id" required>

                    <label for="edit_plan_name">Plan Name:</label>
                    <input type="text" id="edit_plan_name" name="plan_name" required>

                    <label for="edit_duration">Duration (days):</label>
                    <input type="number" id="edit_duration" name="duration" required>

                    <label for="edit_price">Price:</label>
                    <input type="number" step="0.01" id="edit_price" name="price" required>

                    <button type="submit" name="edit_plan">Save Changes</button>
                    <button type="button" onclick="closeModal()">Cancel</button>
                </form>
            </div>
        </div>

        <script>
            function editPlan(plan) {
                document.getElementById('edit_plan_id').value = plan.id;
                document.getElementById('edit_gym_id').value = plan.gym_id;
                document.getElementById('edit_plan_name').value = plan.plan_name;
                document.getElementById('edit_duration').value = plan.duration;
                document.getElementById('edit_price').value = plan.price;
                document.getElementById('editModal').style.display = 'block';
            }

            function closeModal() {
                document.getElementById('editModal').style.display = 'none';
            }
        </script>
    </div>
</body>

</html>