<?php
session_start();
include '../config/database.php';

// Ensure user is superadmin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: login.php");
    exit();
}

// Available duration options
$duration_options = [1, 3, 6, 12]; // Common membership durations in months

// Fetch all gyms for dropdown
$gyms_query = "SELECT gym_id, gym_name FROM gyms WHERE status = 'approved'";
$gyms = $db_connection->query($gyms_query);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add New Membership Plan</title>
    <link rel="stylesheet" href="../assets/css/mains.css">
    <link rel="stylesheet" href="../assets/css/add_plans.css">
    <link rel="stylesheet" href="../assets/css/unified-theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <a href="manage_membership_plans.php" class="back-btn">← Back to Plans</a>
        <h2>Add New Membership Plan</h2>
        
        <form action="../actions/create_plan.php" method="POST" class="plan-form">
            <div class="form-group">
                <label>Gym</label>
                <select name="gym_id" required>
                    <option value="">Select a Gym</option>
                    <?php while ($gym = $gyms->fetch_assoc()): ?>
                        <option value="<?php echo $gym['gym_id']; ?>">
                            <?php echo htmlspecialchars($gym['gym_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Plan Name</label>
                <input type="text" name="plan_name" required>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="4" required></textarea>
            </div>

            <div class="form-group price-input">
                <label>Price (PHP)</label>
                <input type="number" name="price" required min="0" step="0.01">
            </div>

            <div class="form-group">
                <label>Duration *</label>
                <select name="duration" required>
                    <?php foreach ($duration_options as $months): ?>
                        <option value="<?php echo $months; ?>">
                            <?php echo $months . ' ' . ($months == 1 ? 'month' : 'months'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="submit-btn">Create Plan</button>
        </form>
    </div>
</body>
</html>