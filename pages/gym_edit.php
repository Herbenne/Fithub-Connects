<?php
session_start();
include '../config/database.php';

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

// Fetch all users who could be owners (role = 'admin')
$users_query = "SELECT id, username, first_name, last_name FROM users WHERE role = 'admin'";
$users = $db_connection->query($users_query);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Gym - GymHub</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/manage_gyms.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <a href="manage_gyms.php" class="back-btn">← Back to Gym Management</a>
        <h2>Edit Gym: <?php echo htmlspecialchars($gym['gym_name']); ?></h2>

        <form action="../actions/edit_gym.php" method="POST" enctype="multipart/form-data" class="edit-form">
            <input type="hidden" name="gym_id" value="<?php echo $gym['gym_id']; ?>">
            
            <div class="form-group">
                <label>Gym Name</label>
                <input type="text" name="gym_name" value="<?php echo htmlspecialchars($gym['gym_name']); ?>" required>
            </div>

            <div class="form-group">
                <label>Location</label>
                <input type="text" name="gym_location" value="<?php echo htmlspecialchars($gym['gym_location']); ?>" required>
            </div>

            <div class="form-group">
                <label>Phone Number</label>
                <input type="tel" name="gym_phone_number" value="<?php echo htmlspecialchars($gym['gym_phone_number']); ?>" required>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="gym_description" rows="4" required><?php echo htmlspecialchars($gym['gym_description']); ?></textarea>
            </div>

            <div class="form-group">
                <label>Amenities</label>
                <textarea name="gym_amenities" rows="4" required><?php echo htmlspecialchars($gym['gym_amenities']); ?></textarea>
            </div>

            <div class="form-group">
                <label>Gym Owner</label>
                <select name="owner_id" required>
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

            <div class="form-group">
                <label>Gym Thumbnail</label>
                <?php if ($gym['gym_thumbnail']): ?>
                    <div class="current-thumbnail">
                        <img src="../<?php echo htmlspecialchars($gym['gym_thumbnail']); ?>" alt="Current thumbnail">
                    </div>
                <?php endif; ?>
                <input type="file" name="gym_thumbnail" accept="image/*">
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
                                    <img src="<?php echo htmlspecialchars($image); ?>" alt="Equipment <?php echo $index + 1; ?>">
                                    <button type="button" class="remove-image" 
                                            onclick="removeEquipment(<?php echo $index; ?>)">
                                        <i class="fas fa-times"></i>
                                    </button>
                                    <input type="hidden" name="existing_equipment[]" 
                                           value="<?php echo htmlspecialchars($image); ?>">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="equipment-upload">
                        <input type="file" name="equipment_images[]" multiple accept="image/*" 
                               id="equipment_upload">
                        <label for="equipment_upload" class="upload-label">
                            <i class="fas fa-plus"></i> Add Equipment Images
                        </label>
                        <small>You can select multiple images at once</small>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="submit-btn">Update Gym</button>
                <a href="manage_gyms.php" class="cancel-btn">Cancel</a>
            </div>
        </form>
    </div>

    <script>
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

    document.getElementById('equipment_upload').addEventListener('change', function(e) {
        const files = e.target.files;
        const preview = document.createElement('div');
        preview.className = 'equipment-preview';
        
        for (let i = 0; i < files.length; i++) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const div = document.createElement('div');
                div.className = 'equipment-item new';
                div.innerHTML = `
                    <img src="${e.target.result}" alt="New equipment ${i + 1}">
                    <button type="button" class="remove-image" onclick="this.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                preview.appendChild(div);
            }
            reader.readAsDataURL(files[i]);
        }
        
        document.querySelector('.equipment-upload').insertBefore(preview, 
            document.querySelector('.equipment-upload small'));
    });
    </script>
</body>
</html>