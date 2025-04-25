<?php
session_start();
include '../config/database.php';

// Handle delete operation
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_plan'])) {
    $plan_id = $_POST['plan_id'];

    // First verify the plan exists
    $verify_query = "SELECT * FROM membership_plans WHERE plan_id = ?";
    $stmt = $db_connection->prepare($verify_query);
    $stmt->bind_param("i", $plan_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Plan exists, proceed with deletion
        $delete_query = "DELETE FROM membership_plans WHERE plan_id = ?";
        $stmt = $db_connection->prepare($delete_query);
        $stmt->bind_param("i", $plan_id);
        
        if ($stmt->execute()) {
            header("Location: manage_membership_plans.php?success=deleted");
            exit();
        } else {
            header("Location: manage_membership_plans.php?error=delete_failed");
            exit();
        }
    } else {
        header("Location: manage_membership_plans.php?error=invalid_plan");
        exit();
    }
}

// Ensure user is superadmin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: login.php");
    exit();
}

// Get all membership plans with gym information
$query = "SELECT mp.plan_id, mp.plan_name, mp.description, mp.price, mp.duration, g.gym_name 
          FROM membership_plans mp 
          JOIN gyms g ON mp.gym_id = g.gym_id 
          ORDER BY g.gym_name, mp.plan_name";
$result = $db_connection->query($query);

if ($result === false) {
    error_log("Error fetching plans: " . $db_connection->error);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Membership Plans</title>
    <link rel="stylesheet" href="../assets/css/mains.css">

    <link rel="stylesheet" href="../assets/css/manage_membership_plan.css">
    <link rel="stylesheet" href="../assets/css/unified-theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="../assets/js/manage_plans.js" defer></script>
</head>
<body>
    <div class="dashboard-container">
        <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
        <h2>Manage Membership Plans</h2>
        
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                Membership plan was successfully <?php echo $_GET['success'] == 1 ? 'created' : 'updated'; ?>!
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error">
                An error occurred. Please try again.
            </div>
        <?php endif; ?>

        <div class="action-bar">
            <button class="add-btn" onclick="location.href='add_plan.php'" style="background-color: #4CAF50; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; margin: 10px 0;"> 
                <i class="fas fa-plus"></i> Add New Plan
            </button>
        </div>
        
        <table class="plans-table">
            <thead>
                <tr>
                    <th>Gym</th>
                    <th>Plan Name</th>
                    <th>Description</th>
                    <th>Price</th>
                    <th>Duration</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                if ($result && $result->num_rows > 0) {
                    while ($plan = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($plan['gym_name']); ?></td>
                            <td><?php echo htmlspecialchars($plan['plan_name']); ?></td>
                            <td><?php echo htmlspecialchars($plan['description']); ?></td>
                            <td>PHP <?php echo htmlspecialchars($plan['price']); ?></td>
                            <td><?php echo htmlspecialchars($plan['duration']); ?></td>
                            <td>
                                <button class="action-btn edit-btn" 
                                        onclick="window.location.href='edit_plan.php?plan_id=<?php echo $plan['plan_id']; ?>'">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this plan?');">
                                    <input type="hidden" name="plan_id" value="<?php echo $plan['plan_id']; ?>">
                                    <input type="hidden" name="delete_plan" value="1">
                                    <button type="submit" class="action-btn delete-btn">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile;
                } else {
                    echo '<tr><td colspan="6" class="no-records">No membership plans found.</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
</body>
</html>