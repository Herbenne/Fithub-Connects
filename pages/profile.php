<?php
session_start();
include '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user details
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $db_connection->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Profile</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/profile.css">
</head>
<body>
    <div class="profile-container">
        <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">Profile updated successfully!</div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger">Error updating profile. Please try again.</div>
        <?php endif; ?>

        <div class="profile-header">
            <?php
            $profile_pic = $user['profile_picture'] ? '../' . $user['profile_picture'] : '../assets/default-profile.png';
            ?>
            <img src="<?php echo htmlspecialchars($profile_pic); ?>" 
                 alt="Profile Picture" 
                 class="profile-picture"
                 onerror="this.src='../assets/default-profile.png'">
            <h2>My Profile</h2>
        </div>

        <?php if (isset($_SESSION['debug'])): ?>
            <div class="debug-info">
                <p>Profile Picture Path: <?php echo htmlspecialchars($profile_pic); ?></p>
                <p>Database Path: <?php echo htmlspecialchars($user['profile_picture'] ?? 'Not set'); ?></p>
            </div>
        <?php endif; ?>

        <form action="../actions/update_profile.php" method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="profile_picture">Change Profile Picture</label>
                <input type="file" id="profile_picture" name="profile_picture" accept="image/*">
            </div>

            <div class="form-group">
                <label for="first_name">First Name</label>
                <input type="text" id="first_name" name="first_name" 
                       value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
            </div>

            <div class="form-group">
                <label for="last_name">Last Name</label>
                <input type="text" id="last_name" name="last_name" 
                       value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" 
                       value="<?php echo htmlspecialchars($user['email']); ?>" required>
            </div>

            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" 
                       value="<?php echo htmlspecialchars($user['username']); ?>" required>
            </div>

            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="tel" id="phone" name="phone" 
                       value="<?php echo htmlspecialchars($user['contact_number']); ?>">
            </div>

            <div class="form-group">
                <label for="new_password">New Password (leave blank to keep current)</label>
                <input type="password" id="new_password" name="new_password">
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password">
            </div>

            <button type="submit" class="save-btn">Save Changes</button>
        </form>
    </div>
</body>
</html>