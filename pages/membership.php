<?php
session_start();
include '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$gym_id = $_GET['gym_id'] ?? null;

if (!$gym_id) {
    echo "Invalid gym.";
    exit();
}

// Fetch membership plans for the selected gym
$query = "SELECT id, name, price, duration FROM membership_plans WHERE gym_id = ?";
$stmt = $db_connection->prepare($query);
$stmt->bind_param("i", $gym_id);
$stmt->execute();
$result = $stmt->get_result();

// Check if the gym has plans
if ($result->num_rows === 0) {
    echo "No membership plans available for this gym.";
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Membership Plans</title>
    <link rel="stylesheet" href="../assets/css/mains.css">
    <link rel="stylesheet" href="../assets/css/manage_common.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <div class="membership-container">
        <a href="gym_details.php?gym_id=<?php echo $gym_id; ?>" class="back-btn">← Back to Gym Details</a>
        <h2>Select a Membership Plan</h2>
        
        <form action="../actions/process_payment.php" method="POST">
            <input type="hidden" name="gym_id" value="<?php echo htmlspecialchars($gym_id); ?>">
            
            <div class="plan-options">
                <?php while ($plan = $result->fetch_assoc()): ?>
                    <div class="plan-option">
                        <input type="radio" name="membership_plan" 
                               id="plan_<?php echo $plan['id']; ?>"
                               value="<?php echo htmlspecialchars($plan['id']); ?>" required>
                        <label for="plan_<?php echo $plan['id']; ?>">
                            <?php echo htmlspecialchars($plan['name']); ?> - 
                            PHP <?php echo htmlspecialchars($plan['price']); ?> 
                            (<?php echo htmlspecialchars($plan['duration']); ?>)
                        </label>
                    </div>
                <?php endwhile; ?>
            </div>
            
            <button type="submit" class="submit-btn">Proceed to Payment</button>
        </form>
    </div>
</body>
</html>