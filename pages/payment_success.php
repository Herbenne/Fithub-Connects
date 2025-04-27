<?php
session_start();
include '../config/database.php';

if (!isset($_GET['gym_id'], $_GET['user_id'], $_GET['plan_id'])) {
    die("Invalid request. Missing parameters.");
}

$gym_id = $_GET['gym_id'];
$user_id = $_GET['user_id'];
$plan_id = $_GET['plan_id'];

if (!$db_connection) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Get plan duration from membership_plans
$duration_query = "SELECT duration FROM membership_plans WHERE plan_id = ?";
$stmt = $db_connection->prepare($duration_query);
$stmt->bind_param("i", $plan_id);
$stmt->execute();
$result = $stmt->get_result();
$plan = $result->fetch_assoc();
$stmt->close();

// Calculate start and end dates
$start_date = date('Y-m-d'); // Today
$duration_months = 1; // Default to 1 month

// Parse duration string to get number of months
if (preg_match('/(\d+)\s*month/i', $plan['duration'], $matches)) {
    $duration_months = intval($matches[1]);
}
$end_date = date('Y-m-d', strtotime("+$duration_months months", strtotime($start_date)));

// Insert into gym_members table with correct dates
$query = "INSERT INTO gym_members (user_id, gym_id, plan_id, start_date, end_date) VALUES (?, ?, ?, ?, ?)";
$stmt = $db_connection->prepare($query);

if (!$stmt) {
    die("Query preparation failed: " . $db_connection->error);
}

$stmt->bind_param("iiiss", $user_id, $gym_id, $plan_id, $start_date, $end_date);
$stmt->execute();
$stmt->close();

// Update user role to 'member'
$update_query = "UPDATE users SET role = 'member' WHERE id = ?";
$update_stmt = $db_connection->prepare($update_query);

if (!$update_stmt) {
    die("Update query failed: " . $db_connection->error);
}

$update_stmt->bind_param("i", $user_id);
$update_stmt->execute();
$update_stmt->close();

// Update the session to reflect the new role immediately
if ($_SESSION['user_id'] == $user_id) {
    $_SESSION['role'] = 'member';
}

// Get details for display
$gym_query = "SELECT gym_name FROM gyms WHERE gym_id = ?";
$gym_stmt = $db_connection->prepare($gym_query);
$gym_stmt->bind_param("i", $gym_id);
$gym_stmt->execute();
$gym_result = $gym_stmt->get_result();
$gym = $gym_result->fetch_assoc();
$gym_stmt->close();

$plan_query = "SELECT plan_name FROM membership_plans WHERE plan_id = ?";
$plan_stmt = $db_connection->prepare($plan_query);
$plan_stmt->bind_param("i", $plan_id);
$plan_stmt->execute();
$plan_result = $plan_stmt->get_result();
$plan_name = $plan_result->fetch_assoc()['plan_name'];
$plan_stmt->close();

// Get base URL for navigation
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
$base_path = dirname(dirname($_SERVER['PHP_SELF']));
if ($base_path !== '/' && $base_path !== '\\') {
    $base_url .= $base_path;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/unified-theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <title>Payment Success</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            margin: 0;
            padding: 0;
            background-color: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .success-container {
            max-width: 500px;
            margin: auto;
            padding: 30px;
            border-radius: 8px;
            background-color: white;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .success-icon {
            font-size: 60px;
            color: #4CAF50;
            margin-bottom: 20px;
        }

        h2 {
            color: #333;
            margin-bottom: 15px;
        }

        .membership-details {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: left;
        }

        .detail-item {
            margin: 10px 0;
        }

        .detail-label {
            font-weight: bold;
            color: #666;
        }

        .primary-btn {
            display: inline-block;
            margin-top: 20px;
            text-decoration: none;
            color: white;
            background-color: #4CAF50;
            padding: 12px 24px;
            border-radius: 5px;
            font-weight: bold;
            transition: background-color 0.3s;
        }

        .primary-btn:hover {
            background-color: #3d8b40;
        }
    </style>
</head>

<body>
    <div class="success-container">
        <i class="fas fa-check-circle success-icon"></i>
        <h2>Payment Successful!</h2>
        <p>You are now a member of <?php echo htmlspecialchars($gym['gym_name']); ?>.</p>
        
        <div class="membership-details">
            <div class="detail-item">
                <span class="detail-label">Gym:</span> 
                <?php echo htmlspecialchars($gym['gym_name']); ?>
            </div>
            <div class="detail-item">
                <span class="detail-label">Plan:</span> 
                <?php echo htmlspecialchars($plan_name); ?>
            </div>
            <div class="detail-item">
                <span class="detail-label">Start Date:</span> 
                <?php echo date('F j, Y', strtotime($start_date)); ?>
            </div>
            <div class="detail-item">
                <span class="detail-label">End Date:</span> 
                <?php echo date('F j, Y', strtotime($end_date)); ?>
            </div>
        </div>
        
        <a href="<?php echo $base_url; ?>/pages/dashboard.php" class="primary-btn">Go to Dashboard</a>
    </div>

    <script>
        // This script ensures the session is refreshed on the dashboard
        document.addEventListener('DOMContentLoaded', function() {
            // Store membership info for dashboard to display
            localStorage.setItem('newMembership', 'true');
            localStorage.setItem('membershipGym', '<?php echo htmlspecialchars($gym['gym_name']); ?>');
            localStorage.setItem('membershipPlan', '<?php echo htmlspecialchars($plan_name); ?>');
        });
    </script>
</body>

</html>