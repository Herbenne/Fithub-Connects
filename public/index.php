<?php
session_start();
?>
<!DOCTYPE html>
<html>
<head>
    <title>FitHub - Find Your Perfect Gym</title>
    <link rel="stylesheet" href="../assets/css/mains.css">
    <link rel="stylesheet" href="../assets/css/home.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-brand">
            <img src="<?php echo dirname($_SERVER['PHP_SELF']) ?>/../assets/logo/FITHUB LOGO.png" 
                 alt="Fithub Logo" 
                 style="max-height: 50px;"
            >
        </div>
        <div class="nav-links">
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="../pages/dashboard.php">Dashboard</a>
                <a href="../pages/profile.php">Profile</a>
                <a href="../actions/logout.php">Logout</a>
            <?php else: ?>
              <div class="login-register-container">
              <a href="../pages/login.php">Login</a>
              <a href="../pages/register.php">Register</a>
              </div>
            <?php endif; ?>
        </div>
    </nav>

    <header class="hero">
        <div class="hero-content">
            <h1>Find Your Perfect Gym</h1>
            <p>Discover and join the best gyms in your area</p>
            <div class="cta-buttons">
                <a href="../pages/explore_gyms.php" class="btn btn-primary">Explore Gyms</a>
                <a href="../pages/register.php" class="btn btn-secondary">Join Now</a>
            </div>
        </div>
    </header>

    <main>
        <section class="features">
            <h2>Why Choose FitHub?</h2>
            <div class="feature-grid">
                <div class="feature-card">
                    <i class="fas fa-dumbbell"></i>
                    <h3>Wide Selection</h3>
                    <p>Browse through numerous gyms in your area</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-star"></i>
                    <h3>Verified Reviews</h3>
                    <p>Read authentic reviews from real gym members</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-credit-card"></i>
                    <h3>Easy Membership</h3>
                    <p>Simple and secure membership registration</p>
                </div>
            </div>
        </section>

        <section class="featured-gyms">
            <h2>Featured Gyms</h2>
            <div class="gym-grid">
                <?php
                include '../config/database.php';
                
                // Removed LIMIT 3 to show all gyms
                $query = "SELECT g.*, 
                          COALESCE(AVG(r.rating), 0) as avg_rating,
                          COUNT(DISTINCT r.review_id) as review_count
                          FROM gyms g 
                          LEFT JOIN gym_reviews r ON g.gym_id = r.gym_id
                          WHERE g.status = 'approved'
                          GROUP BY g.gym_id
                          ORDER BY avg_rating DESC, review_count DESC";
                
                $result = $db_connection->query($query);
                
                if ($result === false) {
                    echo '<div class="alert alert-danger">Error loading featured gyms</div>';
                } else if ($result->num_rows === 0) {
                    echo '<div class="alert alert-info">No featured gyms available at the moment.</div>';
                } else {
                    while ($gym = $result->fetch_assoc()): 
                        $avgRating = number_format($gym['avg_rating'], 1);
                    ?>
                        <div class="gym-card">
                            <div class="gym-image">
                                <img src="<?php echo !empty($gym['gym_thumbnail']) ? 
                                    htmlspecialchars($gym['gym_thumbnail']) : 
                                    '../assets/images/default-gym.jpg'; ?>" 
                                    alt="<?php echo htmlspecialchars($gym['gym_name']); ?>"
                                    loading="lazy"
                                    onerror="this.src='../assets/images/default-gym.jpg'">
                                <?php if ($avgRating >= 4.5): ?>
                                    <div class="featured-badge">
                                        <i class="fas fa-award"></i> Top Rated
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="gym-info">
                                <h3><?php echo htmlspecialchars($gym['gym_name']); ?></h3>
                                <div class="gym-rating">
                                    <div class="stars">
                                        <?php 
                                        $avgRating = floatval($gym['avg_rating']);
                                        for($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star <?php echo $i <= $avgRating ? 'checked' : ''; ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <span class="rating-text">
                                        <?php echo number_format($avgRating, 1); ?> 
                                        (<?php echo $gym['review_count']; ?> reviews)
                                    </span>
                                </div>
                                <p class="gym-location">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <?php echo htmlspecialchars($gym['gym_location']); ?>
                                </p>
                                <a href="../pages/user_view_gym.php?gym_id=<?php echo $gym['gym_id']; ?>" 
                                   class="btn btn-primary">Learn More</a>
                            </div>
                        </div>
                    <?php endwhile;
                } ?>
            </div>
        </section>
    </main>

    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <h3>About FitHub</h3>
                <p>Your trusted platform for finding and joining the perfect gym.</p>
            </div>
            <div class="footer-section">
                <h3>Quick Links</h3>
                <a href="../pages/explore_gyms.php">Find Gyms</a>
                <a href="../pages/register.php">Register</a>
                <a href="../pages/login.php">Login</a>
            </div>
            <div class="footer-section">
                <h3>Contact</h3>
                <p>Email: info@fithub.com</p>
                <p>Phone: (123) 456-7890</p>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2024 FitHub. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>