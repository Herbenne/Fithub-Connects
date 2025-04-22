<?php
session_start();
require_once __DIR__ . "/../config/database.php"; // Ensure correct path

// Check if the user is logged in and is an admin (gym owner)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Access denied.");
}

$gym_owner_id = $_SESSION['user_id'];

// Update the gym query to only get the approved gym
$query = "SELECT gym_id FROM gyms WHERE owner_id = ? AND status = 'approved'";
$stmt = $db_connection->prepare($query);
if (!$stmt) {
    die("Query preparation failed (gyms): " . $db_connection->error);
}

$stmt->bind_param("i", $gym_owner_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("No approved gym found for this owner.");
}

$gym = $result->fetch_assoc();
$gym_id = $gym['gym_id'];

$stmt->close();

// Update the members query to include user_id
$query = "
    SELECT 
        gm.id, 
        gm.user_id,
        u.username, 
        u.email, 
        mp.plan_name, 
        gm.start_date, 
        gm.end_date,
        gm.status
    FROM gym_members gm
    INNER JOIN users u ON gm.user_id = u.id
    INNER JOIN membership_plans mp ON gm.plan_id = mp.plan_id
    WHERE gm.gym_id = ? 
    ORDER BY gm.joined_at DESC";

$stmt = $db_connection->prepare($query);
if (!$stmt) {
    die("Query preparation failed (gym members): " . $db_connection->error);
}

$stmt->bind_param("i", $gym_id);
$stmt->execute();
$result = $stmt->get_result();


$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Members - FitHub</title>
    <link rel="stylesheet" href="../assets/css/mains.css">
    <link rel="stylesheet" href="../assets/css/manage_members.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <div class="container">
        <div class="header-section">
            <a href="dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <h2>Manage Gym Members</h2>
        </div>

        <div class="table-container">
            <table class="members-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Plan Name</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['id']); ?></td>
                                <td><?php echo htmlspecialchars($row['username']); ?></td>
                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                <td><?php echo htmlspecialchars($row['plan_name']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($row['start_date'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($row['end_date'])); ?></td>
                                <td class="actions">
                                    <a href="edit_member.php?id=<?php echo $row['id']; ?>" class="edit-btn">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <form action="../actions/remove_member.php" method="POST" class="delete-form">
                                        <input type="hidden" name="member_id" value="<?php echo $row['id']; ?>">
                                        <button type="submit" class="delete-btn" 
                                                onclick="return confirm('Are you sure you want to remove this member?');">
                                            <i class="fas fa-trash-alt"></i> Remove
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="no-records">No members found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>