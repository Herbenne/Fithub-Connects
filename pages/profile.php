<?php
session_start();
include '../config/database.php';

// Add error reporting for debugging
ini_set('display_errors', 0); // Don't show errors to users
error_reporting(E_ALL); // Log all errors

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

// Set default image path
$default_image = '../assets/images/default-profile.jpg';
$profile_pic = $default_image; // Default image path

// Profile picture handling - using the file handler approach
if (!empty($user['profile_picture'])) {
    // Use the file handler to generate the appropriate URL
    $profile_pic = "view_file.php?path=" . urlencode($user['profile_picture']) . "&direct=1";
    error_log("Profile picture URL: " . $profile_pic);
}

// Cache control headers
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Profile</title>
    <link rel="stylesheet" href="../assets/css/mains.css">
    <link rel="stylesheet" href="../assets/css/profile.css">
</head>
<body>
    <div class="profile-container">
        <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">Profile updated successfully!</div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger">
                <?php 
                $error = $_GET['error'];
                switch($error) {
                    case 'invalid_file_type':
                        echo "Error: Invalid file type. Please upload JPG, PNG, or GIF.";
                        break;
                    case 'file_too_large':
                        echo "Error: File too large. Maximum size is 5MB.";
                        break;
                    case 'upload_failed':
                        echo "Error: Failed to upload profile picture. Please try again.";
                        break;
                    case 'upload_dir_not_writable':
                        echo "Error: Upload directory is not writable. Please contact administrator.";
                        break;
                    case 'move_upload_failed':
                        echo "Error: Failed to move uploaded file. Please try again.";
                        break;
                    case 'aws_upload_error':
                    case 'local_upload_error':
                        echo "Error: Failed to upload image. Please try again.";
                        break;
                    case 'empty_fields':
                        echo "Error: Please fill in all required fields.";
                        break;
                    case 'duplicate':
                        echo "Error: Username or email already exists.";
                        break;
                    case 'password_mismatch':
                        echo "Error: Passwords do not match.";
                        break;
                    case 'db_prepare_error':
                    case 'db_execute_error':
                        echo "Error: Database error. Please try again later.";
                        break;
                    default:
                        echo "Error updating profile. Please try again.";
                }
                ?>
            </div>
        <?php endif; ?>

        <div class="profile-header">
            <div class="profile-picture-container">
                <img src="fetch_image.php?user_id=<?php echo $user_id; ?>" 
                alt="Profile Picture" 
                class="profile-picture"
                onerror="this.src='../assets/images/default-profile.jpg'">
            </div>
            <h2><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h2>
            <p class="username">@<?php echo htmlspecialchars($user['username']); ?></p>
        </div>

        <form action="../actions/update_profile.php" method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="profile_picture">Change Profile Picture</label>
                <input type="file" 
                       id="profile_picture" 
                       name="profile_picture" 
                       accept="image/jpeg,image/png,image/gif">
                <small class="form-text text-muted">
                    Supported formats: JPG, PNG, GIF. Max size: 5MB
                </small>
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

    <script>
        // Image preview functionality
        document.getElementById('profile_picture').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.querySelector('.profile-picture').src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>