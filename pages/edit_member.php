<?php
session_start();
require_once __DIR__ . "/../config/database.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Access denied.");
}

$member_id = $_GET['id'] ?? null;
if (!$member_id) {
    die("No member specified.");
}

// Fetch member details with status
$query = "
    SELECT gm.*, mp.plan_name, u.username, u.email
    FROM gym_members gm
    JOIN users u ON gm.user_id = u.id
    JOIN membership_plans mp ON gm.plan_id = mp.plan_id
    WHERE gm.id = ?";

$stmt = $db_connection->prepare($query);
$stmt->bind_param("i", $member_id);
$stmt->execute();
$member = $stmt->get_result()->fetch_assoc();

// Fetch available plans
$query = "SELECT * FROM membership_plans WHERE gym_id = ?";
$stmt = $db_connection->prepare($query);
$stmt->bind_param("i", $member['gym_id']);
$stmt->execute();
$plans = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Member - FitHub</title>
    <link rel="stylesheet" href="../assets/css/mains.css">
    <link rel="stylesheet" href="../assets/css/edit_member.css">
    <link rel="stylesheet" href="../assets/css/unified-theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <div class="container">
        <a href="manage_members.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Members
        </a>

        <div class="edit-member-card">
            <h2>Edit Member Membership</h2>
            <form action="../actions/update_member.php" method="POST" class="edit-member-form">
                <input type="hidden" name="member_id" value="<?php echo $member_id; ?>">
                
                <div class="form-group">
                    <label>Username:</label>
                    <input type="text" value="<?php echo htmlspecialchars($member['username']); ?>" readonly class="form-control readonly">
                </div>

                <div class="form-group">
                    <label>Email:</label>
                    <input type="email" value="<?php echo htmlspecialchars($member['email']); ?>" readonly class="form-control readonly">
                </div>

                <div class="form-group">
                    <label>Membership Status:</label>
                    <select name="status" required class="form-control">
                        <option value="active" <?php echo ($member['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo ($member['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Membership Plan:</label>
                    <select name="plan_id" required class="form-control">
                        <?php while ($plan = $plans->fetch_assoc()): ?>
                            <option value="<?php echo $plan['plan_id']; ?>" 
                                    <?php echo ($plan['plan_id'] == $member['plan_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($plan['plan_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>End Date:</label>
                    <input type="date" name="end_date" value="<?php echo $member['end_date']; ?>" required class="form-control">
                </div>

                <div class="button-group">
                    <button type="submit" class="submit-btn">Update Membership</button>
                    <a href="manage_members.php" class="cancel-btn">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>