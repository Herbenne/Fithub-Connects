<?php
session_start();

// Check if the admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin_login.php");
    exit();
}

// Database connection code
$user = 'root';
$pass = '';
$db = 'gymdb';
$port = 3307;

$db_connection = new mysqli('localhost', $user, $pass, $db, $port);

if ($db_connection->connect_error) {
    die("Connection failed: " . $db_connection->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve form data
    $id = $_POST['id'];
    $plan_name = $_POST['plan_name'];
    $duration = $_POST['duration'];
    $price = $_POST['price'];

    // Update the membership plan
    $stmt = $db_connection->prepare("UPDATE membership_plans SET plan_name = ?, duration = ?, price = ? WHERE id = ?");
    $stmt->bind_param("sidi", $plan_name, $duration, $price, $id);

    if ($stmt->execute()) {
        echo "Membership plan updated successfully!";
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
    $db_connection->close();

    // Redirect back to the admin dashboard
    header("Location: admin_dashboard.php");
    exit();
} else {
    // Fetch the existing membership plan data
    $id = $_GET['id'];
    $stmt = $db_connection->prepare("SELECT plan_name, duration, price FROM membership_plans WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($plan_name, $duration, $price);
    $stmt->fetch();
    $stmt->close();
    $db_connection->close();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Membership Plan</title>
</head>
<body>
    <h2>Edit Membership Plan</h2>
    <form action="edit_membership_plan.php" method="post">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($id); ?>">
        <label for="plan_name">Plan Name:</label>
        <input type="text" id="plan_name" name="plan_name" value="<?php echo htmlspecialchars($plan_name); ?>" required><br><br>
        
        <label for="duration">Duration (days):</label>
        <input type="number" id="duration" name="duration" value="<?php echo htmlspecialchars($duration); ?>" required><br><br>
        
        <label for="price">Price:</label>
        <input type="number" id="price" name="price" step="0.01" value="<?php echo htmlspecialchars($price); ?>" required><br><br>
        
        <input type="submit" value="Update Plan">
    </form>
    <br>
    <a href="admin_dashboard.php">Back to Dashboard</a>
</body>
</html>
