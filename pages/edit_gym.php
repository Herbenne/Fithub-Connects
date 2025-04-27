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
    <link rel="stylesheet" href="../assets/css/unified-theme.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
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
        
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            color: #000000;
            text-decoration: none;
            border: 2px solid #FFB22C;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.3s ease;
            margin-bottom: 2rem;
        }

        .back-btn:hover {
            background: #FFB22C;
            transform: translateX(-5px);
        }

        .back-btn i {
            margin-right: 0.5rem;
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
        
        /* Additional styles for the new media section */
        .media-section {
            margin-bottom: 30px;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 8px;
        }
        
        .current-thumbnail {
            margin: 15px 0;
            display: inline-block;
        }
        
        .current-thumbnail img {
            max-width: 200px;
            max-height: 150px;
            border-radius: 4px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
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
        }
        
        .equipment-item img {
            width: 100%;
            height: 120px;
            object-fit: cover;
            border-radius: 4px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .remove-image {
            position: absolute;
            top: -10px;
            right: -10px;
            background: #ff5252;
            color: white;
            border: none;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .equipment-upload {
            border: 2px dashed #ccc;
            padding: 20px;
            text-align: center;
            border-radius: 8px;
            margin: 15px 0;
            background-color: #f5f5f5;
            cursor: pointer;
        }
        
        .equipment-upload:hover {
            background-color: #eaeaea;
        }
        
        .equipment-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class="page-container">
        <nav class="navbar">
            <div class="nav-content">
                <a href="dashboard.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <h1>Edit Gym</h1>
            </div>
        </nav>

    <div class="container">
        <div class="edit-gym-section">
            <h2>Edit Gym Details</h2>
            
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

                <!-- Integrated Media Section from the template -->
                <div class="media-section">
                    <h3>Media & Images</h3>
                    
                    <!-- Gym thumbnail section -->
                    <div class="form-group">
                        <label for="gym_thumbnail">Gym Thumbnail</label>
                        <input type="file" id="gym_thumbnail" name="gym_thumbnail" accept="image/*" class="file-input">
                        
                        <?php if (!empty($gym['gym_thumbnail'])): ?>
                        <div class="current-thumbnail">
                            <img src="<?php echo htmlspecialchars($gym['gym_thumbnail']); ?>" 
                                alt="Current thumbnail" 
                                onerror="this.src='../assets/images/default-gym.jpg';">
                            <span>Current thumbnail</span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Equipment images section with horizontal layout -->
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
                                <button type="button" class="remove-image" onclick="removeImage(this, <?php echo $index; ?>)">
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
                    <button type="submit" name="update_gym" class="submit-btn">
                        <i class="fas fa-save"></i> Update Gym
                    </button>
                    <a href="dashboard.php" class="cancel-btn" style="background-color: #ffb22c; color: #000000; border: none; padding: 10px 20px; border-radius: 5px; text-decoration: none; display: inline-block; font-weight: 500;">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>

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
                button.closest('.equipment-item').style.display = 'none';
            }
        }
        
        // Additional JavaScript for drag and drop functionality
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
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
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
        dropArea.addEventListener('drop', handleDrop, false);
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            fileInput.files = files;
            handleFiles(files);
        }
        
        // Handle selected files (both from drop and file input)
        fileInput.addEventListener('change', function() {
            handleFiles(this.files);
        });
        
        function handleFiles(files) {
            // Limit files to 5
            const filesToProcess = Array.from(files).slice(0, 5);
            
            filesToProcess.forEach(file => {
                // Check file size (2MB limit)
                if (file.size > 2 * 1024 * 1024) {
                    alert('File ' + file.name + ' exceeds the 2MB size limit.');
                    return;
                }
                
                // Preview image
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.createElement('div');
                    preview.className = 'equipment-item';
                    preview.innerHTML = `
                        <img src="${e.target.result}" alt="Preview">
                        <button type="button" class="remove-image">
                            <i class="fas fa-times"></i>
                        </button>
                    `;
                    
                    // Add remove functionality for preview
                    preview.querySelector('.remove-image').addEventListener('click', function() {
                        preview.remove();
                    });
                    
                    previewArea.appendChild(preview);
                }
                reader.readAsDataURL(file);
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
        // Handle file input change for equipment images
        const equipmentInput = document.getElementById('equipment_images');
        const previewContainer = document.getElementById('equipmentPreview');
        
        if (equipmentInput && previewContainer) {
            equipmentInput.addEventListener('change', function(e) {
                const files = e.target.files;
                
                if (files.length > 0) {
                    // Add active class to show preview section
                    previewContainer.classList.add('active');
                    
                    // Clear previous previews
                    previewContainer.innerHTML = '';
                    
                    // Limit to max 5 images
                    const maxImages = 5;
                    const filesToProcess = Math.min(files.length, maxImages);
                    
                    if (files.length > maxImages) {
                        alert(`Only the first ${maxImages} images will be uploaded. You selected ${files.length} images.`);
                    }
                    
                    // Create previews for selected files
                    Array.from(files).slice(0, maxImages).forEach((file, index) => {
                        createImagePreview(file, index, previewContainer);
                    });
                }
            });
        }
        
        // Function to create image preview
        function createImagePreview(file, index, container) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                const previewItem = document.createElement('div');
                previewItem.className = 'equipment-item';
                
                const img = document.createElement('img');
                img.src = e.target.result;
                img.alt = `Equipment preview ${index + 1}`;
                
                const removeBtn = document.createElement('button');
                removeBtn.className = 'remove-btn';
                removeBtn.innerHTML = '<i class="fas fa-times"></i>';
                removeBtn.onclick = function() {
                    previewItem.remove();
                    // Check if all items removed
                    if (container.children.length === 0) {
                        container.classList.remove('active');
                    }
                };
                
                previewItem.appendChild(img);
                previewItem.appendChild(removeBtn);
                container.appendChild(previewItem);
            };
            
            reader.readAsDataURL(file);
        }
        
        // Handle removing existing images
        const removeButtons = document.querySelectorAll('.remove-image');
        
        removeButtons.forEach(button => {
            button.addEventListener('click', function() {
                if (confirm('Are you sure you want to remove this image?')) {
                    const item = this.closest('.equipment-item');
                    const index = Array.from(item.parentNode.children).indexOf(item);
                    
                    // Create hidden input to track removed images
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'remove_equipment[]';
                    input.value = index;
                    document.querySelector('form').appendChild(input);
                    
                    // Hide the item
                    item.style.display = 'none';
                }
            });
        });
        
        // Make equipment upload area clickable
        const uploadArea = document.querySelector('.equipment-upload');
        if (uploadArea && equipmentInput) {
            uploadArea.addEventListener('click', function() {
                equipmentInput.click();
            });
            
            // Add drag and drop functionality
            uploadArea.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.classList.add('highlight');
            });
            
            uploadArea.addEventListener('dragleave', function() {
                this.classList.remove('highlight');
            });
            
            uploadArea.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('highlight');
                
                // Trigger file input with dropped files
                if (e.dataTransfer.files.length > 0) {
                    equipmentInput.files = e.dataTransfer.files;
                    
                    // Trigger change event manually
                    const changeEvent = new Event('change', { bubbles: true });
                    equipmentInput.dispatchEvent(changeEvent);
                }
            });
        }
    });
    </script>
</body>
</html>