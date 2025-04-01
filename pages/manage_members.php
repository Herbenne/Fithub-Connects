<?php
session_start();
require_once __DIR__ . "/../config/database.php"; // Ensure correct path

// Check if the user is logged in and is an admin (gym owner)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Access denied.");
}

$gym_owner_id = $_SESSION['user_id'];

// Fetch the gym owned by the logged-in admin
$query = "SELECT gym_id FROM gyms WHERE owner_id = ?";
$stmt = $db_connection->prepare($query);
if (!$stmt) {
    die("Query preparation failed (gyms): " . $db_connection->error);
}

$stmt->bind_param("i", $gym_owner_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("No gym found for this owner.");
}

$gym = $result->fetch_assoc();
$gym_id = $gym['gym_id'];

$stmt->close();

// Fetch gym members
$query = "
    SELECT gm.id, u.username, u.email, mp.plan_name, gm.start_date, gm.end_date
    FROM gym_members gm
    JOIN users u ON gm.user_id = u.id
    JOIN membership_plans mp ON gm.plan_id = mp.plan_id
    WHERE gm.gym_id = ?";

$stmt = $db_connection->prepare($query);
if (!$stmt) {
    die("Query preparation failed (gym members): " . $db_connection->error);
}

$stmt->bind_param("i", $gym_id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Members</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/manage_common.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .back-btn {
            background-color: #4a5568;
            color: white;
            padding: 8px 16px;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            margin: 20px 0;
        }

        .back-btn:hover {
            background-color: #2d3748;
        }
    </style>
</head>

<body>
    <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>

    <h2>Manage Gym Members</h2>

    <table border="1">
        <tr>
            <th>ID</th>
            <th>Username</th>
            <th>Email</th>
            <th>Plan Name</th>
            <th>Start Date</th>
            <th>End Date</th>
            <th>Actions</th>
        </tr>
        <?php while ($row = $result->fetch_assoc()) { ?>
            <tr>
                <td><?php echo htmlspecialchars($row['id']); ?></td>
                <td><?php echo htmlspecialchars($row['username']); ?></td>
                <td><?php echo htmlspecialchars($row['email']); ?></td>
                <td><?php echo htmlspecialchars($row['plan_name']); ?></td>
                <td><?php echo htmlspecialchars($row['start_date']); ?></td>
                <td><?php echo htmlspecialchars($row['end_date']); ?></td>
                <td>
                    <a href="edit_member.php?id=<?php echo $row['id']; ?>" class="edit-btn">Edit</a>
                    <form action="../actions/remove_member.php" method="POST" style="display: inline;" 
                          onsubmit="return confirm('Are you sure you want to remove this member?');">
                        <input type="hidden" name="member_id" value="<?php echo $row['id']; ?>">
                        <button type="submit" class="delete-btn">Remove</button>
                    </form>
                </td>
            </tr>
        <?php } ?>
    </table>

</body>

</html>