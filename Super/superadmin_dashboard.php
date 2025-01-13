<?php
session_start();

// Check if the user is logged in and is a superadmin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_role'] !== 'superadmin') {
    header("Location: Admin/admin_login_form.php");
    exit();
}

require 'db_connection.php'; // Include database connection

// Retrieve admin details
$admin_email = $_SESSION['admin_email'];

// Handle password change form submission
$password_change_message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['current_password']) && isset($_POST['new_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];

    // Fetch current superadmin's password from the database
    $stmt = $db_connection->prepare("SELECT password FROM admins WHERE email = ?");
    if ($stmt === false) {
        die("Error preparing statement: " . $db_connection->error);
    }

    $stmt->bind_param("s", $_SESSION['admin_email']);
    if (!$stmt->execute()) {
        die("Error executing statement: " . $stmt->error);
    }

    $stmt->bind_result($hashed_password);
    $stmt->fetch();
    $stmt->close();

    if ($hashed_password && password_verify($current_password, $hashed_password)) {
        // Hash new password and update in the database
        $new_hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
        $update_stmt = $db_connection->prepare("UPDATE admins SET password = ? WHERE email = ?");
        if ($update_stmt === false) {
            die("Error preparing update statement: " . $db_connection->error);
        }
        $update_stmt->bind_param("ss", $new_hashed_password, $_SESSION['admin_email']);
        if (!$update_stmt->execute()) {
            die("Error executing update statement: " . $update_stmt->error);
        }
        $update_stmt->close();

        $password_change_message = "Password updated successfully!";
    } else {
        $password_change_message = "Incorrect current password!";
    }
}

// Fetch Total Counts
$totalUsersQuery = "SELECT COUNT(*) as count FROM users";
$totalUsersResult = mysqli_query($db_connection, $totalUsersQuery) or die("Error in query: $totalUsersQuery - " . mysqli_error($db_connection));
$totalUsers = mysqli_fetch_assoc($totalUsersResult)['count'];

$totalGymsQuery = "SELECT COUNT(*) as count FROM gyms";
$totalGymsResult = mysqli_query($db_connection, $totalGymsQuery) or die("Error in query: $totalGymsQuery - " . mysqli_error($db_connection));
$totalGyms = mysqli_fetch_assoc($totalGymsResult)['count'];

$totalMembershipsQuery = "SELECT COUNT(*) as count FROM membership_plans";
$totalMembershipsResult = mysqli_query($db_connection, $totalMembershipsQuery) or die("Error in query: $totalMembershipsQuery - " . mysqli_error($db_connection));
$totalMemberships = mysqli_fetch_assoc($totalMembershipsResult)['count'];

$totalAttendanceQuery = "SELECT COUNT(*) as count FROM attendance";
$totalAttendanceResult = mysqli_query($db_connection, $totalAttendanceQuery) or die("Error in query: $totalAttendanceQuery - " . mysqli_error($db_connection));
$totalAttendance = mysqli_fetch_assoc($totalAttendanceResult)['count'];

// Fetch Role Distribution
$roleDistributionQuery = "SELECT role, COUNT(*) as count FROM admins GROUP BY role";
$roleDistributionResult = mysqli_query($db_connection, $roleDistributionQuery) or die("Error in query: $roleDistributionQuery - " . mysqli_error($db_connection));
$roles = [];
$roleCounts = [];
while ($row = mysqli_fetch_assoc($roleDistributionResult)) {
    $roles[] = $row['role'];
    $roleCounts[] = $row['count'];
}

// Fetch Gyms per Admin
$gymsPerAdminQuery = "SELECT admins.username, COUNT(gyms.gym_id) as gym_count FROM gyms JOIN admins ON gyms.gym_id = admins.gym_id GROUP BY admins.username";
$gymsPerAdminResult = mysqli_query($db_connection, $gymsPerAdminQuery) or die("Error in query: $gymsPerAdminQuery - " . mysqli_error($db_connection));
$admins = [];
$gymCounts = [];
while ($row = mysqli_fetch_assoc($gymsPerAdminResult)) {
    $admins[] = $row['username'];
    $gymCounts[] = $row['gym_count'];
}

// Fetch Membership Plans per Gym
$membershipPerGymQuery = "SELECT gym_name AS gym_name, COUNT(membership_plans.id) AS plan_count FROM membership_plans JOIN gyms ON membership_plans.gym_id = gyms.gym_id GROUP BY gyms.gym_name";
$membershipPerGymResult = mysqli_query($db_connection, $membershipPerGymQuery) or die("Error in query: $membershipPerGymQuery - " . mysqli_error($db_connection));
$gymNames = [];
$planCounts = [];
while ($row = mysqli_fetch_assoc($membershipPerGymResult)) {
    $gymNames[] = $row['gym_name'];
    $planCounts[] = $row['plan_count'];
}

// Monthly User Registrations
$monthlyRegistrationsQuery = "SELECT DATE_FORMAT(reg_date, '%Y-%m') AS month, COUNT(*) AS count FROM users GROUP BY DATE_FORMAT(reg_date, '%Y-%m')";
$monthlyRegistrationsResult = mysqli_query($db_connection, $monthlyRegistrationsQuery) or die("Error in query: $monthlyRegistrationsQuery - " . mysqli_error($db_connection));
$months = [];
$userCounts = [];
while ($row = mysqli_fetch_assoc($monthlyRegistrationsResult)) {
    $months[] = $row['month'];
    $userCounts[] = $row['count'];
}

$db_connection->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Superadmin Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="./superAdminCss/superAdminDashboard.css">
    <script src="https://kit.fontawesome.com/b098b18a13.js" crossorigin="anonymous"></script>
</head>

<body>
    <div class="dashboard-container">
        <header>
            <h1>SUPER ADMIN</h1>
        </header>

        <nav>
            <a href="manage_admins.php"><i class="fa-solid fa-lock"></i>Manage Admins</a>
            <a href="manage_users.php"><i class="fa-solid fa-user"></i>Manage Users</a>
            <a href="manage_gym_applications.php"><i class="fa-solid fa-paperclip"></i>Applications</a>
            <a href="paymentlist.php"><i class="fa-solid fa-money-bill"></i>View Payment</a>
            <a href="sadmin.php"><i class="fa-solid fa-gear"></i>Site Settings</a>
            <a href="manage_gyms.php"><i class="fa-solid fa-dumbbell"></i>Gyms</a>
            <a href="backup_restore.php"><i class="fa-solid fa-file"></i>Backup & Restore</a>
            <a href="../Admin/admin_login_form.php"><i class="fa-solid fa-right-from-bracket"></i>Logout</a>
        </nav>

        <main>
            <div class="card1 admin-info">
                <h2><span class="spanlabel">Admin Email:</span> <?php echo htmlspecialchars($admin_email); ?></h2>
            </div>

            <div class="card password-change">
                <h2><span class="spanlabel">Change Password</span></h2>
                <form method="POST" action="superadmin_dashboard.php">
                    <div class="form-group">
                        <label for="current_password">Current Password:</label>
                        <input type="password" id="current_password" name="current_password" required>
                    </div>
                    <div class="form-group">
                        <label for="new_password">New Password:</label>
                        <input type="password" id="new_password" name="new_password" required>
                    </div>
                    <div class="password-btn-container">
                        
                    <button class="password-btn" type="submit">Change Password</button>
                    </div>
                </form>
                <?php if (!empty($password_change_message)) : ?>
                    <p class="message"><?= htmlspecialchars($password_change_message) ?></p>
                <?php endif; ?>
            </div>

            <div class="card total-counts">
                <h2><span class="spanlabel">Total Counts</span></h2>
                <div class="counts-grid">
                    <div class="count-item">
                        <span class="count-label">Total Users:</span>
                        <span class="count-value"><?php echo htmlspecialchars($totalUsers); ?></span>
                    </div>
                    <div class="count-item">
                        <span class="count-label">Total Gyms:</span>
                        <span class="count-value"><?php echo htmlspecialchars($totalGyms); ?></span>
                    </div>
                    <div class="count-item">
                        <span class="count-label">Total Membership Plans:</span>
                        <span class="count-value"><?php echo htmlspecialchars($totalMemberships); ?></span>
                    </div>
                    <div class="count-item">
                        <span class="count-label">Total Attendance Records:</span>
                        <span class="count-value"><?php echo htmlspecialchars($totalAttendance); ?></span>
                    </div>
                </div>
            </div>

            <div class="charts-grid">
                <div class="card chart-container1">
                    <h2>Role Distribution</h2>
                    <canvas id="roleChart"></canvas>
                </div>

                <div class="card chart-container">
                    <h2>Gyms per Admin</h2>
                    <canvas id="gymsChart"></canvas>
                </div>

                <div class="card chart-container">
                    <h2>Membership Plans per Gym</h2>
                    <canvas id="plansChart"></canvas>
                </div>

                <div class="card chart-container">
                    <h2>Monthly User Registrations</h2>
                    <canvas id="registrationsChart"></canvas>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Role Distribution Chart
        new Chart(document.getElementById('roleChart'), {
            type: 'pie',
            data: {
                labels: <?php echo json_encode($roles); ?>,
                datasets: [{
                    data: <?php echo json_encode($roleCounts); ?>,
                    backgroundColor: ['#ff6384', '#36a2eb', '#ffcd56', '#4caf50', '#ab47bc']
                }]
            }
        });

        // Gyms per Admin Chart
        new Chart(document.getElementById('gymsChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($admins); ?>,
                datasets: [{
                    label: 'Gyms Managed',
                    data: <?php echo json_encode($gymCounts); ?>,
                    backgroundColor: '#4caf50'
                }]
            }
        });

        // Membership Plans per Gym Chart
        new Chart(document.getElementById('plansChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($gymNames); ?>,
                datasets: [{
                    label: 'Membership Plans',
                    data: <?php echo json_encode($planCounts); ?>,
                    backgroundColor: '#ffa726'
                }]
            }
        });

        // Monthly Registrations Chart
        new Chart(document.getElementById('registrationsChart'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode($months); ?>,
                datasets: [{
                    label: 'User Registrations',
                    data: <?php echo json_encode($userCounts); ?>,
                    borderColor: '#36a2eb',
                    fill: false
                }]
            }
        });
    </script>
</body>

</html>