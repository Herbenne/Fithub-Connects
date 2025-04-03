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

// Improve profile picture path handling
$profile_pic = '../assets/images/default-profile.png'; // Default image path
if (!empty($user['profile_picture'])) {
    // Clean and normalize the path
    $image_path = str_replace('\\', '/', $user['profile_picture']);
    $image_path = trim($image_path, '/');
    $image_path = '../' . $image_path;
    
    // Verify file exists and is readable
    if (file_exists($image_path) && is_readable($image_path)) {
        $profile_pic = $image_path;
    }
}

// Cache control headers
header('Cache-Control: public, max-age=31536000');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Profile</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/profiles.css">
    <style>
        .profile-picture {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid #4CAF50;
            background-color: #f5f5f5; /* Add background color while loading */
        }
        
        .profile-picture-container {
            position: relative;
            display: inline-block;
            width: 150px; /* Fixed width */
            height: 150px; /* Fixed height */
            border-radius: 50%;
            overflow: hidden; /* Prevent image overflow */
        }

        /* Remove loading state opacity to prevent flickering */
        .image-loading {
            background-color: #f5f5f5;
        }

        /* Add fade-in animation */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .profile-picture.loaded {
            animation: fadeIn 0.3s ease-in;
        }
    </style>
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
            <div class="profile-picture-container">
                <img src="<?php echo htmlspecialchars($profile_pic); ?>" 
                     alt="Profile Picture" 
                     class="profile-picture"
                     onerror="handleImageError(this);"
                     onload="handleImageLoad(this);"
                     loading="eager">
            </div>
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
                <input type="file" 
                       id="profile_picture" 
                       name="profile_picture" 
                       accept="image/jpeg,image/png,image/gif"
                       onchange="previewImage(this);">
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
        // Improved image handling functions
        function handleImageError(img) {
            if (img.src !== '../assets/images/default-profile.png') {
                img.src = '../assets/images/default-profile.png';
            }
            img.classList.remove('image-loading');
        }

        function handleImageLoad(img) {
            img.classList.remove('image-loading');
            img.classList.add('loaded');
        }

        function previewImage(input) {
            const profilePic = document.querySelector('.profile-picture');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                // Add loading state
                profilePic.classList.add('image-loading');
                profilePic.classList.remove('loaded');
                
                reader.onload = function(e) {
                    profilePic.src = e.target.result;
                    // Remove loading state after a short delay
                    setTimeout(() => {
                        profilePic.classList.remove('image-loading');
                        profilePic.classList.add('loaded');
                    }, 100);
                }
                
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Preload default image
        const defaultImage = new Image();
        defaultImage.src = '../assets/images/default-profile.png';
    </script>
</body>
</html>