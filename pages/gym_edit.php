<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include '../config/database.php';
require_once '../includes/AWSFileManager.php';

$upload_dir = "../assets/images/";
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
    error_log("Created upload directory: " . $upload_dir);
}

if (!is_writable($upload_dir)) {
    error_log("Upload directory is not writable: " . $upload_dir);
}

// Ensure user is superadmin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: login.php");
    exit();
}

// Get gym ID from URL
$gym_id = $_GET['gym_id'] ?? null;

if (!$gym_id) {
    header("Location: manage_gyms.php?error=invalid");
    exit();
}

// Initialize AWS file manager
$awsManager = null;
if (USE_AWS) {
    $awsManager = new AWSFileManager();
}

// Fetch gym details
$query = "SELECT g.*, u.username as owner_name 
          FROM gyms g 
          LEFT JOIN users u ON g.owner_id = u.id 
          WHERE g.gym_id = ?";
$stmt = $db_connection->prepare($query);
$stmt->bind_param("i", $gym_id);
$stmt->execute();
$gym = $stmt->get_result()->fetch_assoc();

if (!$gym) {
    header("Location: manage_gyms.php?error=invalid");
    exit();
}

// Parse equipment images
$equipment_images = [];
if (!empty($gym['equipment_images'])) {
    $equipment_images = json_decode($gym['equipment_images'], true) ?: [];
}

// Fetch all users who could be owners (role = 'admin')
$users_query = "SELECT id, username, first_name, last_name FROM users WHERE role = 'admin'";
$users = $db_connection->query($users_query);

$success_message = $_GET['success'] ?? '';
$error_message = $_GET['error'] ?? '';

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_gym'])) {
    $gym_name = $_POST['gym_name'];
    $gym_location = $_POST['gym_location'];
    $gym_phone_number = $_POST['gym_phone_number'];
    $gym_description = $_POST['gym_description'];
    $gym_amenities = $_POST['gym_amenities'];
    $owner_id = $_POST['owner_id'];

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
                        $delete_result = $awsManager->deleteFile($gym_thumbnail);
                        if ($delete_result) {
                            error_log("Successfully deleted thumbnail from S3: " . $gym_thumbnail);
                        } else {
                            error_log("Failed to delete thumbnail from S3: " . $gym_thumbnail);
                        }
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

        // Parse existing equipment images
        $equipment_images = json_decode($gym['equipment_images'] ?? "[]", true);

        // Handle equipment image removals - do this BEFORE adding new images
        if (isset($_POST['remove_equipment']) && is_array($_POST['remove_equipment'])) {
            foreach ($_POST['remove_equipment'] as $index) {
                if (isset($equipment_images[$index])) {
                    // Log the file that's being deleted
                    error_log("Attempting to delete image: " . $equipment_images[$index]);
                    
                    // Delete the file from AWS if using AWS
                    if (USE_AWS && $awsManager) {
                        $delete_result = $awsManager->deleteFile($equipment_images[$index]);
                        if ($delete_result) {
                            error_log("Successfully deleted image from S3: " . $equipment_images[$index]);
                        } else {
                            error_log("Failed to delete image from S3: " . $equipment_images[$index]);
                        }
                    } else {
                        // For local storage, delete the file if it exists
                        $local_path = $equipment_images[$index];
                        if (file_exists($local_path)) {
                            unlink($local_path);
                        }
                    }
                    
                    // Remove from the array
                    unset($equipment_images[$index]);
                }
            }
            // Re-index array after removals
            $equipment_images = array_values($equipment_images);
        }

        // Handle multiple equipment images upload
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

        // Update gym details
        $update_query = "UPDATE gyms SET 
                        gym_name = ?, 
                        gym_location = ?, 
                        gym_phone_number = ?, 
                        gym_description = ?, 
                        gym_amenities = ?, 
                        gym_thumbnail = ?, 
                        equipment_images = ?,
                        owner_id = ? 
                        WHERE gym_id = ?";
                        
        $stmt = $db_connection->prepare($update_query);
        $equipment_images_json = json_encode($equipment_images);
        
        if (!$stmt) {
            throw new Exception("Error preparing statement: " . $db_connection->error);
        }
        
        $stmt->bind_param("sssssssii", 
            $gym_name, 
            $gym_location, 
            $gym_phone_number, 
            $gym_description, 
            $gym_amenities, 
            $gym_thumbnail, 
            $equipment_images_json,
            $owner_id,
            $gym_id
        );

        if (!$stmt->execute()) {
            throw new Exception("Error updating gym: " . $stmt->error);
        }

        $db_connection->commit();
        $success_message = "Gym details updated successfully!";
        
        // Refresh gym data
        $stmt = $db_connection->prepare($query);
        $stmt->bind_param("i", $gym_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $gym = $result->fetch_assoc();
        
    } catch (Exception $e) {
        $db_connection->rollback();
        $error_message = "Error updating gym details: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Gym - GymHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/mains.css">
    <link rel="stylesheet" href="../assets/css/fithub-ui.css">
    <link rel="stylesheet" href="../assets/css/unified-theme.css">
    <link rel="stylesheet" href="../assets/css/edit_gym.css">
    <style>
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.7);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }
        
        .loading-spinner {
            border: 5px solid #f3f3f3;
            border-top: 5px solid #ffb22c;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 2s linear infinite;
        }
        
        .loading-text {
            color: white;
            margin-top: 10px;
            font-size: 18px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

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
        
        .page-container {
            width: 100%;
        }
        
        .navbar {
            background-color: #f8f9fa;
            padding: 15px 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .nav-content {
            display: flex;
            align-items: center;
            padding: 0 20px;
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
        
        /* Additional styles for the new media section */
        .media-section {
            margin-bottom: 30px;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 8px;
            overflow: hidden; /* Prevent content from overflowing */
        }
        
        .current-thumbnail {
            margin: 15px 0;
            display: block;
        }
        
        .current-thumbnail img {
            max-width: 200px;
            max-height: 150px;
            border-radius: 4px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 5px;
        }
        
        .current-equipment {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin: 15px 0;
        }
        
        .equipment-item {
            position: relative;
            width: 150px;
            height: 120px;
            margin-bottom: 10px;
        }
        
        .equipment-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 4px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .remove-image {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(255, 0, 0, 0.7);
            color: white;
            border: none;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
        }
        
        .remove-image:hover {
            background: rgba(255, 0, 0, 0.9);
        }
        
        .equipment-upload {
            border: 2px dashed #ccc;
            padding: 20px;
            text-align: center;
            border-radius: 8px;
            margin: 15px 0;
            background-color: #f5f5f5;
            cursor: pointer;
            width: 100%;
            box-sizing: border-box;
        }
        
        .equipment-upload:hover, .equipment-upload.highlight {
            background-color: #eaeaea;
            border-color: #ffb22c;
        }
        
        .equipment-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 15px;
        }
        
        .equipment-preview.active {
            padding-top: 15px;
            border-top: 1px dashed #ccc;
        }
        
        .submit-btn, .cancel-btn {
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            display: inline-block;
            margin-right: 10px;
            text-align: center;
            font-weight: 500;
        }
        
        .submit-btn {
            background-color: #ffb22c;
            color: #000;
        }
        
        .submit-btn:hover {
            background-color: #e59f26;
        }
        
        .cancel-btn {
            background-color: #f44336;
            color: white;
            text-decoration: none;
        }
        
        .cancel-btn:hover {
            background-color: #d32f2f;
        }
    </style>
</head>
<body>
    <!-- Loading overlay -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="loading-spinner"></div>
        <div class="loading-text">Uploading images and updating gym...</div>
    </div>

    <div class="container">
        <div class="header-section">
            <a href="manage_gyms.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Gym Management</a>
            <h2>Edit Gym: <?php echo htmlspecialchars($gym['gym_name']); ?></h2>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="success-message">
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="error-message">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <div class="edit-gym-section">
            <form method="POST" enctype="multipart/form-data" class="edit-gym-form" id="gymEditForm">
                <input type="hidden" name="gym_id" value="<?php echo $gym['gym_id']; ?>">
                <?php if ($gym['gym_thumbnail']): ?>
                    <input type="hidden" name="current_thumbnail" value="<?php echo htmlspecialchars($gym['gym_thumbnail']); ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="gym_name">Gym Name</label>
                    <input type="text" id="gym_name" name="gym_name" value="<?php echo htmlspecialchars($gym['gym_name']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="gym_location">Location</label>
                    <input type="text" id="gym_location" name="gym_location" value="<?php echo htmlspecialchars($gym['gym_location']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="gym_phone">Phone Number</label>
                    <input type="tel" id="gym_phone" name="gym_phone_number" value="<?php echo htmlspecialchars($gym['gym_phone_number']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="gym_description">Description</label>
                    <textarea id="gym_description" name="gym_description" rows="4" required><?php echo htmlspecialchars($gym['gym_description']); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="gym_amenities">Amenities</label>
                    <textarea id="gym_amenities" name="gym_amenities" rows="4" required><?php echo htmlspecialchars($gym['gym_amenities']); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="owner_id">Gym Owner</label>
                    <select id="owner_id" name="owner_id" required>
                        <option value="">Select an owner</option>
                        <?php while ($user = $users->fetch_assoc()): ?>
                            <option value="<?php echo $user['id']; ?>" 
                                    <?php echo $user['id'] == $gym['owner_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['username']); ?> 
                                (<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <!-- Media Section -->
                <div class="media-section">
                    <h3>Media & Images</h3>
                    
                    <!-- Gym thumbnail section -->
                    <div class="form-group">
                        <label for="gym_thumbnail">Gym Thumbnail</label>
                        
                        <!-- Upload new thumbnail -->
                        <div class="equipment-upload" id="thumbnailDropArea">
                            <i class="fas fa-cloud-upload-alt fa-2x"></i>
                            <p>Drag and drop or click to upload gym thumbnail</p>
                            <span>(Maximum 2MB)</span>
                            <input type="file" id="gym_thumbnail" name="gym_thumbnail" accept="image/*" class="file-input">
                        </div>
                        
                        <!-- Current thumbnail display -->
                        <?php if (!empty($gym['gym_thumbnail'])): ?>
                        <div class="current-thumbnail">
                            <img src="<?php echo htmlspecialchars($gym['gym_thumbnail']); ?>" 
                                alt="Current thumbnail" 
                                onerror="this.src='../assets/images/default-gym.jpg';">
                            <br>
                            <span>Current thumbnail</span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Equipment images section -->
                    <div class="form-group">
                        <label>Equipment Images</label>
                        
                        <!-- Current equipment images -->
                        <?php if (!empty($equipment_images)): ?>
                        <div class="current-equipment">
                            <?php foreach ($equipment_images as $index => $image): ?>
                            <div class="equipment-item">
                                <img src="<?php echo htmlspecialchars($image); ?>" 
                                     alt="Equipment <?php echo $index + 1; ?>"
                                     onerror="this.src='../assets/images/default-equipment.jpg';">
                                <button type="button" class="remove-image" onclick="removeEquipment(this, <?php echo $index; ?>)">
                                    <i class="fas fa-times"></i>
                                </button>
                                <input type="hidden" name="existing_equipment[]" value="<?php echo htmlspecialchars($image); ?>">
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Upload new equipment images -->
                        <div class="equipment-upload" id="dropArea">
                            <i class="fas fa-cloud-upload-alt fa-2x"></i>
                            <p>Drag and drop or click to upload equipment images</p>
                            <span>(Maximum 5 images, 2MB each)</span>
                            <input type="file" id="equipment_images" name="equipment_images[]" multiple accept="image/*" class="file-input">
                        </div>
                        
                        <!-- Preview area for new uploads -->
                        <div id="equipmentPreview" class="equipment-preview"></div>
                    </div>
                </div>

                <div class="loading-indicator" id="loadingIndicator">
                    <div class="spinner"></div>
                    <p>Uploading images and updating gym details...</p>
                </div>

                <div class="form-actions">
                    <button type="submit" name="update_gym" class="submit-btn"><i class="fas fa-save"></i> Update Gym</button>
                    <a href="manage_gyms.php" class="cancel-btn"><i class="fas fa-times"></i> Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <script>
    // File input event for thumbnail
    document.getElementById('gymEditForm').addEventListener('submit', function() {
        document.getElementById('loadingOverlay').style.display = 'flex';
    });
    
    function removeEquipment(button, index) {
        if (confirm('Are you sure you want to remove this image?')) {
            // Create a hidden input to track removed images
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'remove_equipment[]';
            input.value = index;
            document.getElementById('gymEditForm').appendChild(input);
            
            // Hide the image container
            button.closest('.equipment-item').style.display = 'none';
            console.log("Marked image at index " + index + " for removal");
        }
    }
    
    // Handle thumbnail drag and drop functionality
    const thumbnailDropArea = document.getElementById('thumbnailDropArea');
    const thumbnailInput = document.getElementById('gym_thumbnail');
    
    // Trigger file input when clicking on the drop area
    thumbnailDropArea.addEventListener('click', () => {
        thumbnailInput.click();
    });
    
    // Prevent default drag behaviors for thumbnail
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        thumbnailDropArea.addEventListener(eventName, preventDefaults, false);
    });
    
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    // Highlight thumbnail drop area when dragging over it
    ['dragenter', 'dragover'].forEach(eventName => {
        thumbnailDropArea.addEventListener(eventName, () => {
            thumbnailDropArea.classList.add('highlight');
        }, false);
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
        thumbnailDropArea.addEventListener(eventName, () => {
            thumbnailDropArea.classList.remove('highlight');
        }, false);
    });
    
    // Handle dropped files for thumbnail
    thumbnailDropArea.addEventListener('drop', (e) => {
        const dt = e.dataTransfer;
        const files = dt.files;
        
        if (files.length > 0) {
            // Only take the first file
            thumbnailInput.files = files;
            
            // Check file size (2MB limit)
            if (files[0].size > 2 * 1024 * 1024) {
                alert('File ' + files[0].name + ' exceeds the 2MB size limit.');
                return;
            }
            
            // Show preview of the thumbnail
            const reader = new FileReader();
            reader.onload = function(e) {
                let thumbnailDisplay = document.querySelector('.current-thumbnail');
                
                // If no thumbnail display exists, create one
                if (!thumbnailDisplay) {
                    thumbnailDisplay = document.createElement('div');
                    thumbnailDisplay.className = 'current-thumbnail';
                    
                    const img = document.createElement('img');
                    const span = document.createElement('span');
                    span.textContent = 'New thumbnail';
                    
                    thumbnailDisplay.appendChild(img);
                    thumbnailDisplay.appendChild(document.createElement('br'));
                    thumbnailDisplay.appendChild(span);
                    
                    const uploadDiv = document.getElementById('thumbnailDropArea');
                    uploadDiv.parentNode.insertBefore(thumbnailDisplay, uploadDiv.nextSibling);
                }
                
                const img = thumbnailDisplay.querySelector('img');
                img.src = e.target.result;
                img.alt = 'New thumbnail';
                
                const span = thumbnailDisplay.querySelector('span');
                span.textContent = 'New thumbnail';
            };
            reader.readAsDataURL(files[0]);
        }
    }, false);
    
    // Additional JavaScript for equipment image drag and drop functionality
    const dropArea = document.getElementById('dropArea');
    const fileInput = document.getElementById('equipment_images');
    const previewArea = document.getElementById('equipmentPreview');
    
    // Trigger file input when clicking on the drop area
    dropArea.addEventListener('click', () => {
        fileInput.click();
    });
    
    // Prevent default drag behaviors
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropArea.addEventListener(eventName, preventDefaults, false);
    });
    
    // Highlight drop area when dragging over it
    ['dragenter', 'dragover'].forEach(eventName => {
        dropArea.addEventListener(eventName, highlight, false);
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
        dropArea.addEventListener(eventName, unhighlight, false);
    });
    
    function highlight() {
        dropArea.classList.add('highlight');
    }
    
    function unhighlight() {
        dropArea.classList.remove('highlight');
    }
    
    // Handle dropped files
    dropArea.addEventListener('drop', function(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        fileInput.files = files;
        
        // Clear previous previews
        previewArea.innerHTML = '';
        previewArea.classList.add('active');
        
        // Limit to 5 files
        const maxFiles = 5;
        const filesToProcess = Math.min(files.length, maxFiles);
        
        if (files.length > maxFiles) {
            alert(`Only the first ${maxFiles} images will be uploaded. You selected ${files.length} images.`);
        }
        
        Array.from(files).slice(0, maxFiles).forEach((file, index) => {
            // Check file size (2MB limit)
            if (file.size > 2 * 1024 * 1024) {
                alert(`File ${file.name} exceeds the 2MB size limit.`);
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.createElement('div');
                preview.className = 'equipment-item';
                
                const img = document.createElement('img');
                img.src = e.target.result;
                img.alt = `Equipment preview ${index + 1}`;
                
                const removeButton = document.createElement('button');
                removeButton.type = 'button';
                removeButton.className = 'remove-image';
                removeButton.innerHTML = '<i class="fas fa-times"></i>';
                removeButton.onclick = function() {
                    preview.remove();
                    // Check if all previews removed
                    if (previewArea.children.length === 0) {
                        previewArea.classList.remove('active');
                    }
                };
                
                preview.appendChild(img);
                preview.appendChild(removeButton);
                previewArea.appendChild(preview);
            };
            reader.readAsDataURL(file);
        });
    }, false);
    
    // Handle selected files
    fileInput.addEventListener('change', function(e) {
        const files = e.target.files;
        
        if (files.length > 0) {
            // Clear previous previews
            previewArea.innerHTML = '';
            previewArea.classList.add('active');
            
            // Limit to 5 files
            const maxFiles = 5;
            const filesToProcess = Math.min(files.length, maxFiles);
            
            if (files.length > maxFiles) {
                alert(`Only the first ${maxFiles} images will be uploaded. You selected ${files.length} images.`);
            }
            
            Array.from(files).slice(0, maxFiles).forEach((file, index) => {
                // Check file size (2MB limit)
                if (file.size > 2 * 1024 * 1024) {
                    alert(`File ${file.name} exceeds the 2MB size limit.`);
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.createElement('div');
                    preview.className = 'equipment-item';
                    
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.alt = `Equipment preview ${index + 1}`;
                    
                    const removeButton = document.createElement('button');
                    removeButton.type = 'button';
                    removeButton.className = 'remove-image';
                    removeButton.innerHTML = '<i class="fas fa-times"></i>';
                    removeButton.onclick = function() {
                        preview.remove();
                        // Check if all previews removed
                        if (previewArea.children.length === 0) {
                            previewArea.classList.remove('active');
                        }
                    };
                    
                    preview.appendChild(img);
                    preview.appendChild(removeButton);
                    previewArea.appendChild(preview);
                };
                reader.readAsDataURL(file);
            });
        }
    });
    
    // Handle thumbnail file selection and preview
    thumbnailInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            // Check file size (2MB limit)
            if (file.size > 2 * 1024 * 1024) {
                alert('File ' + file.name + ' exceeds the 2MB size limit.');
                this.value = '';
                return;
            }
            
            // Display preview
            const reader = new FileReader();
            reader.onload = function(e) {
                let thumbnailDisplay = document.querySelector('.current-thumbnail');
                
                // If no thumbnail display exists, create one
                if (!thumbnailDisplay) {
                    thumbnailDisplay = document.createElement('div');
                    thumbnailDisplay.className = 'current-thumbnail';
                    
                    const img = document.createElement('img');
                    const span = document.createElement('span');
                    span.textContent = 'New thumbnail';
                    
                    thumbnailDisplay.appendChild(img);
                    thumbnailDisplay.appendChild(document.createElement('br'));
                    thumbnailDisplay.appendChild(span);
                    
                    const uploadDiv = document.getElementById('thumbnailDropArea');
                    uploadDiv.parentNode.insertBefore(thumbnailDisplay, uploadDiv.nextSibling);
                }
                
                const img = thumbnailDisplay.querySelector('img');
                img.src = e.target.result;
                img.alt = 'New thumbnail';
                
                const span = thumbnailDisplay.querySelector('span');
                span.textContent = 'New thumbnail';
            };
            reader.readAsDataURL(file);
        }
    });
    
    // Image loading handling
    document.addEventListener('DOMContentLoaded', function() {
        const images = document.querySelectorAll('.current-thumbnail img, .equipment-item img');
        
        images.forEach(img => {
            img.addEventListener('load', function() {
                this.style.opacity = '1';
            });
            
            img.addEventListener('error', function() {
                this.src = '../assets/images/default-equipment.jpg';
            });
        });
    });
    </script>
</body>
</html>