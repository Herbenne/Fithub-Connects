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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Gym - GymHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
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
            <form action="../actions/edit_gym.php" method="POST" enctype="multipart/form-data" class="edit-gym-form" id="gymEditForm">
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
    document.getElementById('thumbnailDropArea').addEventListener('click', function() {
        document.getElementById('gym_thumbnail').click();
    });
    
    // Thumbnail preview update
    document.getElementById('gym_thumbnail').addEventListener('change', function(e) {
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

    function removeEquipment(button, index) {
        if (confirm('Are you sure you want to remove this image?')) {
            // Create a hidden input to track removed images
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'remove_equipment[]';
            input.value = index;
            document.querySelector('form').appendChild(input);
            
            // Hide the image container
            button.closest('.equipment-item').style.display = 'none';
            console.log("Marked image at index " + index + " for removal");
        }
    }

    // Drag and drop functionality for thumbnail
    const thumbnailDropArea = document.getElementById('thumbnailDropArea');
    
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        thumbnailDropArea.addEventListener(eventName, preventDefaults, false);
    });
    
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    // Highlight drop areas when dragging
    ['dragenter', 'dragover'].forEach(eventName => {
        thumbnailDropArea.addEventListener(eventName, function() {
            this.classList.add('highlight');
        }, false);
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
        thumbnailDropArea.addEventListener(eventName, function() {
            this.classList.remove('highlight');
        }, false);
    });
    
    // Handle dropped files for thumbnail
    thumbnailDropArea.addEventListener('drop', function(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        
        if (files.length) {
            document.getElementById('gym_thumbnail').files = files;
            // Trigger change event
            const event = new Event('change', { bubbles: true });
            document.getElementById('gym_thumbnail').dispatchEvent(event);
        }
    }, false);
    
    // Equipment images upload and preview
    const dropArea = document.getElementById('dropArea');
    const equipmentInput = document.getElementById('equipment_images');
    const previewArea = document.getElementById('equipmentPreview');
    
    // Make equipment upload area clickable
    dropArea.addEventListener('click', function() {
        equipmentInput.click();
    });
    
    // Prevent default drag behaviors for equipment uploads
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropArea.addEventListener(eventName, preventDefaults, false);
    });
    
    // Highlight equipment drop area
    ['dragenter', 'dragover'].forEach(eventName => {
        dropArea.addEventListener(eventName, function() {
            this.classList.add('highlight');
        }, false);
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
        dropArea.addEventListener(eventName, function() {
            this.classList.remove('highlight');
        }, false);
    });
    
    // Handle equipment dropped files
    dropArea.addEventListener('drop', function(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        
        if (files.length) {
            equipmentInput.files = files;
            // Trigger change event
            const event = new Event('change', { bubbles: true });
            equipmentInput.dispatchEvent(event);
        }
    }, false);
    
    // Handle equipment file selection and preview
    equipmentInput.addEventListener('change', function(e) {
        const files = e.target.files;
        
        if (files.length) {
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

    // Show loading indicator when submitting form
    document.getElementById('gymEditForm').addEventListener('submit', function() {
        document.getElementById('loadingOverlay').style.display = 'flex';
    });
    </script>
</body>
</html>