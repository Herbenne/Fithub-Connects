<?php
session_start();
include '../config/database.php';

// Ensure user is superadmin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: login.php");
    exit();
}

// Get users with pagination
$page = $_GET['page'] ?? 1;
$per_page = 10;
$start = ($page - 1) * $per_page;

$query = "SELECT id, username, email, first_name, last_name, role, reg_date 
          FROM users ORDER BY reg_date DESC LIMIT ?, ?";
$stmt = $db_connection->prepare($query);
$stmt->bind_param("ii", $start, $per_page);
$stmt->execute();
$users = $stmt->get_result();

// Get total users for pagination
$total = $db_connection->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
$total_pages = ceil($total / $per_page);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Users</title>
    <link rel="stylesheet" href="../assets/css/mains.css">
    <link rel="stylesheet" href="../assets/css/manage_user.css">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="../assets/js/manage_users.js" defer></script>
</head>
<body>
    <div class="dashboard-container">
        <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
        <h2>Manage Users</h2>
        
        <div class="table-wrapper">
            <table class="user-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Registration Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($user = $users->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['id']); ?></td>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($user['role']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($user['reg_date'])); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <button class="action-btn edit-btn" onclick="editUser(<?php echo $user['id']; ?>)">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="action-btn delete-btn" onclick="deleteUser(<?php echo $user['id']; ?>)">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="pagination">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?php echo $i; ?>" 
                   class="page-link <?php echo $page == $i ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </div>
    </div>

    <script>
        function editUser(userId) {
            const newRole = prompt("Enter the new role for the user (superadmin, admin, user, member):");
            if (newRole) {
                fetch('user_actions.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'edit', user_id: userId, role: newRole })
                })
                .then(response => response.json())
                .then(data => {
                    alert(data.message);
                    if (data.status === 'success') {
                        location.reload();
                    }
                })
                .catch(error => console.error('Error:', error));
            }
        }

        function deleteUser(userId) {
            if (confirm("Are you sure you want to delete this user?")) {
                fetch('user_actions.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'delete', user_id: userId })
                })
                .then(response => response.json())
                .then(data => {
                    alert(data.message);
                    if (data.status === 'success') {
                        location.reload();
                    }
                })
                .catch(error => console.error('Error:', error));
            }
        }
    </script>
</body>
</html>