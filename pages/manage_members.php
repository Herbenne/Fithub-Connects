<?php
session_start();
require_once __DIR__ . "/../config/database.php";

// Check if the user is logged in and is an admin (gym owner)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php?error=unauthorized");
    exit();
}

$gym_owner_id = $_SESSION['user_id'];

// Update the gym query to only get the approved gym
$query = "SELECT gym_id, gym_name FROM gyms WHERE owner_id = ? AND status = 'approved'";
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

// Get available membership plans for this gym
$plans_query = "SELECT plan_id, plan_name, price, duration FROM membership_plans WHERE gym_id = ? ORDER BY price";
$plans_stmt = $db_connection->prepare($plans_query);
if (!$plans_stmt) {
    die("Query preparation failed (plans): " . $db_connection->error);
}
$plans_stmt->bind_param("i", $gym_id);
$plans_stmt->execute();
$plans_result = $plans_stmt->get_result();
$plans_stmt->close();

// Update the members query to include separate first_name and last_name fields
$query = "
    SELECT 
        gm.id, 
        gm.user_id,
        u.username, 
        u.email,
        u.first_name,
        u.last_name, 
        mp.plan_name, 
        mp.plan_id,
        gm.start_date, 
        gm.end_date,
        CASE 
            WHEN gm.status = 'inactive' THEN 'Inactive'
            WHEN gm.end_date < CURDATE() THEN 'Expired'
            ELSE 'Active'
        END as status
    FROM gym_members gm
    INNER JOIN users u ON gm.user_id = u.id
    INNER JOIN membership_plans mp ON gm.plan_id = mp.plan_id
    WHERE gm.gym_id = ? 
    ORDER BY gm.status ASC, gm.end_date DESC";

$stmt = $db_connection->prepare($query);
if (!$stmt) {
    die("Query preparation failed (gym members): " . $db_connection->error);
}

$stmt->bind_param("i", $gym_id);
$stmt->execute();
$members = $stmt->get_result();
$stmt->close();

// Check for success/error messages
$success_message = '';
$error_message = '';

if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case '1':
            $success_message = 'Member information updated successfully.';
            break;
        case 'member_removed':
            $success_message = 'Member has been removed successfully.';
            break;
    }
}

if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case '1':
            $error_message = 'Failed to update member information.';
            break;
        case 'member_not_found':
            $error_message = 'Member not found.';
            break;
        case 'access_denied':
            $error_message = 'You do not have permission to perform this action.';
            break;
        case 'invalid_request':
            $error_message = 'Invalid request parameters.';
            break;
        default:
            $error_message = urldecode($_GET['error']);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Members - FitHub</title>
    <link rel="stylesheet" href="../assets/css/mains.css">
    <link rel="stylesheet" href="../assets/css/manage_members.css">
    <link rel="stylesheet" href="../assets/css/unified-theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .status-active {
            color: #4CAF50;
            font-weight: bold;
        }
        
        .status-expired {
            color: #f44336;
            font-weight: bold;
        }
        
        .status-inactive {
            color: #ff9800;
            font-weight: bold;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .alert-success {
            background-color: #dff0d8;
            border-color: #d6e9c6;
            color: #3c763d;
        }
        
        .alert-danger {
            background-color: #f2dede;
            border-color: #ebccd1;
            color: #a94442;
        }
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 20px;
            border-radius: 5px;
            width: 50%;
            max-width: 500px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .close-btn {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close-btn:hover {
            color: black;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-section">
            <a href="dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <h2>Manage Members - <?php echo htmlspecialchars($gym['gym_name']); ?></h2>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <div class="table-container">
            <table class="members-table">
                <thead>
                    <tr>
                        <th>First Name</th>
                        <th>Last Name</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Plan</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($members->num_rows > 0): ?>
                        <?php while ($member = $members->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($member['first_name']); ?></td>
                                <td><?php echo htmlspecialchars($member['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($member['username']); ?></td>
                                <td><?php echo htmlspecialchars($member['email']); ?></td>
                                <td><?php echo htmlspecialchars($member['plan_name']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($member['start_date'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($member['end_date'])); ?></td>
                                <td class="status-<?php echo strtolower($member['status']); ?>">
                                    <?php echo htmlspecialchars($member['status']); ?>
                                </td>
                                <td class="actions">
                                    <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($member)); ?>)" class="edit-btn">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <form action="../actions/remove_member.php" method="POST" class="delete-form" onsubmit="return confirm('Are you sure you want to remove this member? This action cannot be undone.');">
                                        <input type="hidden" name="member_id" value="<?php echo $member['id']; ?>">
                                        <button type="submit" class="delete-btn">
                                            <i class="fas fa-trash-alt"></i> Remove
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="no-records">No members found for this gym</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Edit Member Modal -->
    <div id="editMemberModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeModal()">&times;</span>
            <h3>Edit Member Membership</h3>
            <form id="editMemberForm" action="../actions/update_member.php" method="POST">
                <input type="hidden" id="member_id" name="member_id">
                
                <div class="form-group">
                    <label for="member_first_name">First Name:</label>
                    <input type="text" id="member_first_name" readonly class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="member_last_name">Last Name:</label>
                    <input type="text" id="member_last_name" readonly class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="plan_id">Membership Plan:</label>
                    <select id="plan_id" name="plan_id" required class="form-control">
                        <?php while ($plan = $plans_result->fetch_assoc()): ?>
                            <option value="<?php echo $plan['plan_id']; ?>">
                                <?php echo htmlspecialchars($plan['plan_name']); ?> 
                                (₱<?php echo number_format($plan['price'], 2); ?>) - 
                                <?php echo htmlspecialchars($plan['duration']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="end_date">End Date:</label>
                    <input type="date" id="end_date" name="end_date" required class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="status">Status:</label>
                    <select id="status" name="status" required class="form-control">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                
                <div class="form-actions">
                    <button type="button" onclick="closeModal()" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary">Update Membership</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('editMemberModal');
        
        function openEditModal(member) {
            document.getElementById('member_id').value = member.id;
            document.getElementById('member_first_name').value = member.first_name;
            document.getElementById('member_last_name').value = member.last_name;
            document.getElementById('plan_id').value = member.plan_id;
            
            // Convert to YYYY-MM-DD format for the date input
            const endDate = new Date(member.end_date);
            const formattedEndDate = endDate.toISOString().split('T')[0];
            document.getElementById('end_date').value = formattedEndDate;
            
            // Set status
            const statusLower = member.status.toLowerCase();
            document.getElementById('status').value = statusLower === 'expired' ? 'inactive' : statusLower;
            
            // Show the modal
            modal.style.display = 'block';
        }
        
        function closeModal() {
            modal.style.display = 'none';
        }
        
        // Close modal when clicking outside of it
        window.onclick = function(event) {
            if (event.target == modal) {
                closeModal();
            }
        }
        
        // Close alert messages after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(function(alert) {
                    alert.style.opacity = '0';
                    alert.style.transition = 'opacity 0.5s';
                    setTimeout(function() {
                        alert.style.display = 'none';
                    }, 500);
                });
            }, 5000);
        });
    </script>
</body>
</html>