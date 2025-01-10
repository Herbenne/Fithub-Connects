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

// Query for membership plans based on gym_id
$offers_query = "SELECT * FROM membership_plans WHERE gym_id = ?";
$offers_stmt = $db_connection->prepare($offers_query);

if (!$offers_stmt) {
    die("Error preparing statement: " . $db_connection->error);
}

$offers_stmt->bind_param("i", $gym_id);
$offers_stmt->execute();
$offers_result = $offers_stmt->get_result();

// Query for gym images
$image_query = "SELECT image_path FROM gym_equipment_images WHERE gym_id = ?";
$image_stmt = $db_connection->prepare($image_query);
$image_stmt->bind_param("i", $gym_id);
$image_stmt->execute();
$image_result = $image_stmt->get_result();

// Close the database connection after all queries are done
$stmt->close();
$offers_stmt->close();
$image_stmt->close();
$db_connection->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
		<script
      src="https://kit.fontawesome.com/b098b18a13.js"
      crossorigin="anonymous"
    ></script>
    <link rel="stylesheet" href="styles/gym<?php echo $gym_id; ?>.css"> <!-- Unique stylesheet for the gym -->
    <title><?php echo htmlspecialchars($gym['gym_name'] ?? 'Gym Details'); ?> - Details</title>
    <link rel="stylesheet" href="../css/gymphp.css" />
</head>

<body>
    <h1><?php echo htmlspecialchars($gym['gym_name'] ?? 'Unknown Gym'); ?></h1>

    <!-- Display Gym Images -->
    <div class="gym-maincontent">
    <a href="index1.php#gyms"><i class="fa-solid fa-circle-info"></i></a> 
			<div class="gym-images">
        <?php if ($image_result->num_rows > 0): ?>
            <?php while ($image = $image_result->fetch_assoc()): ?>
                <div class="gym-image">
                    <img src="<?php echo htmlspecialchars($image['image_path']); ?>" alt="Gym Image" style="max-width: 100%; height: auto;">
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>No gym images available.</p>
        <?php endif; ?>
    </div>

    <div class="gym-details">  
        <p class="description"><?php echo htmlspecialchars($gym['gym_description'] ?? 'No description available'); ?></p>
        <div class="gym-info">
					<p><i class="fa-solid fa-location-dot"></i><?php echo htmlspecialchars($gym['gym_location'] ?? 'No location provided'); ?></p>
					<p><i class="fa-solid fa-phone"></i><?php echo htmlspecialchars($gym['gym_phone_number'] ?? 'No contact information'); ?></p>
					<p><i class="fa-solid fa-screwdriver-wrench"></i><?php echo htmlspecialchars($gym['gym_amenities'] ?? 'No amenities available'); ?></p>
				</div>
				</div>
						
		</div>

    <!-- Membership Offers Section -->
    <section id="offers" class="offers-container">
        <h1 class="offers-title">Membership Offers</h1>
        <div class="offers">
            <!-- Loop through offers dynamically -->
            <?php
            if ($offers_result->num_rows > 0) {
                while ($offer = $offers_result->fetch_assoc()) {
                    echo '<div class="offer" onclick="redirectToPayment(\'' . $offer['plan_name'] . '\', \'' . $offer['duration'] . '\', ' . $offer['price'] . ')">
                        <h3>' . htmlspecialchars($offer['plan_name']) . '</h3>
                        <p>' . htmlspecialchars($offer['duration']) . '</p>
                        <div class="price">₱' . htmlspecialchars($offer['price']) . '</div>
                        <p class="per-day">per ' . htmlspecialchars($offer['duration']) . '</p>
                    </div>';
                }
            } else {
                echo '<p>No membership plans available for this gym.</p>';
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