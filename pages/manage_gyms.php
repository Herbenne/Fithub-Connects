<?php
session_start();
include '../config/database.php';

// Ensure user is superadmin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: login.php");
    exit();
}

// Update the query at the top of the file
$query = "SELECT g.*, 
          u.username as owner_name,
          COALESCE(ROUND(AVG(gr.rating), 1), 0) as rating,
          COUNT(gr.review_id) as review_count
          FROM gyms g 
          LEFT JOIN users u ON g.owner_id = u.id 
          LEFT JOIN gym_reviews gr ON g.gym_id = gr.gym_id
          WHERE g.status = 'approved' 
          GROUP BY g.gym_id, g.gym_name, g.gym_location, u.username
          ORDER BY g.gym_name";
$result = $db_connection->query($query);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Gyms - FitHub</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/mains.css">
    <link rel="stylesheet" href="../assets/css/rating.css">
    <link rel="stylesheet" href="../assets/css/manage_common.css">
    <link rel="stylesheet" href="../assets/css/unified-theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
        <h2>Manage Approved Gyms</h2>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                <?php 
                $message = $_GET['success'] === 'delete' ? 'Gym was successfully deleted.' : 'Gym was successfully updated.';
                echo $message;
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error">
                <?php 
                $message = 'An error occurred. Please try again.';
                if ($_GET['error'] === 'invalid') {
                    $message = 'Invalid request.';
                }
                echo $message;
                ?>
            </div>
        <?php endif; ?>

        <div class="table-wrapper">
            <table class="gyms-table">
                <thead>
                    <tr>
                        <th>Gym Name</th>
                        <th>Location</th>
                        <th>Owner</th>
                        <th>Rating</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($gym = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($gym['gym_name']); ?></td>
                            <td><?php echo htmlspecialchars($gym['gym_location']); ?></td>
                            <td><?php echo htmlspecialchars($gym['owner_name'] ?? 'No owner'); ?></td>
                            <td>
                                <?php 
                                $rating = floatval($gym['rating']);
                                echo number_format($rating, 1) . ' ';
                                for($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star <?php echo $i <= $rating ? 'active' : ''; ?>"></i>
                                <?php endfor; ?>
                                <span class="review-count">(<?php echo $gym['review_count']; ?>)</span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="action-btn edit-btn" onclick="editGym(<?php echo $gym['gym_id']; ?>)">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="action-btn delete-btn" onclick="deleteGym(<?php echo $gym['gym_id']; ?>)">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
    function editGym(gymId) {
        window.location.href = 'gym_edit.php?gym_id=' + gymId;
    }

    function deleteGym(gymId) {
        if (confirm('Are you sure you want to delete this gym from the system? This action cannot be reversed.')) {
            window.location.href = '../actions/delete_gym.php?gym_id=' + gymId;
        }
    }

    // Add to existing script section
    document.addEventListener('DOMContentLoaded', function() {
        // Add hover effect to table rows
        const rows = document.querySelectorAll('.gyms-table tbody tr');
        rows.forEach(row => {
            row.addEventListener('mouseenter', () => {
                row.style.transition = 'background-color 0.3s ease';
            });
        });

        // Smooth scroll to alerts
        if(document.querySelector('.alert')) {
            document.querySelector('.alert').scrollIntoView({ 
                behavior: 'smooth', 
                block: 'start' 
            });
        }
    });
    </script>
</body>
</html>