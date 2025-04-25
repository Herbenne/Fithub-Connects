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
    <title>Payment Success</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            margin-top: 50px;
        }

        .container {
            max-width: 400px;
            margin: auto;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-shadow: 2px 2px 10px rgba(0, 0, 0, 0.1);
        }

        a {
            display: inline-block;
            margin-top: 20px;
            text-decoration: none;
            color: white;
            background-color: #28a745;
            padding: 10px 15px;
            border-radius: 5px;
        }

        a:hover {
            background-color: #218838;
        }
    </style>
</head>

<body>

    <div class="container">
        <h2>Payment Successful!</h2>
        <p>You are now a gym member.</p>
        <a href="<?php echo $base_url; ?>/pages/dashboard.php">Go to Dashboard</a>
    </div>

</body>

</html>