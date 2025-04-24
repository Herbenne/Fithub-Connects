<?php
session_start();
include '../config/database.php';

// Ensure user is superadmin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: login.php");
    exit();
}

// Add debugging to check for pending applications
$check_query = "SELECT COUNT(*) as count FROM gyms WHERE status = 'pending'";
$check_result = $db_connection->query($check_query);
$pending_count = $check_result->fetch_assoc()['count'];
error_log("Found {$pending_count} pending gym applications");

// Fetch pending gym applications
$query = "SELECT g.*, u.username, u.first_name, u.last_name, u.email, u.contact_number
          FROM gyms g 
          JOIN users u ON g.owner_id = u.id 
          WHERE g.status = 'pending' 
          ORDER BY g.created_at DESC";
$result = $db_connection->query($query);

if (!$result) {
    error_log("Query error: " . $db_connection->error);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Gym Applications - FitHub</title>
    <link rel="stylesheet" href="../assets/css/mains.css">
    <link rel="stylesheet" href="../assets/css/manage_gym.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* Additional styling for the status indicator */
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            color: white;
        }
        .status-pending {
            background-color: #ff9800;
        }
        .document-indicator {
            color: #4CAF50;
            margin-left: 5px;
        }
        .no-document {
            color: #f44336;
        }
        /* Add loading overlay styles */
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            color: white;
        }
        .spinner {
            border: 5px solid #f3f3f3;
            border-top: 5px solid #3498db;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 2s linear infinite;
            margin-bottom: 20px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Loading overlay -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="spinner"></div>
        <h3>Processing Request...</h3>
        <p>Please wait while we process your request.</p>
    </div>

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

        <?php if ($result && $result->num_rows > 0): ?>
            <table class="gyms-table">
                <thead>
                    <tr>
                        <th>Gym Name</th>
                        <th>Location</th>
                        <th>Owner</th>
                        <th>Documents</th>
                        <th>Submission Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($application = $result->fetch_assoc()): 
                        // Check if legal documents exist
                        $has_documents = !empty($application['legal_documents']);
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($application['gym_name']); ?></td>
                            <td><?php echo htmlspecialchars($application['gym_location']); ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?></strong>
                                <br>
                                <small><?php echo htmlspecialchars($application['email']); ?></small>
                                <br>
                                <small><?php echo htmlspecialchars($application['contact_number']); ?></small>
                            </td>
                            <td>
                                <?php if ($has_documents): ?>
                                    <span class="document-indicator">
                                        <i class="fas fa-file-alt"></i> Documents Submitted
                                    </span>
                                <?php else: ?>
                                    <span class="document-indicator no-document">
                                        <i class="fas fa-exclamation-triangle"></i> No Documents
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($application['created_at'])); ?></td>
                            <td class="actions">
                                <a href="view_application.php?gym_id=<?php echo $application['gym_id']; ?>" class="view-btn">
                                    <i class="fas fa-eye"></i> View Details
                                </a>
                                <form action="../actions/approve_gym.php" method="POST" style="display: inline;" onsubmit="showLoading()">
                                    <input type="hidden" name="gym_id" value="<?php echo $application['gym_id']; ?>">
                                    <button type="submit" class="approve-btn" 
                                            onclick="return confirm('Are you sure you want to approve this application?')">
                                        <i class="fas fa-check"></i> Approve
                                    </button>
                                </form>
                                <form action="../actions/reject_gym.php" method="POST" style="display: inline;" onsubmit="showLoading()">
                                    <input type="hidden" name="gym_id" value="<?php echo $application['gym_id']; ?>">
                                    <button type="submit" class="reject-btn" 
                                            onclick="return confirm('Are you sure you want to reject this application?')">
                                        <i class="fas fa-times"></i> Reject
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="no-applications">
                <i class="fas fa-info-circle"></i>
                <p>No pending gym applications found.</p>
            </div>
        <?php endif; ?>
    </div>

    <script>
    function showLoading() {
        document.getElementById('loadingOverlay').style.display = 'flex';
        return true;
    }
    
    // Also show loading screen when View Details is clicked
    document.querySelectorAll('.view-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            showLoading();
        });
    });
    </script>
</body>
</html>