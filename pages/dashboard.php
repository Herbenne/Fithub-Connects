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

// Update the membership query section
if ($role === 'member') {
    $membership_query = "SELECT 
                            m.*, 
                            g.gym_name, 
                            g.gym_thumbnail, 
                            p.plan_name, 
                            p.duration,
                            CASE 
                                WHEN m.status = 'inactive' THEN 'Inactive'
                                WHEN m.end_date < CURDATE() THEN 'Expired'
                                ELSE 'Active'
                            END as membership_status
                        FROM gym_members m 
                        JOIN gyms g ON m.gym_id = g.gym_id 
                        JOIN membership_plans p ON m.plan_id = p.plan_id 
                        WHERE m.user_id = ?
                        ORDER BY m.start_date DESC";
    
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
    <link rel="stylesheet" href="../assets/css/unified-theme.css">
    <link rel="stylesheet" href="../assets/css/fithub-ui.css">
    <link rel="stylesheet" href="../assets/css/featured-gyms.css">
    <!-- Add Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Add Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>

<body>
    <script>
    // Remove any leftover "Submitting Application..." header
    document.addEventListener('DOMContentLoaded', function() {
        // Check if we're on the dashboard page
        if (window.location.href.indexOf('dashboard.php') > -1) {
            // Check if there's a submitting application message
            const submittingHeader = document.querySelector('h1, h2, h3, h4, h5, h6');
            if (submittingHeader && submittingHeader.textContent.includes('Submitting Application')) {
                submittingHeader.style.display = 'none';
                const paragraph = document.querySelector('p');
                if (paragraph && paragraph.textContent.includes('Please wait')) {
                    paragraph.style.display = 'none';
                }
            }
        }
    });
    </script>
    <!-- Loading overlay -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="spinner"></div>
        <h3>Submitting Application...</h3>
        <p>Please wait while we upload your files and process your application.</p>
    </div>
    <nav class="navbar">
        <div class="nav-brand"> <img src="<?php echo dirname($_SERVER['PHP_SELF']) ?>/../assets/logo/FITHUB LOGO.png" 
                 alt="Fithub Logo" 
                 style="max-height: 50px;"
            ></div>
        <div class="nav-links">
            <?php if (!isset($_SESSION['user_id'])): ?>
                <a href="login.php">Login</a>
                <a href="register.php">Register</a>
            <?php else: ?>
                <?php if ($_SESSION['role'] === 'user'): ?>
                    <a href="dashboard.php">Dashboard</a>
                    <a href="explore_gyms.php">Explore Gyms</a>
                    <a href="profile.php">My Profile</a>
                <?php elseif ($_SESSION['role'] === 'member'): ?>
                    <a href="dashboard.php">Dashboard</a>
                    <a href="explore_gyms.php">Explore Gyms</a>
                    <a href="profile.php">My Profile</a>
                <?php elseif ($_SESSION['role'] === 'admin'): ?>
                    <a href="dashboard.php">Dashboard</a>
                    <a href="edit_gym.php">My Gym</a>
                    <a href="profile.php">My Profile</a>
                <?php elseif ($_SESSION['role'] === 'superadmin'): ?>
                    <a href="all_gyms_analytics.php">FitHub Analytics</a>
                <?php endif; ?>
                <a href="../actions/logout.php">Logout</a>
            <?php endif; ?>
        </div>
    </nav>
    <div class="dashboard-container">     
    <?php
    // Display success and error messages based on URL parameters
    if (isset($_GET['success']) || isset($_GET['error'])) {
        $messageHtml = '';
        
        // Success messages
        if (isset($_GET['success'])) {
            $success = $_GET['success'];
            if ($success === 'application_submitted') {
                $messageHtml = '<div class="success-message"><i class="fas fa-check-circle"></i> Your gym application has been submitted successfully! We will review your application shortly.</div>';
            } else if ($success === '1') {
                $messageHtml = '<div class="success-message"><i class="fas fa-check-circle"></i> Operation completed successfully!</div>';
            }
        }
        
        // Error messages
        if (isset($_GET['error'])) {
            $error = $_GET['error'];
            $errorMsg = isset($_GET['message']) ? urldecode($_GET['message']) : '';
            
            switch($error) {
                case 'missing_fields':
                    $messageHtml = '<div class="error-message"><i class="fas fa-exclamation-circle"></i> Please fill in all required fields.</div>';
                    break;
                case 'missing_documents':
                    $messageHtml = '<div class="error-message"><i class="fas fa-exclamation-circle"></i> ' . ($errorMsg ?: 'Please upload all required documents.') . '</div>';
                    break;
                case 'application_failed':
                    $messageHtml = '<div class="error-message"><i class="fas fa-exclamation-circle"></i> ' . ($errorMsg ?: 'Failed to submit application. Please try again.') . '</div>';
                    break;
                case 'existing_application':
                    $messageHtml = '<div class="error-message"><i class="fas fa-exclamation-circle"></i> You already have a pending gym application. Please wait for approval.</div>';
                    break;
                case 'existing_approved_gym':
                    $messageHtml = '<div class="error-message"><i class="fas fa-exclamation-circle"></i> You already have an approved gym. You cannot submit another application.</div>';
                    break;
                default:
                    $messageHtml = '<div class="error-message"><i class="fas fa-exclamation-circle"></i> An error occurred: ' . htmlspecialchars($error) . '</div>';
            }
        }
        
        // Output the message if we have one
        if (!empty($messageHtml)) {
            echo $messageHtml;
            
            // Clean URL after showing message (using JavaScript)
            echo '<script>
                // Wait until page is loaded
                window.addEventListener("DOMContentLoaded", function() {
                    // Remove query parameters from URL without reloading the page
                    if (window.history && window.history.pushState) {
                        const newUrl = window.location.pathname;
                        window.history.pushState({}, document.title, newUrl);
                    }
                    
                    // Auto-hide messages after 10 seconds
                    setTimeout(function() {
                        const messages = document.querySelectorAll(".success-message, .error-message");
                        messages.forEach(function(message) {
                            message.style.opacity = "0";
                            message.style.transition = "opacity 1s";
                            setTimeout(function() {
                                message.remove();
                            }, 1000);
                        });
                    }, 10000);
                });
            </script>';
        }
    }
?>       

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
                    // Updated statistics for superadmin
                    $total_users = $db_connection->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
                    // Modified to count only approved gyms
                    $total_gyms = $db_connection->query("SELECT COUNT(*) as count FROM gyms WHERE status = 'approved'")->fetch_assoc()['count'];
                    $pending_gyms = $db_connection->query("SELECT COUNT(*) as count FROM gyms WHERE status = 'pending'")->fetch_assoc()['count'];
                    $total_members = $db_connection->query("SELECT COUNT(*) as count FROM gym_members")->fetch_assoc()['count'];
                    ?>
                    
                    <!-- Rest of the stats cards stay the same -->
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
                            <h3>Approved Gyms</h3>
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
                            <!-- New Button for Analytics -->
                            <a href="all_gyms_analytics.php" class="admin-btn">
                                <i class="fas fa-chart-bar"></i> View All Gym Analytics
                            </a>
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

                    <h4>Legal Documents</h4>
                    <div class="form-group">
                        <label for="business_permit">Business/Mayor's Permit *</label>
                        <input type="file" id="business_permit" name="business_permit" required accept=".pdf,.jpg,.jpeg,.png">
                        <small>Upload your valid business permit (PDF, JPG, PNG formats)</small>
                    </div>

                    <div class="form-group">
                        <label for="valid_id">Valid ID of Gym Owner *</label>
                        <input type="file" id="valid_id" name="valid_id" required accept=".pdf,.jpg,.jpeg,.png">
                        <small>Upload a government-issued ID (PDF, JPG, PNG formats)</small>
                     </div>

                    <div class="form-group">
                        <label for="tax_certificate">Tax Certificate *</label>
                        <input type="file" id="tax_certificate" name="tax_certificate" required accept=".pdf,.jpg,.jpeg,.png">
                        <small>Upload your tax certificate (PDF, JPG, PNG formats)</small>
                    </div>

                    <button type="submit" class="submit-btn">Submit Application</button>
                </form>
            </div>
                    
                <?php endif; ?>
            </div>
            <!-- Regular member section -->
            <section class="featured-gyms">
                <h2>Featured Gyms</h2>
                <div id="gymCarousel" class="carousel slide" data-bs-ride="carousel">
                    <div class="carousel-inner">
                        <?php 
                        if ($result && $result->num_rows > 0):
                            $items = array_chunk(iterator_to_array($result), 3); // Group items by 3
                            foreach($items as $index => $group): 
                        ?>
                            <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                                <div class="row">
                                    <?php foreach($group as $gym): ?>
                                        <div class="col-md-4">
                                            <div class="gym-card">
                                                <div class="gym-image">
                                                    <img src="<?php echo !empty($gym['gym_thumbnail']) ? 
                                                        htmlspecialchars($gym['gym_thumbnail']) : 
                                                        '../assets/images/default-gym.jpg'; ?>" 
                                                        alt="<?php echo htmlspecialchars($gym['gym_name']); ?>"
                                                        onerror="this.onerror=null; this.src='../assets/images/default-gym.jpg';">
                                                </div>
                                                <div class="gym-info">
                                                    <h3><?php echo htmlspecialchars($gym['gym_name']); ?></h3>
                                                    <div class="gym-rating">
                                                        <div class="stars">
                                                            <?php 
                                                            $rating = round($gym['avg_rating'], 1);
                                                            for ($i = 1; $i <= 5; $i++): ?>
                                                                <i class="bi bi-star-fill <?php echo $i <= $rating ? 'checked' : ''; ?>"></i>
                                                            <?php endfor; ?>
                                                        </div>
                                                        <span class="rating-text">
                                                            (<?php echo $gym['review_count']; ?> reviews)
                                                        </span>
                                                    </div>
                                                    <a href="user_view_gym.php?gym_id=<?php echo $gym['gym_id']; ?>" 
                                                        class="view-gym-btn">View Gym</a>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php 
                            endforeach;
                        endif; 
                        ?>
                    </div>
                    
                    <!-- Updated carousel controls with custom styling -->
                    <button class="custom-carousel-control custom-prev" type="button" data-bs-target="#gymCarousel" data-bs-slide="prev">
                        <i class="bi bi-chevron-left"></i>
                    </button>
                    <button class="custom-carousel-control custom-next" type="button" data-bs-target="#gymCarousel" data-bs-slide="next">
                        <i class="bi bi-chevron-right"></i>
                    </button>
                </div>
            </section>

<style>
.custom-carousel-control {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    width: 40px;
    height: 40px;
    background-color: #007bff;
    border: none;
    border-radius: 50%;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0.7;
    transition: opacity 0.3s;
}

.custom-carousel-control:hover {
    opacity: 1;
    background-color: #0056b3;
}

.custom-prev {
    left: -20px;
}

.custom-next {
    right: -20px;
}

.bi-chevron-left, .bi-chevron-right {
    font-size: 20px;
}

/* Add to your existing CSS */
.membership-card {
    position: relative;
    border: 1px solid #ddd;
    border-radius: 8px;
    overflow: hidden;
    transition: transform 0.2s;
}

.membership-card.inactive {
    opacity: 0.8;
    border-color: #f44336;
}

.membership-card.expired {
    opacity: 0.8;
    border-color: #ff9800;
}

.status-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    padding: 5px 10px;
    border-radius: 4px;
    color: white;
    font-weight: bold;
    z-index: 1;
}

.status-badge.active {
    background-color: #4CAF50;
}

.status-badge.inactive {
    background-color: #f44336;
}

.status-badge.expired {
    background-color: #ff9800;
}

.loading-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.7);
        z-index: 9999;
        justify-content: center;
        align-items: center;
        flex-direction: column;
        color: white;
}
.spinner {
        border: 5px solid #f3f3f3;
        border-top: 5px solid #3498db;
        border-radius: 50%;
        width: 50px;
        height: 50px;
        animation: spin 2s linear infinite;
        margin-bottom: 20px;
}
@keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
}

/* Style for success messages */
.success-message {
        background-color: #4CAF50;
        color: white;
        padding: 15px;
        margin-bottom: 20px;
        border-radius: 4px;
        display: flex;
        align-items: center;
}
.success-message i {
        margin-right: 10px;
        font-size: 24px;
}

/* Media queries for responsive design */
@media (max-width: 768px) {
    .header-content {
        flex-direction: column;
        gap: 15px;
    }
    
    .nav-links {
        width: 100%;
        justify-content: center;
        flex-wrap: wrap;
    }
}
</style>

            <!-- User's active memberships section -->
            <?php if ($role === 'member' && $memberships && $memberships->num_rows > 0): ?>
                <section class="my-memberships">
                    <h2>My Memberships</h2>
                    <div class="membership-grid">
                        <?php while ($membership = $memberships->fetch_assoc()): ?>
                            <div class="membership-card <?php echo strtolower($membership['membership_status']); ?>">
                                <div class="status-badge <?php echo strtolower($membership['membership_status']); ?>">
                                    <?php echo htmlspecialchars($membership['membership_status']); ?>
                                </div>
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

    <!-- Move JavaScript to end of body -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/dashboard.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    // Show loading overlay when the gym application form is submitted
    document.addEventListener("DOMContentLoaded", function() {
        const applicationForm = document.querySelector("#applicationForm form");
        
        if (applicationForm) {
            applicationForm.addEventListener("submit", function(e) {
                // Check if all required fields are filled
                const requiredFields = this.querySelectorAll("[required]");
                let allFilled = true;
                
                requiredFields.forEach(field => {
                    if (!field.value) {
                        allFilled = false;
                    }
                });
                
                // Check if all required file uploads are selected
                const requiredFileInputs = ['gym_thumbnail', 'business_permit', 'valid_id', 'tax_certificate'];
                requiredFileInputs.forEach(inputName => {
                    const input = this.querySelector(`input[name="${inputName}"]`);
                    if (input && input.hasAttribute('required') && !input.files.length) {
                        allFilled = false;
                    }
                });
                
                if (allFilled) {
                    // Show loading overlay
                    document.getElementById("loadingOverlay").style.display = "flex";
                    return true;
                } else {
                    // Don't submit yet - browser will show validation messages
                    return false;
                }
            });
        }
        
        // Check if there's a success message in the URL
        const urlParams = new URLSearchParams(window.location.search);
        const successParam = urlParams.get('success');
        
        if (successParam === 'application_submitted') {
            // Create a success message element
            const successMessage = document.createElement('div');
            successMessage.className = 'success-message';
            successMessage.innerHTML = '<i class="fas fa-check-circle"></i> Your gym application has been submitted successfully! We will review your application shortly.';
            
            // Insert at the top of the dashboard container
            const dashboardContainer = document.querySelector('.dashboard-container');
            if (dashboardContainer) {
                dashboardContainer.insertBefore(successMessage, dashboardContainer.firstChild);
                
                // Scroll to the success message
                successMessage.scrollIntoView({ behavior: 'smooth' });
                
                // Remove success parameter from URL without refreshing
                const newUrl = window.location.pathname;
                history.pushState({}, '', newUrl);
                
                // Remove message after 10 seconds
                setTimeout(() => {
                    successMessage.style.opacity = '0';
                    successMessage.style.transition = 'opacity 1s';
                    setTimeout(() => successMessage.remove(), 1000);
                }, 10000);
            }
        }
        
        // Also check for error message
        const errorParam = urlParams.get('error');
        const errorMessage = urlParams.get('message');
        
        if (errorParam === 'application_failed') {
            // Create an error message element
            const errorElement = document.createElement('div');
            errorElement.className = 'alert alert-error';
            errorElement.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + 
                (errorMessage ? decodeURIComponent(errorMessage) : 'Failed to submit gym application. Please try again.');
            
            // Insert at the top of the dashboard container
            const dashboardContainer = document.querySelector('.dashboard-container');
            if (dashboardContainer) {
                dashboardContainer.insertBefore(errorElement, dashboardContainer.firstChild);
                
                // Scroll to the error message
                errorElement.scrollIntoView({ behavior: 'smooth' });
            }
        }
    });

        document.addEventListener('DOMContentLoaded', function() {
            // Check if there's a new membership from payment success
            const newMembership = localStorage.getItem('newMembership');
            
            if (newMembership === 'true') {
                // Get stored membership details
                const gymName = localStorage.getItem('membershipGym');
                const planName = localStorage.getItem('membershipPlan');
                
                // Create a success notification
                const notification = document.createElement('div');
                notification.className = 'success-message';
                notification.innerHTML = `<i class="fas fa-check-circle"></i> Congratulations! You are now a member of ${gymName}.`;
                
                // Add it to the dashboard
                const dashboardContainer = document.querySelector('.dashboard-container');
                if (dashboardContainer) {
                    dashboardContainer.insertBefore(notification, dashboardContainer.firstChild);
                }
                
                // If there's no memberships section yet, create one
                if (!document.querySelector('.my-memberships')) {
                    // Create membership section
                    const membershipSection = document.createElement('section');
                    membershipSection.className = 'my-memberships';
                    membershipSection.innerHTML = `
                        <h2>My Memberships</h2>
                        <div class="membership-grid">
                            <div class="membership-card active">
                                <div class="status-badge active">
                                    Active
                                </div>
                                <img src="../assets/images/default-gym.jpg" alt="${gymName}" 
                                    onerror="this.src='../assets/images/default-gym.jpg'">
                                <div class="membership-details">
                                    <h3>${gymName}</h3>
                                    <p class="plan-name">${planName}</p>
                                    <p class="membership-info">
                                        <span class="label">Start Date:</span> 
                                        ${new Date().toLocaleDateString()}
                                    </p>
                                    <p class="membership-info">
                                        <span class="label">Status:</span> 
                                        Active
                                    </p>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    // Insert before the featured gyms section or at the end
                    const featuredGyms = document.querySelector('.featured-gyms');
                    if (featuredGyms) {
                        dashboardContainer.insertBefore(membershipSection, featuredGyms);
                    } else {
                        dashboardContainer.appendChild(membershipSection);
                    }
                }
                
                // Clear the localStorage to prevent showing again on refresh
                localStorage.removeItem('newMembership');
                localStorage.removeItem('membershipGym');
                localStorage.removeItem('membershipPlan');
                
                // Force reload the page after 2 seconds to get the fresh data from server
                setTimeout(function() {
                    window.location.reload();
                }, 2000);
            }
        });
    <script>
    
</body>

</html>