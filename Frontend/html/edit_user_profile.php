<?php
session_start();

require 'db_connection.php'; // Include database connection

$user_id = $_SESSION['user_id']; // Assuming the user ID is stored in the session

// Fetch user data from the database
$query = "SELECT username, email, full_name, age, contact_number, profile_picture, password FROM users WHERE id = ?";
$stmt = $db_connection->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($username, $email, $full_name, $age, $contact_number, $profile_picture, $current_password);
$stmt->fetch();
$stmt->close();

// Handle profile picture upload and password change
$upload_dir = 'uploads/';
$profile_picture_error = '';
$uploaded_picture = $profile_picture; // Default to current profile picture
$password_error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Update user profile data
    $new_username = $_POST['username'];
    $new_email = $_POST['email'];
    $new_full_name = $_POST['full_name'];
    $new_age = $_POST['age'];
    $new_contact_number = $_POST['contact_number'];

    // Handle profile picture upload
    if ($_FILES['profile_picture']['error'] == 0) {
        $profile_picture = $_FILES['profile_picture']['name'];
        $target_file = $upload_dir . basename($profile_picture);

        // Check file size (limit to 2MB)
        if ($_FILES['profile_picture']['size'] > 2000000) {
            $profile_picture_error = 'Sorry, your file is too large. The max file size is 2MB.';
        } else {
            // Check if the file is an image (optional for security)
            $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
            if (!in_array($imageFileType, ['jpg', 'jpeg', 'png', 'gif'])) {
                $profile_picture_error = 'Sorry, only JPG, JPEG, PNG & GIF files are allowed.';
            } else {
                // Upload the file
                if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_file)) {
                    // If the new profile picture is uploaded successfully, update the DB
                    $uploaded_picture = $profile_picture;
                } else {
                    $profile_picture_error = 'Sorry, there was an error uploading your file.';
                }
            }
        }
    }

    // Handle password change
    if (!empty($_POST['current_password']) && !empty($_POST['new_password']) && !empty($_POST['confirm_new_password'])) {
        $current_password_input = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_new_password = $_POST['confirm_new_password'];

        // Verify the current password
        if (!password_verify($current_password_input, $current_password)) {
            $password_error = 'The current password is incorrect.';
        } elseif ($new_password !== $confirm_new_password) {
            $password_error = 'The new password and confirmation do not match.';
        } elseif (strlen($new_password) < 6) {
            $password_error = 'The new password must be at least 6 characters long.';
        } else {
            // Hash the new password before saving it
            $hashed_new_password = password_hash($new_password, PASSWORD_BCRYPT);
        }
    }

    // Prepare SQL for updating user data (without password if not changing)
    if (isset($hashed_new_password)) {
        // If password is changed, include the password in the update
        $query = "UPDATE users SET username = ?, email = ?, full_name = ?, age = ?, contact_number = ?, profile_picture = ?, password = ? WHERE id = ?";
        $stmt = $db_connection->prepare($query);
        $stmt->bind_param("sssssssi", $new_username, $new_email, $new_full_name, $new_age, $new_contact_number, $uploaded_picture, $hashed_new_password, $user_id);
    } else {
        // If password is not changed, exclude the password from the update
        $query = "UPDATE users SET username = ?, email = ?, full_name = ?, age = ?, contact_number = ?, profile_picture = ? WHERE id = ?";
        $stmt = $db_connection->prepare($query);
        $stmt->bind_param("ssssssi", $new_username, $new_email, $new_full_name, $new_age, $new_contact_number, $uploaded_picture, $user_id);
    }

    $stmt->execute();
    $stmt->close();

    // Redirect to profile after update
    header("Location: user_profile.php");
    exit();
}

$db_connection->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile</title>
    <link rel="stylesheet" href="../css/editUsersProfile.css"> <!-- Link to your CSS -->
</head>

<body>
    <header>
        <h1>Edit Profile</h1>
    </header>

    <div class="container">
        <div class="edit-profile-card">
            <h2>Update Profile Information</h2>
            <a href="user_profile.php">View Profile</a>

            <!-- Display any error message for the profile picture upload -->
            <?php if ($profile_picture_error): ?>
                <p style="color: red;"><?php echo $profile_picture_error; ?></p>
            <?php endif; ?>

            <!-- Display any error message for the password change -->
            <?php if ($password_error): ?>
                <p style="color: red;"><?php echo $password_error; ?></p>
            <?php endif; ?>

            <form action="edit_user_profile.php" method="POST" enctype="multipart/form-data">
                <label for="username">Username</label>
                <input type="text" name="username" id="username" value="<?= htmlspecialchars($username) ?>" required>

                <label for="email">Email</label>
                <input type="email" name="email" id="email" value="<?= htmlspecialchars($email) ?>" required>

                <label for="full_name">Full Name</label>
                <input type="text" name="full_name" id="full_name" value="<?= htmlspecialchars($full_name) ?>" required>

                <label for="age">Age</label>
                <input type="number" name="age" id="age" value="<?= htmlspecialchars($age) ?>" required>

                <label for="contact_number">Contact Number</label>
                <input type="text" name="contact_number" id="contact_number" value="<?= htmlspecialchars($contact_number) ?>" required>

                <label for="profile_picture">Profile Picture</label>
                <input type="file" name="profile_picture" id="profile_picture">

                <h3>Change Password</h3>

                <label for="current_password">Current Password</label>
                <input type="password" name="current_password" id="current_password">

                <label for="new_password">New Password</label>
                <input type="password" name="new_password" id="new_password">

                <label for="confirm_new_password">Confirm New Password</label>
                <input type="password" name="confirm_new_password" id="confirm_new_password">

                <button type="submit">Update Profile</button>
            </form>
        </div>
    </div>
</body>

</html>