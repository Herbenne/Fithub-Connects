<?php
session_start();
include '../config/database.php';

// Ensure user is superadmin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: login.php");
    exit();
}

// Fetch pending gym applications
$query = "SELECT g.*, u.username, u.first_name, u.last_name 
          FROM gyms g 
          JOIN users u ON g.owner_id = u.id 
          WHERE g.status = 'pending' 
          ORDER BY g.created_at DESC";
$result = $db_connection->query($query);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Gym Applications - GymHub</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/manage_gyms.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
        <h2>Pending Gym Applications</h2>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                <?php 
                $message = 'Application was successfully ';
                $message .= $_GET['success'] === 'rejected' ? 'rejected.' : 'approved.';
                echo $message;
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>

        <table class="gyms-table">
            <thead>
                <tr>
                    <th>Gym Name</th>
                    <th>Location</th>
                    <th>Owner</th>
                    <th>Submission Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($application = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($application['gym_name']); ?></td>
                            <td><?php echo htmlspecialchars($application['gym_location']); ?></td>
                            <td>
                                <?php echo htmlspecialchars($application['first_name'] . ' ' . 
                                                          $application['last_name']); ?>
                                <br>
                                <small>(<?php echo htmlspecialchars($application['username']); ?>)</small>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($application['created_at'])); ?></td>
                            <td class="actions">
                                <button class="view-btn" onclick="viewDetails(<?php echo $application['gym_id']; ?>)">
                                    <i class="fas fa-eye"></i> View
                                </button>
                                <form action="../actions/approve_gym.php" method="POST" style="display: inline;">
                                    <input type="hidden" name="gym_id" value="<?php echo $application['gym_id']; ?>">
                                    <button type="submit" class="approve-btn" 
                                            onclick="return confirm('Are you sure you want to approve this application?')">
                                        <i class="fas fa-check"></i> Approve
                                    </button>
                                </form>
                                <form action="../actions/reject_gym.php" method="POST" style="display: inline;">
                                    <input type="hidden" name="gym_id" value="<?php echo $application['gym_id']; ?>">
                                    <button type="submit" class="reject-btn" 
                                            onclick="return confirm('Are you sure you want to reject this application?')">
                                        <i class="fas fa-times"></i> Reject
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="no-applications">No pending applications found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <script>
    function viewDetails(gymId) {
        window.location.href = `view_application.php?gym_id=${gymId}`;
    }
    </script>
</body>
</html>