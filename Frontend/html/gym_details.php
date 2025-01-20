<?php
session_start();

// Ensure the admin is logged in
if (!isset($_SESSION['admin_logged_in']) || !isset($_SESSION['gym_id'])) {
    echo "Access Denied: You need to log in first.";
    exit();
}

// Redirect to gym application form if gym_id is 0
if ($_SESSION['gym_id'] == 0) {
    header("Location: gym_application_form.php");
    exit();
}

// Fetch the gym ID from the URL (using $_GET)
$gym_id = $_GET['gym_id'] ?? null;

if (!$gym_id) {
    echo "Gym ID is missing in the URL! Please ensure the gym ID is passed in the query string.";
    exit();
}

// Connect to the database
$user = 'root';
$pass = ''; // Change this to the actual password if it's not empty
$db = 'gymdb';
$port = 3307;

$db_connection = new mysqli('localhost', $user, $pass, $db, $port);

if ($db_connection->connect_error) {
    die("Connection failed: " . $db_connection->connect_error);
}

// Fetch the gym details only if the gym_id matches the logged-in admin's gym_id
$admin_gym_id = $_SESSION['gym_id'];  // Gym ID of the logged-in admin

// If the admin's gym_id does not match the gym_id from the URL, deny access
if ($admin_gym_id != $gym_id && $_SESSION['admin_role'] != 'superadmin') {
    echo "Access Denied: You are not authorized to view this gym.";
    exit();
}

// Fetch gym details from the database using the correct column name 'gym_id'
$gym_query = "SELECT * FROM gyms WHERE gym_id = ?";
$stmt = $db_connection->prepare($gym_query);
if (!$stmt) {
    die("Error preparing the query: " . $db_connection->error);
}

$stmt->bind_param("i", $gym_id);
$stmt->execute();
$gym_result = $stmt->get_result();

// Debugging: Check the SQL query and results
if ($gym_result->num_rows > 0) {
    $gym = $gym_result->fetch_assoc();
} else {
    echo "Gym not found! No records matched gym_id: " . htmlspecialchars($gym_id);
    exit();
}

// Handle the form submission to update gym details
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update'])) {
    // Fetch and sanitize inputs
    $gym_name = htmlspecialchars(trim($_POST['gym_name']));
    $location = htmlspecialchars(trim($_POST['location']));
    $phone_number = htmlspecialchars(trim($_POST['phone_number']));
    $description = htmlspecialchars(trim($_POST['description']));
    $amenities = htmlspecialchars(trim($_POST['amenities']));

    // Validate inputs
    if (empty($gym_name) || empty($location) || empty($phone_number) || empty($description)) {
        echo "All fields are required.";
    } else {
        // Update gym details in the database
        $update_query = "UPDATE gyms SET gym_name = ?, gym_location = ?, gym_phone_number = ?, gym_description = ?, gym_amenities = ? WHERE gym_id = ?";
        $update_stmt = $db_connection->prepare($update_query);
        $update_stmt->bind_param("sssssi", $gym_name, $location, $phone_number, $description, $amenities, $gym_id);

        if ($update_stmt->execute()) {
            echo "Gym details updated successfully!";
        } else {
            echo "Failed to update gym details.";
        }
    }
}

// Fetch the membership plans for this gym
$plans_query = "SELECT * FROM membership_plans WHERE gym_id = ?";
$plans_stmt = $db_connection->prepare($plans_query);
$plans_stmt->bind_param("i", $gym_id);
$plans_stmt->execute();
$plans_result = $plans_stmt->get_result();

// Fetch gym equipment images for this gym
$images_query = "SELECT * FROM gym_equipment_images WHERE gym_id = ?";
$images_stmt = $db_connection->prepare($images_query);
$images_stmt->bind_param("i", $gym_id);
$images_stmt->execute();
$images_result = $images_stmt->get_result();

$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/gymDetails.css">
    <script
        src="https://kit.fontawesome.com/b098b18a13.js"
        crossorigin="anonymous"
    ></script>
    <title>Gym Details</title>
</head>

<body>

    <h2 class ="gymh2">Gym Details: <span class="fithub"><?php echo htmlspecialchars($gym['gym_name']); ?></span></h2>
    
    <?php if ($_SESSION['admin_role'] == 'superadmin' || $_SESSION['gym_id'] == $gym_id): ?>
        <form method="POST">
        <a class="href" href="index1.php#gyms"> <i class="fa-solid fa-right-from-bracket"></i></a> 
            <label for="gym_name">Gym Name:</label>
            <input type="text" id="gym_name" name="gym_name" value="<?php echo htmlspecialchars($gym['gym_name']); ?>" required>

            <label for="location">Location:</label>
            <input type="text" id="location" name="location" value="<?php echo htmlspecialchars($gym['gym_location']); ?>" required>

            <label for="phone_number">Phone Number:</label>
            <input type="text" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($gym['gym_phone_number']); ?>" required>

            <label for="description">Description:</label>
            <textarea id="description" name="description" rows="4" required><?php echo htmlspecialchars($gym['gym_description']); ?></textarea>

            <label for="amenities">Amenities:</label>
            <textarea id="amenities" name="amenities" rows="4" required><?php echo htmlspecialchars($gym['gym_amenities']); ?></textarea>

            <button type="submit" name="update">Update Gym Details</button>
        </form>
    <?php else: ?>
        <p>You do not have permission to edit these details.</p>
    <?php endif; ?>

    <h3 class="currentGymh3">Current Gym Details:</h3>
    <ul>
        <li><strong>Location:</strong> <?php echo htmlspecialchars($gym['gym_location']); ?></li>
        <li><strong>Phone:</strong> <?php echo htmlspecialchars($gym['gym_phone_number']); ?></li>
        <li><strong>Description:</strong> <?php echo htmlspecialchars($gym['gym_description']); ?></li>
        <li><strong>Amenities:</strong> <?php echo htmlspecialchars($gym['gym_amenities']); ?></li>
    </ul>

    <!-- Equipment Images Section -->
    <h3 class="Equipmenth3">Equipment Images</h3>
  <div class="equipment-images">
  <?php while ($image = $images_result->fetch_assoc()): ?>
        <div class="equipment-image">
                <img src="<?php echo htmlspecialchars($image['image_path']); ?>" alt="Equipment Image" width="150">
            <a href="delete_image.php?image_id=<?php echo $image['id']; ?>&gym_id=<?php echo $gym_id; ?>"><button>Delete</button></a>
        </div>
    <?php endwhile; ?>
  </div>

    <!-- Add Equipment Image Form -->
    <h3 class="uploadh3">Upload Equipment Image</h3>
    <form action="upload_image.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="gym_id" value="1"> <!-- Example gym_id -->
        <label for="image">Choose Image:</label>
        <input type="file" name="image" id="image" required><br><br>
        <button type="submit">Upload Image</button>
    </form>

    <!-- Membership Plans Section -->
    <h3 class="Membershiph3">Membership Plans</h3>
<div class="membership-container">
<table class="membership-table">
    <thead>
        <tr>
            <th>Plan Name</th>
            <th>Duration (Days)</th>
            <th>Price</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($plan = $plans_result->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($plan['plan_name']); ?></td>
                <td><?php echo htmlspecialchars($plan['duration']); ?></td>
                <td>₱<?php echo htmlspecialchars($plan['price']); ?></td>
                <td>
                    <a href="delete_plan.php?plan_id=<?php echo $plan['id']; ?>&gym_id=<?php echo $gym_id; ?>" class="delete-btn">Delete</a>
                </td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>
</div>

    <!-- Add Membership Plan Form -->
    <h3 class="addMembershiph3">Add Membership Plan</h3>
    <form action="add_plan.php" method="POST">
        <input type="hidden" name="gym_id" value="<?php echo $gym_id; ?>">
        <label for="plan_name">Plan Name:</label>
        <input type="text" id="plan_name" name="plan_name" required>
        <label for="duration">Duration (Days):</label>
        <input type="number" id="duration" name="duration" required>
        <label for="price">Price:</label>
        <input type="number" id="price" name="price" required>
        <input type="submit" value="Add Plan">
    </form>

</body>

</html>

<?php
// Close the database connection
$plans_stmt->close();
$images_stmt->close();
$db_connection->close();
?>