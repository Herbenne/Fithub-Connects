<?php
// Include database connection
require 'db_connection.php';

// Check if gym_id is provided
if (!isset($_GET['gym_id']) || empty($_GET['gym_id'])) {
    die("Invalid gym ID.");
}

$gym_id = intval($_GET['gym_id']); // Sanitize input

// Fetch gym details from the database
$sql = "SELECT gym_id, gym_name, gym_location, gym_phone_number, gym_description, gym_amenities FROM gyms WHERE gym_id = ?";
$stmt = $db_connection->prepare($sql);

if (!$stmt) {
    die("Error preparing statement: " . $db_connection->error);
}

$stmt->bind_param("i", $gym_id);
$stmt->execute();
$result = $stmt->get_result();

// Check if a gym was found
if ($result->num_rows === 0) {
    die("No gym found with the given ID.");
}

$gym = $result->fetch_assoc();

// Query for membership plans
$offers_query = "SELECT * FROM membership_plans";
$offers_result = $db_connection->query($offers_query);

// Query for gym image (profile picture)
$image_query = "SELECT image_path FROM gym_equipment_images WHERE gym_id = ? LIMIT 1"; // Assuming one profile image per gym
$image_stmt = $db_connection->prepare($image_query);
$image_stmt->bind_param("i", $gym_id);
$image_stmt->execute();
$image_result = $image_stmt->get_result();
$image = $image_result->fetch_assoc();

// Close the database connection after all queries are done
$stmt->close();
$image_stmt->close();
$db_connection->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles/gym<?php echo $gym_id; ?>.css"> <!-- Unique stylesheet for the gym -->
    <title><?php echo htmlspecialchars($gym['gym_name'] ?? 'Gym Details'); ?> - Details</title>
    <link rel="stylesheet" href="../css/offers.css" />
</head>

<body>
    <h1><?php echo htmlspecialchars($gym['gym_name'] ?? 'Unknown Gym'); ?></h1>

    <!-- Display Gym Image (Profile Picture) -->
    <?php if (isset($image['image_path']) && !empty($image['image_path'])): ?>
        <div class="gym-image">
            <img src="<?php echo htmlspecialchars($image['image_path']); ?>" alt="Gym Image" style="max-width: 100%; height: auto;">
        </div>
    <?php else: ?>
        <p>No gym image available.</p>
    <?php endif; ?>

    <p><strong>Location:</strong> <?php echo htmlspecialchars($gym['gym_location'] ?? 'No location provided'); ?></p>
    <p><strong>Description:</strong> <?php echo htmlspecialchars($gym['gym_description'] ?? 'No description available'); ?></p>
    <p><strong>Contact:</strong> <?php echo htmlspecialchars($gym['gym_phone_number'] ?? 'No contact information'); ?></p>
    <p><strong>Amenities:</strong> <?php echo htmlspecialchars($gym['gym_amenities'] ?? 'No amenities available'); ?></p>
    <a href="index1.php">Back to Gym List</a>

    <!-- Membership Offers Section -->
    <section id="offers" class="offers-container">
        <h1 class="offers-title">Membership Offers</h1>
        <div class="offers">
            <!-- Loop through offers dynamically -->
            <?php
            while ($offer = $offers_result->fetch_assoc()) {
                echo '<div class="offer" onclick="redirectToPayment(\'' . $offer['plan_name'] . '\', \'' . $offer['duration'] . '\', ' . $offer['price'] . ')">
                  <h3>' . htmlspecialchars($offer['plan_name']) . '</h3>
                  <p>' . htmlspecialchars($offer['duration']) . '</p>
                  <div class="price">₱' . htmlspecialchars($offer['price']) . '</div>
                  <p class="per-day">per ' . htmlspecialchars($offer['duration']) . '</p>
                </div>';
            }
            ?>
        </div>
    </section>

    <script>
        function redirectToPayment(plan, duration, price) {
            const url = `payment_test.php?plan=${encodeURIComponent(plan)}&duration=${encodeURIComponent(duration)}&price=${price}`;
            window.location.href = url;
        }
    </script>
</body>

</html>