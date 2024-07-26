<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user = 'root';
$pass = ''; // Change this to the actual password if it's not empty
$db = 'gymdb';
$port = 3307;

$db_connection = new mysqli('localhost', $user, $pass, $db, $port);

if ($db_connection->connect_error) {
    die("Connection failed: " . $db_connection->connect_error);
}

$user_id = $_SESSION['user_id'];
$stmt = $db_connection->prepare("SELECT username, unique_id, email, full_name, age, contact_number, profile_picture, membership_end_date, membership_status FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($username, $unique_id, $email, $full_name, $age, $contact_number, $profile_picture, $membership_end_date, $membership_status);
$stmt->fetch();
$stmt->close();

$today = new DateTime();
$end_date = new DateTime($membership_end_date);
$interval = $today->diff($end_date);
$remaining_days = $interval->days;
if ($today > $end_date) {
    $remaining_days = 0;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_FILES['profile_picture'])) {
        $profile_picture = $_FILES['profile_picture'];
        $target_dir = __DIR__ . "/uploads";
        $target_file = $target_dir . basename($profile_picture["name"]);
        $uploadOk = 1;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        // Check if file is an image
        $check = getimagesize($profile_picture["tmp_name"]);
        if ($check === false) {
            $error_message = "File is not an image.";
            $uploadOk = 0;
        }

        // Check file size
        if ($profile_picture["size"] > 500000) {
            $error_message = "Sorry, your file is too large.";
            $uploadOk = 0;
        }

        // Allow certain file formats
        if ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif") {
            $error_message = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
            $uploadOk = 0;
        }

        // Check if $uploadOk is set to 0 by an error
        if ($uploadOk == 0) {
            $error_message = "Sorry, your file was not uploaded.";
        } else {
            if (move_uploaded_file($profile_picture["tmp_name"], $target_file)) {
                $stmt = $db_connection->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                $stmt->bind_param("si", $target_file, $user_id);
                if ($stmt->execute()) {
                    $success_message = "Profile picture updated successfully.";
                    // Refresh profile info
                    $stmt = $db_connection->prepare("SELECT profile_picture FROM users WHERE id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $stmt->bind_result($profile_picture);
                    $stmt->fetch();
                    $stmt->close();
                } else {
                    $error_message = "Error updating profile picture: " . $stmt->error;
                }
            } else {
                $error_message = "Sorry, there was an error uploading your file.";
            }
        }
    } elseif (isset($_POST['delete_picture']) && $_POST['delete_picture'] == '1') {
        // Handle profile picture deletion
        $stmt = $db_connection->prepare("UPDATE users SET profile_picture = NULL WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            // Delete the file from the server
            if (!empty($profile_picture) && file_exists($profile_picture)) {
                unlink($profile_picture);
            }
            $success_message = "Profile picture deleted successfully.";
            $profile_picture = null; // Update the profile picture variable
        } else {
            $error_message = "Error deleting profile picture: " . $stmt->error;
        }
        $stmt->close();
    } elseif (isset($_POST['update_profile'])) {
        // Handle profile update
        $username = $_POST['username'];
        $email = $_POST['email'];
        $full_name = $_POST['full_name'];
        $age = $_POST['age'];
        $contact_number = $_POST['contact_number'];

        $stmt = $db_connection->prepare("UPDATE users SET username = ?, email = ?, full_name = ?, age = ?, contact_number = ? WHERE id = ?");
        $stmt->bind_param("sssisi", $username, $email, $full_name, $age, $contact_number, $user_id);
        if ($stmt->execute()) {
            $success_message = "Profile updated successfully.";
        } else {
            $error_message = "Error updating profile: " . $stmt->error;
        }
        $stmt->close();
    }
}

$db_connection->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>User Profile</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 20px;
        }
        .profile-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .profile-picture-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }
        .profile-picture {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #ddd;
        }
        .default-picture {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background-color: #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            color: #bbb;
            border: 2px solid #ddd;
        }
        .profile-details {
            width: 100%;
            text-align: left;
        }
        .profile-details p {
            margin: 8px 0;
            font-size: 16px;
        }
        .upload-form, .edit-form {
            margin-top: 20px;
        }
        .upload-form input[type="file"], .edit-form input[type="text"], .edit-form input[type="number"], .edit-form input[type="email"] {
            margin-right: 10px;
        }
        .upload-form input[type="submit"], .edit-form input[type="submit"], .edit-form input[type="button"], .delete-button, .logout-button {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .upload-form input[type="submit"], .edit-form input[type="submit"] {
            background-color: #007bff;
            color: #fff;
        }
        .upload-form input[type="submit"]:hover, .edit-form input[type="submit"]:hover {
            background-color: #0056b3;
        }
        .edit-form input[type="button"] {
            background-color: #6c757d;
            color: #fff;
        }
        .edit-form input[type="button"]:hover {
            background-color: #5a6268;
        }
        .error-message {
            color: red;
            margin-bottom: 10px;
        }
        .success-message {
            color: green;
            margin-bottom: 10px;
        }
        .delete-button {
            background-color: #ff4d4d;
            color: #fff;
            margin-top: 10px;
        }
        .delete-button:hover {
            background-color: #cc0000;
        }
        .logout-button {
            background-color: #ff4d4d;
            color: #fff;
            margin-top: 20px;
        }
        .logout-button:hover {
            background-color: #cc0000;
        }
        .edit-form-container {
            display: none;
        }
    </style>
    <script>
        function toggleEditForm() {
            var editFormContainer = document.getElementById('edit-form-container');
            if (editFormContainer.style.display === 'none' || editFormContainer.style.display === '') {
                editFormContainer.style.display = 'block';
            } else {
                editFormContainer.style.display = 'none';
            }
        }
    </script>
</head>
<body>
    <div class="profile-container">
        <?php if (isset($error_message)): ?>
            <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
        <?php endif; ?>
        <?php if (isset($success_message)): ?>
            <p class="success-message"><?php echo htmlspecialchars($success_message); ?></p>
        <?php endif; ?>

        <div class="profile-picture-container">
            <?php if (!empty($profile_picture) && is_string($profile_picture) && file_exists($profile_picture)): ?>
                <img src="<?php echo htmlspecialchars(basename($profile_picture)); ?>" alt="Profile Picture" class="profile-picture">
            <?php else: ?>
                <div class="default-picture">N/A</div>
            <?php endif; ?>
            <div class="upload-form">
                <form action="profile.php" method="post" enctype="multipart/form-data">
                    <input type="file" name="profile_picture" accept="image/*" required>
                    <input type="submit" value="Upload Picture">
                </form>
            </div>
            <form action="profile.php" method="post">
                <input type="hidden" name="delete_picture" value="1">
                <input type="submit" value="Delete Picture" class="delete-button">
            </form>
        </div>

        <div class="profile-details">
            <p><strong>Username:</strong> <?php echo htmlspecialchars($username); ?></p>
            <p><strong>Unique ID:</strong> <?php echo htmlspecialchars($unique_id); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($email); ?></p>
            <p><strong>Full Name:</strong> <?php echo htmlspecialchars($full_name); ?></p>
            <p><strong>Age:</strong> <?php echo htmlspecialchars($age); ?></p>
            <p><strong>Contact Number:</strong> <?php echo htmlspecialchars($contact_number); ?></p>
            <p><strong>Membership Status:</strong> <?php echo htmlspecialchars($membership_status); ?></p>
            <p><strong>Remaining Days:</strong> <?php echo $remaining_days; ?> days</p>
        </div>

        <div class="edit-button">
            <input type="button" value="Edit Profile" onclick="toggleEditForm()">
        </div>

        <div id="edit-form-container" class="edit-form-container">
            <form action="profile.php" method="post" class="edit-form">
                <p><strong>Edit Profile:</strong></p>
                <p>
                    <label for="username">Username:</label>
                    <input type="text" name="username" id="username" value="<?php echo htmlspecialchars($username); ?>" required>
                </p>
                <p>
                    <label for="email">Email:</label>
                    <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($email); ?>" required>
                </p>
                <p>
                    <label for="full_name">Full Name:</label>
                    <input type="text" name="full_name" id="full_name" value="<?php echo htmlspecialchars($full_name); ?>" required>
                </p>
                <p>
                    <label for="age">Age:</label>
                    <input type="number" name="age" id="age" value="<?php echo htmlspecialchars($age); ?>" required>
                </p>
                <p>
                    <label for="contact_number">Contact Number:</label>
                    <input type="text" name="contact_number" id="contact_number" value="<?php echo htmlspecialchars($contact_number); ?>" required>
                </p>
                <input type="hidden" name="update_profile" value="1">
                <input type="submit" value="Update Profile">
            </form>
        </div>

        <form action="logout.php" method="post">
            <input type="submit" value="Logout" class="logout-button">
        </form>
    </div>
</body>
</html>
