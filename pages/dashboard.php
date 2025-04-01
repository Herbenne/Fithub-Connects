<?php
session_start();
include '../config/database.php';
include '../includes/auth.php'; // Protect page

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role = $_SESSION['role'];

// Fetch gym data for admin
if ($role === 'admin') {
    $gym_query = "SELECT g.*, 
                  COALESCE(AVG(gr.rating), 0) as avg_rating,
                  COUNT(DISTINCT gm.user_id) as member_count,
                  COUNT(DISTINCT gr.review_id) as review_count
                  FROM gyms g 
                  LEFT JOIN gym_reviews gr ON g.gym_id = gr.gym_id
                  LEFT JOIN gym_members gm ON g.gym_id = gm.gym_id
                  WHERE g.owner_id = ? AND g.status = 'approved'
                  GROUP BY g.gym_id";
    
    $stmt = $db_connection->prepare($gym_query);
    if ($stmt === false) {
        error_log("Error preparing gym query: " . $db_connection->error);
        $gym = null;
    } else {
        $stmt->bind_param("i", $user_id);
        if (!$stmt->execute()) {
            error_log("Error executing gym query: " . $stmt->error);
            $gym = null;
        } else {
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $gym = $result->fetch_assoc();
            } else {
                // Check if user has a pending gym application
                $pending_query = "SELECT * FROM gyms WHERE owner_id = ? AND status = 'pending'";
                $stmt = $db_connection->prepare($pending_query);
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $pending_result = $stmt->get_result();
                if ($pending_result->num_rows > 0) {
                    $gym = null; // Show pending message
                } else {
                    // This shouldn't happen - admin should have an approved gym
                    error_log("Admin user {$user_id} has no associated gym");
                    $gym = null;
                }
            }
        }
    }
}

// Fetch user's active memberships
if ($role === 'member') {
    $membership_query = "SELECT m.*, g.gym_name, g.gym_thumbnail, p.plan_name, p.duration 
                        FROM gym_members m 
                        JOIN gyms g ON m.gym_id = g.gym_id 
                        JOIN membership_plans p ON m.plan_id = p.plan_id 
                        WHERE m.user_id = ?";
    
    $stmt = $db_connection->prepare($membership_query);
    
    if ($stmt === false) {
        error_log("Error preparing membership query: " . $db_connection->error);
        $memberships = null;
    } else {
        if (!$stmt->bind_param("i", $user_id)) {
            error_log("Error binding parameters: " . $stmt->error);
            $memberships = null;
        } else {
            if (!$stmt->execute()) {
                error_log("Error executing query: " . $stmt->error);
                $memberships = null;
            } else {
                $memberships = $stmt->get_result();
            }
        }
    }
}

// Fetch featured gyms with error handling
$query = "SELECT g.*, 
          COALESCE(AVG(r.rating), 0) as avg_rating,
          COUNT(DISTINCT r.review_id) as review_count
          FROM gyms g 
          LEFT JOIN gym_reviews r ON g.gym_id = r.gym_id
          WHERE g.status = 'approved'
          GROUP BY g.gym_id
          ORDER BY avg_rating DESC";
$result = $db_connection->query($query);

if ($result === false) {
    error_log("Error fetching gyms: " . $db_connection->error);
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Dashboard - FitHub</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/mains.css">
    <link rel="stylesheet" href="../assets/css/dashboards.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/dashboard.js"></script>
</head>

<body>
    <nav class="navbar">
        <div class="nav-brand"> <img src="<?php echo dirname($_SERVER['PHP_SELF']) ?>/../assets/logo/FITHUB LOGO.png" 
                 alt="Fithub Logo" 
                 style="max-height: 50px;"
            ></div>
        <div class="nav-links">
            <a href="dashboard.php" class="active">Dashboard</a>
            <a href="profile.php">My Profile</a>
            <a href="../actions/logout.php">Logout</a>
        </div>
    </nav>

    <div class="dashboard-container">
        <div class="welcome-section">
            <h1>Welcome, <?php echo htmlspecialchars($username); ?>!</h1>
            <p class="role-badge"><?php echo ucfirst($role); ?></p>
        </div>

        <?php if ($role === 'admin'): ?>
            <?php if ($gym): ?>
                <!-- Admin's gym management section -->
                <div class="admin-dashboard">
                    <h2>Gym Management Dashboard</h2>
                    <div class="stat-cards">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-info">
                                <h3>Total Members</h3>
                                <p class="stat-number"><?php echo $gym['member_count']; ?></p>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-star"></i>
                            </div>
                            <div class="stat-info">
                                <h3>Average Rating</h3>
                                <p class="stat-number"><?php echo number_format($gym['avg_rating'], 1); ?></p>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-comment"></i>
                            </div>
                            <div class="stat-info">
                                <h3>Total Reviews</h3>
                                <p class="stat-number"><?php echo $gym['review_count']; ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="admin-actions">
                        <h3>Quick Actions</h3>
                        <div class="action-buttons">
                            <a href="edit_gym.php?gym_id=<?php echo $gym['gym_id']; ?>" class="admin-btn">
                                <i class="fas fa-edit"></i> Edit Gym Details
                            </a>
                            <a href="manage_plans.php?gym_id=<?php echo $gym['gym_id']; ?>" class="admin-btn">
                                <i class="fas fa-clipboard-list"></i> Manage Plans
                            </a>
                            <a href="manage_members.php?gym_id=<?php echo $gym['gym_id']; ?>" class="admin-btn">
                                <i class="fas fa-users-cog"></i> Manage Members
                            </a>
                            <a href="gym_analytics.php?gym_id=<?php echo $gym['gym_id']; ?>" class="admin-btn">
                                <i class="fas fa-chart-bar"></i> View Analytics
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <?php
                    $pending_query = "SELECT * FROM gyms WHERE owner_id = ? AND status = 'pending'";
                    $stmt = $db_connection->prepare($pending_query);
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $pending_result = $stmt->get_result();
                    
                    if ($pending_result->num_rows > 0) {
                        $pending_gym = $pending_result->fetch_assoc();
                        ?>
                        <p><i class="fas fa-info-circle"></i> Your gym "<?php echo htmlspecialchars($pending_gym['gym_name']); ?>" 
                           is pending approval from the superadmin.</p>
                    <?php } else { ?>
                        <p><i class="fas fa-exclamation-circle"></i> Error: No gym found for your account. 
                           Please contact support if you believe this is an error.</p>
                    <?php } ?>
                </div>
            <?php endif; ?>
        <?php elseif ($role === 'superadmin'): ?>
            <div class="superadmin-dashboard">
                <h2>Superadmin Control Panel</h2>
                
                <div class="stats-grid">
                    <?php
                    // Quick statistics for superadmin
                    $total_users = $db_connection->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
                    $total_gyms = $db_connection->query("SELECT COUNT(*) as count FROM gyms")->fetch_assoc()['count'];
                    $pending_gyms = $db_connection->query("SELECT COUNT(*) as count FROM gyms WHERE status='pending'")->fetch_assoc()['count'];
                    $total_members = $db_connection->query("SELECT COUNT(*) as count FROM gym_members")->fetch_assoc()['count'];
                    ?>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Total Users</h3>
                            <p class="stat-number"><?php echo $total_users; ?></p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-dumbbell"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Total Gyms</h3>
                            <p class="stat-number"><?php echo $total_gyms; ?></p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Pending Approvals</h3>
                            <p class="stat-number"><?php echo $pending_gyms; ?></p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Active Members</h3>
                            <p class="stat-number"><?php echo $total_members; ?></p>
                        </div>
                    </div>
                </div>

                <div class="admin-controls">
                    <div class="control-section">
                        <h3>Quick Actions</h3>
                        <div class="action-buttons">
                            <a href="manage_users.php" class="admin-btn">Manage Users</a>
                            <a href="manage_gym_applications.php" class="admin-btn">Gym Applications</a>
                            <a href="manage_gyms.php" class="admin-btn">Manage Gyms</a>
                            <a href="manage_memberships.php" class="admin-btn">Manage Memberships</a>
                            <a href="manage_membership_plans.php" class="admin-btn">Manage Plans</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php elseif ($role === 'user' || $role === 'member'): ?>
            <!-- Add this section before the featured gyms -->
            <div class="application-section">
                <?php
                // Check if user has pending application
                $check_query = "SELECT * FROM gyms WHERE owner_id = ? AND status = 'pending'";
                $stmt = $db_connection->prepare($check_query);
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $pending_application = $stmt->get_result()->fetch_assoc();
                ?>

                <?php if ($pending_application): ?>
                    <div class="pending-application">
                        <h3><i class="fas fa-clock"></i> Pending Gym Application</h3>
                        <p>Your application for "<?php echo htmlspecialchars($pending_application['gym_name']); ?>" is under review.</p>
                    </div>
                <?php else: ?>
                    <div class="become-owner">
                        <h3><i class="fas fa-dumbbell"></i> Want to List Your Gym?</h3>
                        <p>Submit an application to become a gym owner and start managing your gym on FitHub.</p>
                        <button onclick="showApplicationForm()" class="apply-btn">Apply Now</button>
                    </div>

                    <div id="applicationForm" class="application-form" style="display: none;">
                        <h3>Gym Owner Application</h3>
                        <form action="../actions/submit_gym_application.php" method="POST" enctype="multipart/form-data">
                            <div class="form-group">
                                <label for="gym_name">Gym Name *</label>
                                <input type="text" id="gym_name" name="gym_name" required>
                            </div>

                            <div class="form-group">
                                <label for="gym_location">Location *</label>
                                <input type="text" id="gym_location" name="gym_location" required>
                            </div>

                            <div class="form-group">
                                <label for="gym_phone">Phone Number *</label>
                                <input type="tel" id="gym_phone" name="gym_phone_number" required 
                                       pattern="[0-9]{10,}" title="Please enter a valid phone number">
                            </div>

                            <div class="form-group">
                                <label for="gym_description">Description *</label>
                                <textarea id="gym_description" name="gym_description" rows="4" required 
                                          placeholder="Describe your gym, its focus, and what makes it unique..."></textarea>
                            </div>

                            <div class="form-group">
                                <label for="gym_amenities">Amenities *</label>
                                <textarea id="gym_amenities" name="gym_amenities" rows="4" required 
                                          placeholder="List your gym's amenities..."></textarea>
                            </div>

                            <button type="submit" class="submit-btn">Submit Application</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
            <!-- Regular member section -->
            <section class="featured-gyms">
                <h2>Featured Gyms</h2>
                <div class="carousel-container">
                    <button class="carousel-btn prev" onclick="moveCarousel(-1)">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button class="carousel-btn next" onclick="moveCarousel(1)">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                    
                    <div class="gym-grid">
                        <?php
                        if ($result && $result->num_rows > 0):
                            while ($gym = $result->fetch_assoc()): ?>
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
                                            <?php 
                                            $avgRating = round($gym['avg_rating'], 1);
                                            for($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star <?php echo $i <= $avgRating ? 'checked' : ''; ?>"></i>
                                            <?php endfor; ?>
                                            <span class="rating-text">
                                                <?php echo number_format($avgRating, 1); ?> 
                                                (<?php echo $gym['review_count']; ?> reviews)
                                            </span>
                                        </div>
                                        <p class="gym-location">
                                            <i class="fas fa-map-marker-alt"></i> 
                                            <?php echo htmlspecialchars($gym['gym_location']); ?>
                                        </p>
                                        <a href="user_view_gym.php?gym_id=<?php echo $gym['gym_id']; ?>" 
                                           class="btn-view">View Gym</a>
                                    </div>
                                </div>
                            <?php endwhile;
                        else: ?>
                            <p class="no-gyms">No featured gyms available at the moment.</p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="carousel-dots"></div>
            </section>

            <!-- User's active memberships section -->
            <?php if ($role === 'member' && $memberships && $memberships->num_rows > 0): ?>
                <section class="my-memberships">
                    <h2>My Active Memberships</h2>
                    <div class="membership-grid">
                        <?php while ($membership = $memberships->fetch_assoc()): ?>
                            <div class="membership-card">
                                <img src="<?php echo !empty($membership['gym_thumbnail']) ? 
                                    htmlspecialchars($membership['gym_thumbnail']) : 
                                    '../assets/images/default-gym.jpg'; ?>" 
                                    alt="<?php echo htmlspecialchars($membership['gym_name']); ?>"
                                    onerror="this.src='../assets/images/default-gym.jpg'">
                                <div class="membership-details">
                                    <h3><?php echo htmlspecialchars($membership['gym_name']); ?></h3>
                                    <p class="plan-name"><?php echo htmlspecialchars($membership['plan_name']); ?></p>
                                    <p class="membership-info">
                                        <span class="label">Start Date:</span> 
                                        <?php echo date('M d, Y', strtotime($membership['start_date'])); ?>
                                    </p>
                                    <p class="membership-info">
                                        <span class="label">End Date:</span> 
                                        <?php echo date('M d, Y', strtotime($membership['end_date'])); ?>
                                    </p>
                                    <p class="membership-info">
                                        <span class="label">Duration:</span> 
                                        <?php echo htmlspecialchars($membership['duration']); ?>
                                    </p>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </section>
            <?php endif; ?>
        <?php endif; ?>
    </div>


    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <h3>About FitHub</h3>
                <p>Your trusted platform for finding and joining the perfect gym.</p>
            </div>
            <div class="footer-section">
                <h3>Quick Links</h3>
                <a href="explore_gyms.php">Find Gyms</a>
                <a href="profile.php">My Profile</a>
                <a href="../actions/logout.php">Logout</a>
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