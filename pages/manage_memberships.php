<?php
session_start();
include '../config/database.php';

// Ensure user is superadmin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: login.php");
    exit();
}

// Get memberships with user and gym details
$query = "SELECT gm.*, g.gym_name, 
          CONCAT(u.first_name, ' ', u.last_name) as member_name,
          mp.plan_name, mp.price
          FROM gym_members gm
          JOIN users u ON gm.user_id = u.id
          JOIN gyms g ON gm.gym_id = g.gym_id
          JOIN membership_plans mp ON gm.plan_id = mp.plan_id
          ORDER BY gm.joined_at DESC";
$result = $db_connection->query($query);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Memberships</title>
    <link rel="stylesheet" href="../assets/css/mains.css">
    <link rel="stylesheet" href="../assets/css/manage_common.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* Copy styles from manage_users.php and add: */
        .status-active { color: #4CAF50; }
        .status-expired { color: #f44336; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
        <h2>Manage Memberships</h2>
        
        <table class="user-table">
            <thead>
                <tr>
                    <th>Member</th>
                    <th>Gym</th>
                    <th>Plan</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($membership = $result->fetch_assoc()): 
                    $is_active = strtotime($membership['end_date']) > time();
                ?>
                    <tr>
                        <td><?php echo htmlspecialchars($membership['member_name']); ?></td>
                        <td><?php echo htmlspecialchars($membership['gym_name']); ?></td>
                        <td><?php echo htmlspecialchars($membership['plan_name']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($membership['start_date'])); ?></td>
                        <td><?php echo date('M d, Y', strtotime($membership['end_date'])); ?></td>
                        <td class="<?php echo $is_active ? 'status-active' : 'status-expired'; ?>">
                            <?php echo $is_active ? 'Active' : 'Expired'; ?>
                        </td>
                        <td>
                            <button class="action-btn edit-btn" 
                                    onclick="editMembership(<?php echo $membership['id']; ?>)">
                                Edit
                            </button>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</body>
</html>