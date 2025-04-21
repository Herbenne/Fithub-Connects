<?php
session_start();
include '../config/database.php';

// Check if user is admin or superadmin
$is_admin = isset($_SESSION['role']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'superadmin');

// Simplified query to ensure we're getting results
$query = "SELECT g.*, 
          COALESCE(AVG(r.rating), 0) as avg_rating,
          COUNT(DISTINCT r.review_id) as review_count
          FROM gyms g 
          LEFT JOIN gym_reviews r ON g.gym_id = r.gym_id
          WHERE g.status = 'approved'
          GROUP BY g.gym_id";

$result = $db_connection->query($query);

// Debug output
if (!$result) {
    error_log("Query failed: " . $db_connection->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Explore Gyms - GymHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/explore_gym.css">
</head>
<body>
    <div class="page-container">
        <nav class="navbar">
            <div class="nav-content">
            <a href="dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <h1>Explore Gyms</h1>
            </div>
        </nav>

        <?php if ($is_admin): ?>
            <div class="admin-notice">
                <i class="fas fa-info-circle"></i>
                <span>Note: As a gym administrator, you can view gym details but cannot apply for memberships.</span>
            </div>
        <?php endif; ?>

        <!-- Search and Filter Section -->
        <div class="search-filter-section">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="gymSearch" placeholder="Search gyms...">
            </div>
        </div>

        <div class="content-wrapper">
            <div class="gym-grid">
                <?php 
                if ($result && $result->num_rows > 0):
                    while ($gym = $result->fetch_assoc()): 
                        // Debug output
                        // error_log(print_r($gym, true));
                ?>
                    <div class="gym-card">
                        <div class="gym-image">
                            <img src="<?php echo !empty($gym['gym_thumbnail']) ? 
                                htmlspecialchars($gym['gym_thumbnail']) : 
                                '../assets/images/default-gym.jpg'; ?>" 
                                alt="<?php echo htmlspecialchars($gym['gym_name']); ?>"
                                onerror="this.src='../assets/images/default-gym.jpg'">
                        </div>
                        <div class="gym-info">
                            <h3><?php echo htmlspecialchars($gym['gym_name']); ?></h3>
                            <div class="gym-rating">
                                <div class="stars">
                                    <?php 
                                    $rating = round($gym['avg_rating'], 1);
                                    for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star <?php echo $i <= $rating ? 'checked' : ''; ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <span class="rating-text">
                                    <?php echo number_format($rating, 1); ?> 
                                    (<?php echo $gym['review_count']; ?> reviews)
                                </span>
                            </div>
                            <p class="gym-location">
                                <i class="fas fa-map-marker-alt"></i>
                                <?php echo htmlspecialchars($gym['gym_location']); ?>
                            </p>
                            <a href="<?php 
                                if (isset($_SESSION['role'])) {
                                    echo ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'superadmin') ? 'view_gym.php' : 'user_view_gym.php';
                                } else {
                                    echo 'user_view_gym.php';
                                }
                            ?>?gym_id=<?php echo $gym['gym_id']; ?>" class="btn">
                                Learn More <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                <?php 
                    endwhile; 
                else: ?>
                    <div class="no-gyms">
                        <i class="fas fa-dumbbell"></i>
                        <p>No gyms available at the moment.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="../assets/js/explore_gyms.js"></script>
</body>
</html>