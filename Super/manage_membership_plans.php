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
    <script src="https://kit.fontawesome.com/b098b18a13.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="./superAdminCss/manageMembershipPlan.css">
</head>

<body>
    <div class="dashboard-container">
        <header>
            <h1>Manage Membership Plan</h1>
        </header>

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

        <main>
            <div class="card">
                <h2 class="spanlabel">Add New Membership Plan</h2>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="gym_id">Gym ID:</label>
                        <input type="number" id="gym_id" name="gym_id" required>
                    </div>
                    <div class="form-group">
                        <label for="plan_name">Plan Name:</label>
                        <input type="text" id="plan_name" name="plan_name" required>
                    </div>
                    <div class="form-group">
                        <label for="duration">Duration (days):</label>
                        <input type="number" id="duration" name="duration" required>
                    </div>
                    <div class="form-group">
                        <label for="price">Price:</label>
                        <input type="number" step="0.01" id="price" name="price" required>
                    </div>
                    <button type="submit" name="add_plan" class="btn btn-primary">Add Plan</button>
                </form>
            </div>

            <div class="card">
                <h2>Existing Membership Plans</h2>
                <div class="table-responsive">
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
                                    <td data-label="ID"><?php echo htmlspecialchars($plan['id']); ?></td>
                                    <td data-label="Gym ID"><?php echo htmlspecialchars($plan['gym_id']); ?></td>
                                    <td data-label="Plan Name"><?php echo htmlspecialchars($plan['plan_name']); ?></td>
                                    <td data-label="Duration"><?php echo htmlspecialchars($plan['duration']); ?></td>
                                    <td data-label="Price"><?php echo htmlspecialchars($plan['price']); ?></td>
                                    <td data-label="Actions">
                                        <button type="button" onclick="editPlan(<?php echo htmlspecialchars(json_encode($plan)); ?>)" class="btn btn-edit">Edit</button>
                                        <a href="?delete_id=<?php echo $plan['id']; ?>" class="btn btn-delete" onclick="return confirm('Are you sure?');">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
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
</body>
</html>