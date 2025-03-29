<?php
session_start();
include '../config/database.php';
include '../includes/auth.php';

// Ensure user is an admin
if ($_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch gym details
$query = "SELECT * FROM gyms WHERE owner_id = ?";
$stmt = $db_connection->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$gym = $result->fetch_assoc();

if (!$gym) {
    echo "You don't have a gym to edit.";
    exit();
}

$gym_id = $gym['gym_id']; // Ensure correct gym_id reference

// Handle gym details update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_gym'])) {
    $gym_name = $_POST['gym_name'];
    $gym_location = $_POST['gym_location'];
    $gym_phone_number = $_POST['gym_phone_number'];
    $gym_description = $_POST['gym_description'];
    $gym_amenities = $_POST['gym_amenities'];

    // Handle gym thumbnail upload
    $gym_thumbnail = $gym['gym_thumbnail'] ?? "";
    if (!empty($_FILES['gym_thumbnail']['name'])) {
        $target_dir = "../assets/images/";
        $gym_thumbnail = $target_dir . basename($_FILES['gym_thumbnail']['name']);
        move_uploaded_file($_FILES['gym_thumbnail']['tmp_name'], $gym_thumbnail);
    }

    // Handle multiple equipment images upload
    $equipment_images = json_decode($gym['equipment_images'] ?? "[]", true);
    if (!empty($_FILES['equipment_images']['name'][0])) {
        foreach ($_FILES['equipment_images']['tmp_name'] as $key => $tmp_name) {
            $target_file = "../assets/images/" . basename($_FILES['equipment_images']['name'][$key]);
            move_uploaded_file($tmp_name, $target_file);
            $equipment_images[] = $target_file;
        }
    }

    // Update gym details
    $update_query = "UPDATE gyms SET gym_name = ?, gym_location = ?, gym_phone_number = ?, gym_description = ?, gym_amenities = ?, gym_thumbnail = ?, equipment_images = ? WHERE owner_id = ?";
    $stmt = $db_connection->prepare($update_query);
    $equipment_images_json = json_encode($equipment_images);
    $stmt->bind_param("sssssssi", $gym_name, $gym_location, $gym_phone_number, $gym_description, $gym_amenities, $gym_thumbnail, $equipment_images_json, $user_id);

    if ($stmt->execute()) {
        echo "Gym details updated successfully!";
    } else {
        echo "Error updating gym details: " . $stmt->error;
    }
}

// Handle adding a new membership plan
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_plan'])) {
    $plan_name = $_POST['plan_name'];
    $price = $_POST['price'];
    $duration = $_POST['duration'];
    $description = $_POST['description'];

    $insertQuery = "INSERT INTO membership_plans (gym_id, plan_name, price, duration, description) VALUES (?, ?, ?, ?, ?)";
    $stmt = $db_connection->prepare($insertQuery);
    $stmt->bind_param("isdss", $gym_id, $plan_name, $price, $duration, $description);

    if ($stmt->execute()) {
        echo "Membership plan added successfully!";
    } else {
        echo "Error adding plan: " . $stmt->error;
    }
}

// Handle deleting a membership plan
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_plan'])) {
    $plan_id = $_POST['plan_id'];

    $deleteQuery = "DELETE FROM membership_plans WHERE plan_id = ?";
    $stmt = $db_connection->prepare($deleteQuery);
    $stmt->bind_param("i", $plan_id);

    if ($stmt->execute()) {
        echo "Plan deleted successfully!";
    } else {
        echo "Error deleting plan: " . $stmt->error;
    }
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Edit Gym</title>
</head>

<body>
    <h2>Edit Gym Details</h2>
    <form method="POST" enctype="multipart/form-data">
        <label>Gym Name:</label>
        <input type="text" name="gym_name" value="<?php echo htmlspecialchars($gym['gym_name']); ?>" required>

        <label>Location:</label>
        <input type="text" name="gym_location" value="<?php echo htmlspecialchars($gym['gym_location']); ?>" required>

        <label>Phone Number:</label>
        <input type="text" name="gym_phone_number" value="<?php echo htmlspecialchars($gym['gym_phone_number']); ?>" required>

        <label>Description:</label>
        <textarea name="gym_description" required><?php echo htmlspecialchars($gym['gym_description']); ?></textarea>

        <label>Amenities:</label>
        <input type="text" name="gym_amenities" value="<?php echo htmlspecialchars($gym['gym_amenities']); ?>" required>

        <label>Gym Thumbnail:</label>
        <input type="file" name="gym_thumbnail" accept="image/*">
        <?php if (!empty($gym['gym_thumbnail'])): ?>
            <img src="<?php echo $gym['gym_thumbnail']; ?>" width="100">
        <?php endif; ?>

        <label>Equipment Images:</label>
        <input type="file" name="equipment_images[]" accept="image/*" multiple>
        <?php
        $equipment_images = json_decode($gym['equipment_images'] ?? "[]", true);
        foreach ($equipment_images as $image): ?>
            <img src="<?php echo $image; ?>" width="100">
        <?php endforeach; ?>

        <button type="submit" name="update_gym">Update Gym</button>
    </form>

    <h3>Manage Membership Plans</h3>
    <form method="POST">
        <label>Plan Name:</label>
        <input type="text" name="plan_name" required>

        <label>Price:</label>
        <input type="number" step="0.01" name="price" required>

        <label>Duration (in months):</label>
        <select name="duration" required>
            <option value="1 month">1 Month</option>
            <option value="3 months">3 Months</option>
            <option value="6 months">6 Months</option>
            <option value="12 months">12 Months</option>
        </select>

        <label>Description:</label>
        <textarea name="description" required></textarea>

        <button type="submit" name="add_plan">Add Plan</button>
    </form>

    <h3>Existing Membership Plans</h3>
    <table border="1">
        <tr>
            <th>Plan Name</th>
            <th>Price</th>
            <th>Duration</th>
            <th>Description</th>
            <th>Actions</th>
        </tr>
        <?php
        $query = "SELECT * FROM membership_plans WHERE gym_id = ?";
        $stmt = $db_connection->prepare($query);
        $stmt->bind_param("i", $gym_id);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($plan = $result->fetch_assoc()):
        ?>
            <tr>
                <td><?php echo htmlspecialchars($plan['plan_name']); ?></td>
                <td><?php echo number_format($plan['price'], 2); ?></td>
                <td><?php echo htmlspecialchars($plan['duration']); ?></td>
                <td><?php echo htmlspecialchars($plan['description']); ?></td>
                <td>
                    <form method="POST">
                        <input type="hidden" name="plan_id" value="<?php echo $plan['plan_id']; ?>">
                        <button type="submit" name="delete_plan">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endwhile; ?>
    </table>
</body>

</html>