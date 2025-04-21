<?php
session_start();
include '../config/database.php';

// Ensure user is superadmin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: ../login.php");
    exit();
}

// Get user ID from query string
if (!isset($_GET['id'])) {
    die("User ID is required.");
}

$user_id = intval($_GET['id']);

// Fetch user details with gym information
$stmt = $db_connection->prepare("
    SELECT u.*, g.gym_name 
    FROM users u 
    LEFT JOIN gyms g ON u.gym_id = g.gym_id 
    WHERE u.id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    die("User not found.");
}

// Fetch all gyms for dropdown
$gyms_query = "SELECT gym_id, gym_name FROM gyms ORDER BY gym_name";
$gyms_result = $db_connection->query($gyms_query);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $unique_id = $_POST['unique_id'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $age = !empty($_POST['age']) ? intval($_POST['age']) : null;
    $contact_number = $_POST['contact_number'];
    $gym_id = !empty($_POST['gym_id']) ? intval($_POST['gym_id']) : null;
    $new_role = $_POST['role'];

    // Begin transaction
    $db_connection->begin_transaction();

    try {
        // Check if password change was requested
        $password_sql = '';
        $types = "sssssisssi"; // Default types without password
        $params = [
            $unique_id, 
            $username, 
            $email, 
            $first_name, 
            $last_name, 
            $age, 
            $contact_number, 
            $gym_id, 
            $new_role, 
            $user_id
        ];

        if (!empty($_POST['new_password'])) {
            // Validate password confirmation
            if ($_POST['new_password'] !== $_POST['confirm_password']) {
                throw new Exception("Passwords do not match");
            }

            // Hash the new password
            $hashed_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            $password_sql = ", password = ?";
            $types .= "s"; // Add string type for password
            $params[] = $hashed_password;
        }

        // Update user information
        $update_stmt = $db_connection->prepare("
            UPDATE users 
            SET unique_id = ?, 
                username = ?, 
                email = ?, 
                first_name = ?, 
                last_name = ?, 
                age = ?, 
                contact_number = ?, 
                gym_id = ?, 
                role = ? 
                $password_sql
            WHERE id = ?
        ");

        // Bind parameters dynamically
        $update_stmt->bind_param($types, ...$params);

        if (!$update_stmt->execute()) {
            throw new Exception("Failed to update user information");
        }

        // If role changed from member to another role, remove from gym_members
        if ($user['role'] === 'member' && $new_role !== 'member') {
            $delete_membership = $db_connection->prepare("
                DELETE FROM gym_members WHERE user_id = ?
            ");
            $delete_membership->bind_param("i", $user_id);
            $delete_membership->execute();
        }

        $db_connection->commit();
        header("Location: manage_users.php?message=User updated successfully");
        exit();
    } catch (Exception $e) {
        $db_connection->rollback();
        $error = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit User - FitHub</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/mains.css">
    <link rel="stylesheet" href="../assets/css/edit_user.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <div class="form-container">
        <h2>Edit User</h2>
        <?php if (isset($error)): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="unique_id">Unique ID:</label>
                <input type="text" name="unique_id" id="unique_id" value="<?php echo htmlspecialchars($user['unique_id']); ?>" required>
            </div>

            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" name="username" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
            </div>

            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
            </div>

            <div class="form-group">
                <label for="new_password">New Password (leave blank to keep current):</label>
                <input type="password" name="new_password" id="new_password">
                <p class="password-hint">Password must be at least 6 characters long.</p>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm New Password:</label>
                <input type="password" name="confirm_password" id="confirm_password">
            </div>

            <div class="form-group">
                <label for="first_name">First Name:</label>
                <input type="text" name="first_name" id="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
            </div>

            <div class="form-group">
                <label for="last_name">Last Name:</label>
                <input type="text" name="last_name" id="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
            </div>

            <div class="form-group">
                <label for="age">Age:</label>
                <input type="number" name="age" id="age" value="<?php echo htmlspecialchars($user['age'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="contact_number">Contact Number:</label>
                <input type="text" name="contact_number" id="contact_number" value="<?php echo htmlspecialchars($user['contact_number'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="gym_id">Assigned Gym:</label>
                <select name="gym_id" id="gym_id">
                    <option value="">None</option>
                    <?php while ($gym = $gyms_result->fetch_assoc()): ?>
                        <option value="<?php echo $gym['gym_id']; ?>" 
                                <?php echo ($user['gym_id'] == $gym['gym_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($gym['gym_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="role">Role:</label>
                <select name="role" id="role" required>
                    <option value="superadmin" <?php echo $user['role'] === 'superadmin' ? 'selected' : ''; ?>>Superadmin</option>
                    <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>User</option>
                    <option value="member" <?php echo $user['role'] === 'member' ? 'selected' : ''; ?>>Member</option>
                </select>
            </div>

            <div class="btn-container">
                <a href="manage_users.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Update User</button>
            </div>
        </form>
    </div>
    <script>
    document.querySelector('form').addEventListener('submit', function(e) {
        const newPassword = document.getElementById('new_password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        
        if (newPassword || confirmPassword) {
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
            } else if (newPassword.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long!');
            }
        }
    });
    </script>
</body>
</html>