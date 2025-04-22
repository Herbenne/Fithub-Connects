<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include '../config/database.php';

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

// Add this before displaying images to check paths
if ($gym['gym_thumbnail']) {
    error_log("Thumbnail path: " . $gym['gym_thumbnail']);
}
if ($gym['equipment_images']) {
    error_log("Equipment images: " . $gym['equipment_images']);
}

// Fetch all users who could be owners (role = 'admin')
$users_query = "SELECT id, username, first_name, last_name FROM users WHERE role = 'admin'";
$users = $db_connection->query($users_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Gym - GymHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/gym_edit.css">
</head>
<body>
    <div class="dashboard-container">
        <div class="header-section">
            <a href="manage_gyms.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Gym Management</a>
            <h2>Edit Gym: <?php echo htmlspecialchars($gym['gym_name']); ?></h2>
        </div>

        <form action="../actions/edit_gym.php" method="POST" enctype="multipart/form-data" class="edit-form">
            <input type="hidden" name="gym_id" value="<?php echo $gym['gym_id']; ?>">
            <?php if ($gym['gym_thumbnail']): ?>
                <input type="hidden" name="current_thumbnail" value="<?php echo htmlspecialchars($gym['gym_thumbnail']); ?>">
            <?php endif; ?>
            
            <div class="form-grid">
                <div class="form-column">
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
                </div>
                
                <div class="form-column">
                    <div class="form-group">
                        <label for="gym_description">Description</label>
                        <textarea id="gym_description" name="gym_description" rows="4" required><?php echo htmlspecialchars($gym['gym_description']); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="gym_amenities">Amenities</label>
                        <textarea id="gym_amenities" name="gym_amenities" rows="4" required><?php echo htmlspecialchars($gym['gym_amenities']); ?></textarea>
                    </div>
                </div>
            </div>

            <div class="media-section">
                <div class="form-group thumbnail-section">
                    <label for="gym_thumbnail">Gym Thumbnail</label>
                    <?php if ($gym['gym_thumbnail']): ?>
                        <div class="current-thumbnail">
                            <img src="<?php echo htmlspecialchars($gym['gym_thumbnail']); ?>" 
                                 alt="Current thumbnail"
                                 onerror="this.onerror=null; this.src='../assets/images/placeholder.png';">
                        </div>
                    <?php endif; ?>
                    <div class="file-upload-wrapper">
                        <input type="file" id="gym_thumbnail" name="gym_thumbnail" accept="image/*" class="file-input">
                        <label for="gym_thumbnail" class="upload-label">
                            <i class="fas fa-upload"></i> Choose Thumbnail
                        </label>
                        <span class="file-name">No file chosen</span>
                    </div>
                    <small>Leave empty to keep current image</small>
                </div>

                <div class="form-group">
                    <label>Equipment Images</label>
                    <div class="equipment-gallery">
                        <?php 
                        $equipment_images = json_decode($gym['equipment_images'] ?? '[]', true);
                        if (!empty($equipment_images)): ?>
                            <div class="current-equipment">
                                <?php foreach ($equipment_images as $index => $image): ?>
                                    <div class="equipment-item">
                                        <img src="<?php echo htmlspecialchars($image); ?>" 
                                             alt="Equipment <?php echo $index + 1; ?>"
                                             onerror="this.onerror=null; this.src='../assets/images/placeholder.png';">
                                        <button type="button" class="remove-image" onclick="removeEquipment(<?php echo $index; ?>)">
                                            <i class="fas fa-times"></i>
                                        </button>
                                        <input type="hidden" name="existing_equipment[]" value="<?php echo htmlspecialchars($image); ?>">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                    
                <div class="equipment-upload">
                    <input type="file" name="equipment_images[]" multiple accept="image/*" 
                           id="equipment_upload" class="file-input">
                    <label for="equipment_upload" class="upload-label">
                        <i class="fas fa-plus"></i> Add Equipment Images
                    </label>
                    <div class="equipment-preview"></div>
                    <small>You can select multiple images at once</small>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="submit-btn"><i class="fas fa-save"></i> Update Gym</button>
                <a href="manage_gyms.php" class="cancel-btn"><i class="fas fa-times"></i> Cancel</a>
            </div>
        </form>
    </div>

    <script>
    // File input name display
    document.getElementById('gym_thumbnail').addEventListener('change', function(e) {
        const fileName = e.target.files[0] ? e.target.files[0].name : 'No file chosen';
        this.nextElementSibling.nextElementSibling.textContent = fileName;
    });

    function removeEquipment(index) {
        if (confirm('Are you sure you want to remove this equipment image?')) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'remove_equipment[]';
            input.value = index;
            document.querySelector('form').appendChild(input);
            document.querySelectorAll('.equipment-item')[index].style.display = 'none';
        }
    }

    // Single event listener for equipment upload preview
    document.getElementById('equipment_upload').addEventListener('change', function(e) {
        const files = e.target.files;
        const preview = document.querySelector('.equipment-preview');
        preview.innerHTML = ''; // Clear previous preview
        
        if (files.length > 0) {
            preview.classList.add('active');
        }
        
        Array.from(files).forEach((file, i) => {
            const reader = new FileReader();
            reader.onload = function(e) {
                const div = document.createElement('div');
                div.className = 'equipment-item new';
                const img = document.createElement('img');
                img.src = e.target.result;
                img.alt = `New equipment ${i + 1}`;
                img.style.opacity = '0';
                
                img.onload = function() {
                    img.style.opacity = '1';
                };
                
                const removeBtn = document.createElement('button');
                removeBtn.className = 'remove-preview';
                removeBtn.innerHTML = '<i class="fas fa-times"></i>';
                removeBtn.onclick = function() {
                    div.remove();
                };
                
                div.appendChild(img);
                div.appendChild(removeBtn);
                preview.appendChild(div);
            };
            reader.readAsDataURL(file);
        });
    });

    // Image loading handling
    document.addEventListener('DOMContentLoaded', function() {
        const images = document.querySelectorAll('.current-thumbnail img, .equipment-item img');
        
        images.forEach(img => {
            img.addEventListener('load', function() {
                this.style.opacity = '1';
            });
            
            img.addEventListener('error', function() {
                this.src = '../assets/images/placeholder.png';
            });
        });
    });
    </script>
</body>
</html>