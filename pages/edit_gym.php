<?php
session_start();
require_once '../config/database.php';
require_once '../includes/AWSFileManager.php';

// Ensure user is an admin
if ($_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch gym details
$query = "SELECT * FROM gyms WHERE owner_id = ? AND status = 'approved'";
$stmt = $db_connection->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$gym = $result->fetch_assoc();

if (!$gym) {
    echo "You don't have a gym to edit.";
    exit();
}

$gym_id = $gym['gym_id'];
$success_message = '';
$error_message = '';

// Initialize AWS file manager
$awsManager = null;
if (USE_AWS) {
    $awsManager = new AWSFileManager();
}

// Handle gym details update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_gym'])) {
    $gym_name = $_POST['gym_name'];
    $gym_location = $_POST['gym_location'];
    $gym_phone_number = $_POST['gym_phone_number'];
    $gym_description = $_POST['gym_description'];
    $gym_amenities = $_POST['gym_amenities'];

    // Start transaction
    $db_connection->begin_transaction();
    
    try {
        // Handle gym thumbnail upload
        $gym_thumbnail = $gym['gym_thumbnail'] ?? "";
        if (!empty($_FILES['gym_thumbnail']['name']) && $_FILES['gym_thumbnail']['error'] === UPLOAD_ERR_OK) {
            $tmp_path = $_FILES['gym_thumbnail']['tmp_name'];
            $filename = $_FILES['gym_thumbnail']['name'];
            
            if (USE_AWS && $awsManager) {
                // Create gym folder structure if it doesn't exist
                $awsManager->createGymFolder($gym_id, $gym_name);
                
                // Upload thumbnail to AWS S3
                $new_thumbnail = $awsManager->uploadGymThumbnail($tmp_path, $gym_id, $filename);
                
                if ($new_thumbnail) {
                    // If there was an old thumbnail, delete it
                    if (!empty($gym_thumbnail)) {
                        $awsManager->deleteFile($gym_thumbnail);
                    }
                    $gym_thumbnail = $new_thumbnail;
                } else {
                    throw new Exception("Failed to upload thumbnail to AWS S3");
                }
            } else {
                // Legacy code for local storage
                $target_dir = "../assets/images/";
                $gym_thumbnail = $target_dir . basename($_FILES['gym_thumbnail']['name']);
                if (!move_uploaded_file($_FILES['gym_thumbnail']['tmp_name'], $gym_thumbnail)) {
                    throw new Exception("Failed to upload thumbnail locally");
                }
            }
        }

        // Handle multiple equipment images upload
        $equipment_images = json_decode($gym['equipment_images'] ?? "[]", true);
        if (!empty($_FILES['equipment_images']['name'][0])) {
            foreach ($_FILES['equipment_images']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['equipment_images']['error'][$key] === UPLOAD_ERR_OK) {
                    $filename = $_FILES['equipment_images']['name'][$key];
                    
                    if (USE_AWS && $awsManager) {
                        // Upload equipment image to AWS S3
                        $new_image = $awsManager->uploadEquipmentImages($tmp_name, $gym_id, $filename);
                        
                        if ($new_image) {
                            $equipment_images[] = $new_image;
                        } else {
                            throw new Exception("Failed to upload equipment image to AWS S3");
                        }
                    } else {
                        // Legacy code for local storage
                        $target_file = "../assets/images/" . basename($_FILES['equipment_images']['name'][$key]);
                        if (move_uploaded_file($tmp_name, $target_file)) {
                            $equipment_images[] = $target_file;
                        } else {
                            throw new Exception("Failed to upload equipment image locally");
                        }
                    }
                }
            }
        }
        
        // Handle equipment image removals
        if (isset($_POST['remove_equipment']) && is_array($_POST['remove_equipment'])) {
            foreach ($_POST['remove_equipment'] as $index) {
                if (isset($equipment_images[$index])) {
                    // Delete the file from AWS if using AWS
                    if (USE_AWS && $awsManager) {
                        $awsManager->deleteFile($equipment_images[$index]);
                    }
                    // Remove from the array
                    unset($equipment_images[$index]);
                }
            }
            // Re-index array
            $equipment_images = array_values($equipment_images);
        }

        // Update gym details
        $update_query = "UPDATE gyms SET 
                        gym_name = ?, 
                        gym_location = ?, 
                        gym_phone_number = ?, 
                        gym_description = ?, 
                        gym_amenities = ?, 
                        gym_thumbnail = ?, 
                        equipment_images = ? 
                        WHERE owner_id = ?";
                        
        $stmt = $db_connection->prepare($update_query);
        $equipment_images_json = json_encode($equipment_images);
        
        if (!$stmt) {
            throw new Exception("Error preparing statement: " . $db_connection->error);
        }
        
        $stmt->bind_param("sssssssi", 
            $gym_name, 
            $gym_location, 
            $gym_phone_number, 
            $gym_description, 
            $gym_amenities, 
            $gym_thumbnail, 
            $equipment_images_json, 
            $user_id
        );

        if (!$stmt->execute()) {
            throw new Exception("Error updating gym: " . $stmt->error);
        }

        $db_connection->commit();
        $success_message = "Gym details updated successfully!";
        
        // Refresh gym data
        $stmt = $db_connection->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $gym = $result->fetch_assoc();
        
    } catch (Exception $e) {
        $db_connection->rollback();
        $error_message = "Error updating gym details: " . $e->getMessage();
    }
}

// Parse equipment images to array
$equipment_images = [];
if (!empty($gym['equipment_images'])) {
    $equipment_images = json_decode($gym['equipment_images'], true) ?: [];
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Gym - FitHub</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/mains.css">
    <link rel="stylesheet" href="../assets/css/edit_gym.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .success-message {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .back-to-dashboard {
            display: inline-block;
            margin-top: 10px;
            text-decoration: none;
            color: #4CAF50;
        }
        
        .back-to-dashboard:hover {
            text-decoration: underline;
        }
        
        .loading-indicator {
            display: none;
            text-align: center;
            padding: 20px;
        }
        
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 2s linear infinite;
            margin: 0 auto;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-brand">
            <img src="../assets/logo/FITHUB LOGO.png" alt="FitHub Logo" style="max-height: 50px;">
        </div>
        <div class="nav-links">
            <a href="dashboard.php">Dashboard</a>
            <a href="profile.php">My Profile</a>
            <a href="../actions/logout.php">Logout</a>
        </div>
    </nav>

    <div class="container">
        <div class="edit-gym-section">
            <h2>Edit Gym Details</h2>
            
            <?php if (!empty($success_message)): ?>
                <div class="success-message">
                    <?php echo $success_message; ?>
                    <a href="dashboard.php" class="back-to-dashboard">Back to Dashboard</a>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
                <div class="error-message">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data" class="edit-gym-form" id="gymForm">
                <div class="form-group">
                    <label>Gym Name:</label>
                    <input type="text" name="gym_name" value="<?php echo htmlspecialchars($gym['gym_name']); ?>" required>
                </div>

                <div class="form-group">
                    <label>Location:</label>
                    <input type="text" name="gym_location" value="<?php echo htmlspecialchars($gym['gym_location']); ?>" required>
                </div>

                <div class="form-group">
                    <label>Phone Number:</label>
                    <input type="text" name="gym_phone_number" value="<?php echo htmlspecialchars($gym['gym_phone_number']); ?>" required>
                </div>

                <div class="form-group">
                    <label>Description:</label>
                    <textarea name="gym_description" required rows="4"><?php echo htmlspecialchars($gym['gym_description']); ?></textarea>
                </div>

                <div class="form-group">
                    <label>Amenities:</label>
                    <textarea name="gym_amenities" required rows="3"><?php echo htmlspecialchars($gym['gym_amenities']); ?></textarea>
                </div>

                <div class="form-group">
                    <label>Gym Thumbnail:</label>
                    <input type="file" name="gym_thumbnail" accept="image/*" class="file-input">
                    <?php if (!empty($gym['gym_thumbnail'])): ?>
                        <div class="current-image">
                            <img src="<?php echo $gym['gym_thumbnail']; ?>" alt="Current thumbnail" 
                                 onerror="this.onerror=null; this.src='../assets/images/default-gym.jpg';">
                            <span>Current thumbnail</span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label>Equipment Images:</label>
                    <input type="file" name="equipment_images[]" accept="image/*" multiple class="file-input">
                    <div class="equipment-images">
                        <?php foreach ($equipment_images as $index => $image): ?>
                            <div class="equipment-image">
                                <img src="<?php echo $image; ?>" alt="Equipment" 
                                     onerror="this.onerror=null; this.src='../assets/images/default-equipment.jpg';">
                                <button type="button" class="remove-btn" onclick="removeImage(this, <?php echo $index; ?>)">
                                    <i class="bi bi-x"></i>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="loading-indicator" id="loadingIndicator">
                    <div class="spinner"></div>
                    <p>Uploading images and updating gym details...</p>
                </div>

                <button type="submit" name="update_gym" class="submit-btn">Update Gym</button>
                <a href="dashboard.php" class="cancel-btn">Cancel</a>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('gymForm').addEventListener('submit', function() {
            document.getElementById('loadingIndicator').style.display = 'block';
        });
        
        function removeImage(button, index) {
            if (confirm('Are you sure you want to remove this image?')) {
                // Create a hidden input to track removed images
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'remove_equipment[]';
                input.value = index;
                document.getElementById('gymForm').appendChild(input);
                
                // Hide the image container
                button.closest('.equipment-image').style.display = 'none';
            }
        }
    </script>
</body>
</html>