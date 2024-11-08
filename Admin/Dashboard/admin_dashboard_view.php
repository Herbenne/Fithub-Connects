<?php
// Include the database connection file
include 'db_connection.php';

// Fetch users data from the database
$user_query = "SELECT * FROM users";
$result = $db_connection->query($user_query);

// Verify if the query was successful
if (!$result) {
    die("Query failed: " . $db_connection->error);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table, th, td {
            border: 1px solid black;
            padding: 8px;
        }
        th {
            background-color: #f2f2f2;
        }
        td {
            text-align: left;
        }
        .actions {
            white-space: nowrap;
        }
        .actions a {
            margin-right: 5px;
        }
        .card {
            border: 1px solid #ddd;
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
    <h2>Admin Dashboard</h2>
    <p>Welcome, Admin!</p>

    <!-- User Management Section -->
    <h3>User Management</h3>
    <table>
        <thead>
            <tr>
                <th>Unique ID</th>
                <th>Username</th>
                <th>Email</th>
                <th>Full Name</th>
                <th>Age</th>
                <th>Contact Number</th>
                <th>Membership Status</th>
                <th>Remaining Days</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    // Determine membership status
                    $status = $row['membership_status'];
                    if ($status === 'active') {
                        $end_date = new DateTime($row['membership_end_date']);
                        if ($end_date < new DateTime()) {
                            $status = 'inactive';
                            $row['membership_status'] = 'inactive';
                            $stmt = $db_connection->prepare("UPDATE users SET membership_status = ? WHERE id = ?");
                            $stmt->bind_param("si", $status, $row['id']);
                            $stmt->execute();
                            $stmt->close();
                        }
                    }

                    // Calculate remaining days
                    $remaining_days = 0;
                    if ($status === 'active') {
                        $end_date = new DateTime($row['membership_end_date']);
                        $today = new DateTime();
                        $interval = $today->diff($end_date);
                        $remaining_days = $interval->days;
                        if ($today > $end_date) {
                            $remaining_days = 0;
                        }
                    }
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['unique_id'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($row['username']); ?></td>
                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                        <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['age']); ?></td>
                        <td><?php echo htmlspecialchars($row['contact_number']); ?></td>
                        <td><?php echo htmlspecialchars(ucfirst($status)); ?></td>
                        <td><?php echo htmlspecialchars($remaining_days); ?></td>
                        <td class="actions">
                            <a href="edit_user.php?id=<?php echo $row['id']; ?>">Edit</a>
                            <a href="delete_user.php?id=<?php echo $row['id']; ?>">Delete</a>
                        </td>
                    </tr>
                <?php }
            } else {
                echo '<tr><td colspan="9">No users found.</td></tr>';
            }
            ?>
        </tbody>
    </table>

    <!-- Attendance List Section -->
    <h3>Attendance List</h3>
    <form method="get" action="admin_dashboard.php">
        <label for="attendance_date">Select Date:</label>
        <input type="date" id="attendance_date" name="attendance_date">
        <input type="submit" value="Filter">
    </form>
    <table>
        <thead>
            <tr>
                <th>Unique ID</th>
                <th>Username</th>
                <th>Check In</th>
                <th>Check Out</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if (isset($_GET['attendance_date']) && !empty($_GET['attendance_date'])) {
                $selected_date = $_GET['attendance_date'];
                $filtered_query = "SELECT attendance.id, users.unique_id, users.username, attendance.check_in, attendance.check_out 
                                   FROM attendance 
                                   JOIN users ON attendance.user_id = users.id
                                   WHERE DATE(attendance.check_in) = ?
                                   ORDER BY attendance.check_in DESC";
                $stmt = $db_connection->prepare($filtered_query);
                $stmt->bind_param('s', $selected_date);
                $stmt->execute();
                $filtered_result = $stmt->get_result();

                if ($filtered_result->num_rows > 0) {
                    while ($attendance = $filtered_result->fetch_assoc()) {
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($attendance['unique_id']) . '</td>';
                        echo '<td>' . htmlspecialchars($attendance['username']) . '</td>';
                        echo '<td>' . htmlspecialchars($attendance['check_in']) . '</td>';
                        echo '<td>' . htmlspecialchars($attendance['check_out']) . '</td>';
                        echo '</tr>';
                    }
                } else {
                    echo '<tr><td colspan="4">No attendance records found for the selected date.</td></tr>';
                }
                $stmt->close();
            } else {
                $attendance_query = "SELECT attendance.id, users.unique_id, users.username, attendance.check_in, attendance.check_out 
                                     FROM attendance 
                                     JOIN users ON attendance.user_id = users.id
                                     ORDER BY attendance.check_in DESC";
                $attendance_result = $db_connection->query($attendance_query);

                if ($attendance_result) {
                    while ($attendance = $attendance_result->fetch_assoc()) {
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($attendance['unique_id']) . '</td>';
                        echo '<td>' . htmlspecialchars($attendance['username']) . '</td>';
                        echo '<td>' . htmlspecialchars($attendance['check_in']) . '</td>';
                        echo '<td>' . htmlspecialchars($attendance['check_out']) . '</td>';
                        echo '</tr>';
                    }
                } else {
                    echo '<tr><td colspan="4">No attendance records found.</td></tr>';
                }
            }
            ?>
        </tbody>
    </table>
    <!-- Add User Form -->
    <h3>Add User with Membership</h3>
    <form action="admin_dashboard.php" method="post">
        <input type="hidden" name="add_user" value="1">
        
        <label for="username">Username:</label>
        <input type="text" id="username" name="username" required><br><br>
        
        <label for="membership_plan_id">Membership Plan:</label>
        <select id="membership_plan_id" name="membership_plan_id" required>
            <?php
            // Display membership plans
            $plans_query = "SELECT id, plan_name FROM membership_plans";
            $plans_result = $db_connection->query($plans_query);
            if ($plans_result) {
                while ($plan = $plans_result->fetch_assoc()) {
                    echo '<option value="' . htmlspecialchars($plan['id']) . '">' . htmlspecialchars($plan['plan_name']) . '</option>';
                }
            }
            ?>
        </select><br><br>
        
        <input type="submit" value="Add User">
    </form>
    <!-- Membership Management Section -->
    <h3>Membership Management</h3>
    <form action="add_membership_plan.php" method="post">
        <label for="plan_name">Plan Name:</label>
        <input type="text" id="plan_name" name="plan_name" required><br><br>
        
        <label for="duration">Duration (days):</label>
        <input type="number" id="duration" name="duration" required><br><br>
        
        <label for="price">Price:</label>
        <input type="number" id="price" name="price" step="0.01" required><br><br>
        
        <input type="submit" value="Add Plan">
    </form>
    
<!-- Membership Management Section -->
    <h3>Existing Membership Plans</h3>
    <table>
        <thead>
            <tr>
                <th>Plan Name</th>
                <th>Duration (days)</th>
                <th>Price</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $plans_query = "SELECT id, plan_name, duration, price FROM membership_plans";
            $plans_result = $db_connection->query($plans_query);
            if ($plans_result) {
                while ($plan = $plans_result->fetch_assoc()) {
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($plan['plan_name']) . '</td>';
                    echo '<td>' . htmlspecialchars($plan['duration']) . '</td>';
                    echo '<td>' . htmlspecialchars($plan['price']) . '</td>';
                    echo '<td class="actions">';
                    echo '<a href="edit_membership_plan.php?id=' . $plan['id'] . '">Edit</a>';
                    echo '<a href="delete_membership_plan.php?id=' . $plan['id'] . '">Delete</a>';
                    echo '</td>';
                    echo '</tr>';
                }
            } else {
                echo '<tr><td colspan="4">No membership plans found.</td></tr>';
            }
            ?>
        </tbody>
        </table>
    
    <!-- Admin Management Section -->
<h3>Admin Management</h3>
<form action="admin_dashboard.php" method="post">
    <h4>Add New Admin</h4>
    <label for="admin_username">Username:</label>
    <input type="text" id="admin_username" name="admin_username" required><br><br>

    <label for="admin_email">Email:</label>
    <input type="email" id="admin_email" name="admin_email" required><br><br>

    <label for="admin_password">Password:</label>
    <input type="password" id="admin_password" name="admin_password" required><br><br>

    <label for="admin_password_confirm">Confirm Password:</label>
    <input type="password" id="admin_password_confirm" name="admin_password_confirm" required><br><br>

    <input type="submit" name="add_admin" value="Add Admin">
</form>

<h4>Existing Admins</h4>
<table>
    <thead>
        <tr>
            <th>Username</th>
            <th>Email</th>
            <th>Created At</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php
        if (isset($admins_result)) {
            while ($admin = $admins_result->fetch_assoc()) {
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($admin['username']); ?></td>
                    <td><?php echo htmlspecialchars($admin['email']); ?></td>
                    <td><?php echo htmlspecialchars($admin['created_at']); ?></td>
                    <td>
                        <a href="edit_admin.php?id=<?php echo $admin['id']; ?>">Edit</a>
                        <a href="delete_admin.php?id=<?php echo $admin['id']; ?>" onclick="return confirm('Are you sure you want to delete this admin?');">Delete</a>
                    </td>
                </tr>
            <?php }
        } else {
            echo '<tr><td colspan="4">No admins found.</td></tr>';
        }
        ?>
    </tbody>
</table>

    <br>
    <form action="../admin_logout.php" method="post">
        <input type="submit" value="Logout">
    </form>
</body>
</html>
