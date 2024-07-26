<!DOCTYPE html>
<html>
<head>
    <title>Attendance Checker</title>
</head>
<body>
    <h2>Attendance Checker</h2>
    <form method="post" action="">
        <label for="unique_id">Enter your Unique ID:</label>
        <input type="text" id="unique_id" name="unique_id" required>
        <button type="submit" name="check_in">Check IN</button>
        <button type="submit" name="check_out">Check OUT</button>
    </form>

    <?php
    // Database connection
    require_once 'db_connection.php'; // Include your DB connection file

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $unique_id = $_POST['unique_id'];
        $action = isset($_POST['check_in']) ? 'check_in' : 'check_out';

        // Check if user exists and membership is active
        $stmt = $db_connection->prepare("SELECT id FROM users WHERE unique_id = ? AND membership_status = 'active'");
        if (!$stmt) {
            die("Prepare failed: " . $db_connection->error);
        }
        $stmt->bind_param("s", $unique_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $user_id = $user['id'];
            $now = new DateTime();
            $now_str = $now->format('Y-m-d H:i:s');

            if ($action == 'check_in') {
                // Insert new check-in record
                $stmt = $db_connection->prepare("INSERT INTO attendance (user_id, check_in) VALUES (?, ?)");
                if (!$stmt) {
                    die("Prepare failed: " . $db_connection->error);
                }
                $stmt->bind_param("is", $user_id, $now_str);
                if ($stmt->execute()) {
                    echo "<p>Check-in successful.</p>";
                } else {
                    echo "<p>Error checking in. Please try again.</p>";
                }
            } else if ($action == 'check_out') {
                // Check if there's a check-in record to update
                $stmt = $db_connection->prepare("SELECT id, check_in FROM attendance WHERE user_id = ? AND check_out IS NULL ORDER BY check_in DESC LIMIT 1");
                if (!$stmt) {
                    die("Prepare failed: " . $db_connection->error);
                }
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $last_check_in_result = $stmt->get_result();
                $last_check_in = $last_check_in_result->fetch_assoc();

                if ($last_check_in) {
                    $last_check_in_time = new DateTime($last_check_in['check_in']);
                    $interval = $last_check_in_time->diff($now);

                    if ($interval->i < 10) {
                        echo "<p>You must wait at least 10 minutes before checking out.</p>";
                    } else {
                        // Update check-out record
                        $stmt = $db_connection->prepare("UPDATE attendance SET check_out = ? WHERE id = ?");
                        if (!$stmt) {
                            die("Prepare failed: " . $db_connection->error);
                        }
                        $stmt->bind_param("si", $now_str, $last_check_in['id']);
                        if ($stmt->execute()) {
                            echo "<p>Check-out successful.</p>";
                        } else {
                            echo "<p>Error checking out. Please try again.</p>";
                        }
                    }
                } else {
                    echo "<p>No check-in record found. Please check-in first.</p>";
                }
            }
        } else {
            echo "<p>Membership is not active or user does not exist.</p>";
        }
        $stmt->close();
    }
    $db_connection->close();
    ?>
</body>
</html>
