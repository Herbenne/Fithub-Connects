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
    <title>Edit Member</title>
    <style>
        .form-container {
            max-width: 600px;
            margin: 20px auto;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .button-group {
            margin-top: 20px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 10px;
            text-decoration: none;
        }
        .btn-primary {
            background-color: #4CAF50;
            color: white;
        }
        .btn-secondary {
            background-color: #666;
            color: white;
        }
        .status-active {
            background-color: #4CAF50;
            color: white;
        }
        .status-inactive {
            background-color: #f44336;
            color: white;
        }
    </style>
</head>
<body>
    <h2>Edit Member Membership</h2>
    <form action="../actions/update_member.php" method="POST">
        <input type="hidden" name="member_id" value="<?php echo $member_id; ?>">
        
        <div>
            <label>Username:</label>
            <input type="text" value="<?php echo htmlspecialchars($member['username']); ?>" readonly>
        </div>

        <div>
            <label>Email:</label>
            <input type="email" value="<?php echo htmlspecialchars($member['email']); ?>" readonly>
        </div>

        <div>
            <label>Membership Plan:</label>
            <select name="plan_id" required>
                <?php while ($plan = $plans->fetch_assoc()) { ?>
                    <option value="<?php echo $plan['plan_id']; ?>" 
                            <?php echo ($plan['plan_id'] == $member['plan_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($plan['plan_name']); ?>
                    </option>
                <?php } ?>
            </select>
        </div>

        <div>
            <label>End Date:</label>
            <input type="date" name="end_date" value="<?php echo $member['end_date']; ?>" required>
        </div>

        <button type="submit">Update Membership</button>
        <a href="manage_members.php">Cancel</a>
    </form>
</body>
</html>