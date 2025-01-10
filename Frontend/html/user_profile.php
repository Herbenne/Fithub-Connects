<?php
session_start();

require 'db_connection.php'; // Include database connection

$user_id = $_SESSION['user_id']; // Assuming the user ID is stored in the session

// Fetch user data from the database
$query = "SELECT username, email, full_name, age, contact_number, profile_picture FROM users WHERE id = ?";
$stmt = $db_connection->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($username, $email, $full_name, $age, $contact_number, $profile_picture);
$stmt->fetch();
$stmt->close();

$db_connection->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile</title>
    <link rel="stylesheet" href="styles.css"> <!-- Link to your CSS -->
</head>

<body>
    <header>
        <h1>User Profile</h1>
    </header>

    <nav>
        <a href="index2.php">Home</a>
        <a href="edit_user_profile.php">Edit Profile</a>
        <a href="logout.php">Logout</a>
    </nav>

    <div class="container">
        <div class="profile-card">
            <h2>Profile Information</h2>

            <?php if ($profile_picture): ?>
                <img src="uploads/<?= htmlspecialchars($profile_picture) ?>" alt="Profile Picture" class="profile-picture">
            <?php else: ?>
                <p>No profile picture uploaded.</p>
            <?php endif; ?>

            <p><strong>Username:</strong> <?= htmlspecialchars($username) ?></p>
            <p><strong>Email:</strong> <?= htmlspecialchars($email) ?></p>
            <p><strong>Full Name:</strong> <?= htmlspecialchars($full_name) ?></p>
            <p><strong>Age:</strong> <?= htmlspecialchars($age) ?></p>
            <p><strong>Contact Number:</strong> <?= htmlspecialchars($contact_number) ?></p>

        </div>
    </div>
</body>

</html>