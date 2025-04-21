<?php
session_start();
include '../config/database.php';

// Ensure user is superadmin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: login.php");
    exit();
}

// Replace the existing query with:
$query = "SELECT gm.id, gm.user_id, gm.gym_id, gm.plan_id, gm.start_date, 
          gm.end_date, g.gym_name, 
          CONCAT(u.first_name, ' ', u.last_name) as member_name,
          mp.plan_name, mp.price,
          CASE 
              WHEN gm.end_date >= CURDATE() AND gm.status = 'active' THEN 'active'
              ELSE 'expired'
          END as status
          FROM gym_members gm
          JOIN users u ON gm.user_id = u.id
          JOIN gyms g ON gm.gym_id = g.gym_id
          JOIN membership_plans mp ON gm.plan_id = mp.plan_id
          ORDER BY gm.start_date DESC";
$result = $db_connection->query($query);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Memberships</title>
    <link rel="stylesheet" href="../assets/css/manage_common.css">
    <link rel="stylesheet" href="../assets/css/mains.css">
    <link rel="stylesheet" href="../assets/css/partial_manage_membership.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* Copy styles from manage_users.php and add: */
        .status-active { color: #4CAF50; }
        .status-expired { color: #f44336; }
    </style>
    <style>
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        .btn-primary {
            background-color: #4CAF50;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .btn-secondary {
            background-color: #666;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .btn-primary:hover, .btn-secondary:hover {
            opacity: 0.9;
        }
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
                <?php while ($membership = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($membership['member_name']); ?></td>
                        <td><?php echo htmlspecialchars($membership['gym_name']); ?></td>
                        <td><?php echo htmlspecialchars($membership['plan_name']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($membership['start_date'])); ?></td>
                        <td><?php echo date('M d, Y', strtotime($membership['end_date'])); ?></td>
                        <td class="<?php echo $membership['status'] === 'active' ? 'status-active' : 'status-expired'; ?>">
                            <?php echo ucfirst($membership['status']); ?>
                        </td>
                        <td>
                            <button class="action-btn edit-btn" 
                                    onclick="openEditModal(<?php echo htmlspecialchars(json_encode($membership)); ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            
                            <button class="action-btn delete-btn" 
                                    onclick="confirmDelete(<?php echo $membership['id']; ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <!-- Edit Membership Modal -->
        <div id="editModal" class="modal">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h3>Edit Membership</h3>
                <form id="editMembershipForm" method="POST">
                    <input type="hidden" id="membership_id" name="membership_id">
                    
                    <div class="form-group">
                        <label for="plan_id">Membership Plan:</label>
                        <select id="plan_id" name="plan_id" required>
                            <?php
                            $plans_query = "SELECT * FROM membership_plans ORDER BY plan_name";
                            $plans_result = $db_connection->query($plans_query);
                            while ($plan = $plans_result->fetch_assoc()) {
                                echo "<option value='" . $plan['plan_id'] . "'>" . 
                                     htmlspecialchars($plan['plan_name']) . " (₱" . 
                                     number_format($plan['price'], 2) . ")</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="start_date">Start Date:</label>
                        <input type="date" id="start_date" name="start_date" required>
                    </div>

                    <div class="form-group">
                        <label for="end_date">End Date:</label>
                        <input type="date" id="end_date" name="end_date" required>
                    </div>

                    <div class="form-group">
                        <label for="status">Status:</label>
                        <select id="status" name="status" required>
                            <option value="active">Active</option>
                            <option value="expired">Expired</option>
                        </select>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn-secondary" onclick="modal.style.display='none'">Cancel</button>
                        <button type="submit" class="btn-primary">Update Membership</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <style>
    /* Modal Styles */
    .modal {
        display: none;
        position: fixed;
        z-index: 1;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.4);
    }

    .modal-content {
        background-color: #fefefe;
        margin: 15% auto;
        padding: 20px;
        border: 1px solid #888;
        width: 80%;
        max-width: 500px;
        border-radius: 8px;
    }

    .close {
        color: #aaa;
        float: right;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
    }

    .form-group {
        margin-bottom: 15px;
    }

    .form-group label {
        display: block;
        margin-bottom: 5px;
    }

    .form-group input,
    .form-group select {
        width: 100%;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }

    .form-actions {
        display: flex;
        justify-content: space-between;
    }

    .action-btn {
        padding: 5px 10px;
        margin: 0 2px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
    }

    .edit-btn {
        background-color: #4CAF50;
        color: white;
    }

    .delete-btn {
        background-color: #f44336;
        color: white;
    }
    </style>

    <script>
    const modal = document.getElementById('editModal');
    const span = document.getElementsByClassName('close')[0];

    function openEditModal(membership) {
        document.getElementById('membership_id').value = membership.id;
        document.getElementById('plan_id').value = membership.plan_id;
        
        // Format dates correctly
        const startDate = new Date(membership.start_date).toISOString().split('T')[0];
        const endDate = new Date(membership.end_date).toISOString().split('T')[0];
        
        document.getElementById('start_date').value = startDate;
        document.getElementById('end_date').value = endDate;
        
        // Set status directly from the database value
        document.getElementById('status').value = membership.status;
        
        modal.style.display = "block";
    }

    // Form submission handling
    document.getElementById('editMembershipForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        try {
            const formData = new FormData(this);
            
            const response = await fetch('../actions/update_membership.php', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error('Network response was not ok');
            }

            const data = await response.json();

            if (data.success) {
                alert('Membership updated successfully!');
                window.location.reload();
            } else {
                throw new Error(data.message || 'Update failed');
            }
        } catch (error) {
            alert('Error updating membership: ' + error.message);
            console.error('Error:', error);
        }
    });

    // Close modal handlers
    span.onclick = function() {
        modal.style.display = "none";
    }

    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = "none";
        }
    }

    // Add this function after the existing script tag
    function confirmDelete(membershipId) {
        if (confirm('Are you sure you want to delete this membership?')) {
            const formData = new FormData();
            formData.append('id', membershipId);
            
            fetch('../actions/delete_membership.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Membership deleted successfully!');
                    window.location.reload();
                } else {
                    throw new Error(data.message || 'Delete failed');
                }
            })
            .catch(error => {
                alert('Error deleting membership: ' + error.message);
                console.error('Error:', error);
            });
        }
    }
    </script>
</body>
</html>