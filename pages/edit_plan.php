<?php
session_start();
include '../config/database.php';

// Ensure user is superadmin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: login.php");
    exit();
}

// Get plan ID from URL
$plan_id = $_GET['plan_id'] ?? null;

if (!$plan_id) {
    header("Location: manage_membership_plans.php");
    exit();
}

// Fetch plan details
$query = "SELECT mp.*, g.gym_name 
          FROM membership_plans mp 
          JOIN gyms g ON mp.gym_id = g.gym_id 
          WHERE mp.plan_id = ?";
$stmt = $db_connection->prepare($query);
$stmt->bind_param("i", $plan_id);
$stmt->execute();
$plan = $stmt->get_result()->fetch_assoc();

if (!$plan) {
    header("Location: manage_membership_plans.php");
    exit();
}

// Fetch all gyms for dropdown
$gyms_query = "SELECT gym_id, gym_name FROM gyms WHERE status = 'approved'";
$gyms = $db_connection->query($gyms_query);

// Function to extract number from duration string
function extractDuration($duration) {
    return (int) filter_var($duration, FILTER_SANITIZE_NUMBER_INT);
}

// Current duration value from database
$current_duration = extractDuration($plan['duration']);

// Available duration options
$duration_options = [1, 3, 6, 12]; // Common membership durations in months
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Membership Plan - FitHub</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/mains.css">
    <link rel="stylesheet" href="../assets/css/manage_plans.css">
    <link rel="stylesheet" href="../assets/css/edit_plan.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <a href="manage_membership_plans.php" class="back-btn">← Back to Plans</a>
        <h2>Edit Membership Plan</h2>
        
        <form action="../actions/update_plan.php" method="POST" class="plan-form">
            <input type="hidden" name="plan_id" value="<?php echo htmlspecialchars($plan['plan_id']); ?>">
            
            <div class="form-group">
                <label>Gym</label>
                <select name="gym_id" required>
                    <?php while ($gym = $gyms->fetch_assoc()): ?>
                        <option value="<?php echo $gym['gym_id']; ?>" 
                                <?php echo $gym['gym_id'] == $plan['gym_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($gym['gym_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Plan Name</label>
                <input type="text" name="plan_name" value="<?php echo htmlspecialchars($plan['plan_name']); ?>" required>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="4" required><?php echo htmlspecialchars($plan['description']); ?></textarea>
            </div>

            <div class="form-group">
                <label>Price (PHP)</label>
                <input type="number" name="price" value="<?php echo htmlspecialchars($plan['price']); ?>" required>
            </div>

            <div class="form-group">
                <label>Duration</label>
                <select name="duration" required>
                    <?php foreach ($duration_options as $months): ?>
                        <option value="<?php echo $months; ?>" 
                                <?php echo $current_duration == $months ? 'selected' : ''; ?>>
                            <?php echo $months . ' ' . ($months == 1 ? 'month' : 'months'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="submit-btn">Update Plan</button>
        </form>
    </div>
</body>
</html>