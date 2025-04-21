<?php
session_start();
include '../config/database.php';

// Update the access control section
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

// Check if user is admin or superadmin
$is_admin = $_SESSION['role'] === 'admin';
$is_superadmin = $_SESSION['role'] === 'superadmin';

if (!$is_admin && !$is_superadmin) {
    header("Location: explore_gyms.php");
    exit();
}

if (!isset($_GET['gym_id'])) {
    header("Location: explore_gyms.php");
    exit();
}

$gym_id = $_GET['gym_id'];

// Modified query to properly handle first_name and last_name
$query = "SELECT g.*, 
          u.id as owner_user_id,
          u.first_name,
          u.last_name,
          CONCAT(u.first_name, ' ', u.last_name) as owner_name
          FROM gyms g 
          LEFT JOIN users u ON g.owner_id = u.id 
          WHERE g.gym_id = ? AND g.status = 'approved'";

// Add error checking for the prepare statement
$stmt = $db_connection->prepare($query);

if ($stmt === false) {
    // Log the error for debugging
    error_log("Query preparation failed: " . $db_connection->error);
    header("Location: explore_gyms.php?error=database_error");
    exit();
}

// Add error handling for bind_param
if (!$stmt->bind_param("i", $gym_id)) {
    error_log("Binding parameters failed: " . $stmt->error);
    header("Location: explore_gyms.php?error=database_error");
    exit();
}

// Execute query with error handling
if (!$stmt->execute()) {
    error_log("Query execution failed: " . $stmt->error);
    header("Location: explore_gyms.php?error=database_error");
    exit();
}

$result = $stmt->get_result();
$gym = $result->fetch_assoc();

// Add error checking for results
if (!$gym) {
    header("Location: explore_gyms.php?error=invalid_gym");
    exit();
}

// Store owner_id in a variable for easier access
$owner_id = $gym['owner_id'] ?? null;

// Replace the reviews fetch section with this updated code
// Fetch reviews
$review_query = "SELECT r.*, 
                 u.username, 
                 CONCAT(u.first_name, ' ', u.last_name) as full_name 
                 FROM gym_reviews r 
                 JOIN users u ON r.user_id = u.id 
                 WHERE r.gym_id = ? 
                 ORDER BY r.created_at DESC";

$review_stmt = $db_connection->prepare($review_query);

if ($review_stmt === false) {
    error_log("Review query preparation failed: " . $db_connection->error);
    $reviews = [];
} else {
    if (!$review_stmt->bind_param("i", $gym_id)) {
        error_log("Review binding parameters failed: " . $review_stmt->error);
        $reviews = [];
    } else {
        if (!$review_stmt->execute()) {
            error_log("Review query execution failed: " . $review_stmt->error);
            $reviews = [];
        } else {
            $reviews = $review_stmt->get_result();
        }
    }
}

// Calculate average rating with error handling
$rating_query = "SELECT AVG(rating) as avg_rating FROM gym_reviews WHERE gym_id = ?";
$rating_stmt = $db_connection->prepare($rating_query);

if ($rating_stmt === false) {
    error_log("Rating query preparation failed: " . $db_connection->error);
    $avg_rating = 0;
} else {
    if (!$rating_stmt->bind_param("i", $gym_id)) {
        error_log("Rating binding parameters failed: " . $rating_stmt->error);
        $avg_rating = 0;
    } else {
        if (!$rating_stmt->execute()) {
            error_log("Rating query execution failed: " . $rating_stmt->error);
            $avg_rating = 0;
        } else {
            $result = $rating_stmt->get_result();
            $avg_rating = $result->fetch_assoc()['avg_rating'] ?? 0;
        }
    }
}

// Update the canPostReview function
function canPostReview($userId, $gymData) {
    global $is_admin, $is_superadmin;
    
    // Superadmin cannot post reviews
    if ($is_superadmin) {
        return false;
    }
    
    // Admin cannot review their own gym
    if ($is_admin && isset($gymData['owner_id']) && $userId == $gymData['owner_id']) {
        return false;
    }
    
    return true;
}

// Add after canPostReview function and before the HTML

function canDeleteContent($contentUserId, $gymData) {
    global $is_superadmin, $is_admin;
    
    // Superadmin can delete anything
    if ($is_superadmin) {
        return true;
    }
    
    // Admin can delete content on their own gym
    if ($is_admin && isset($gymData['owner_id']) && $_SESSION['user_id'] == $gymData['owner_id']) {
        return true;
    }
    
    // Users can delete their own content
    return $_SESSION['user_id'] == $contentUserId;
}

// Modified owner check logic
function isGymOwner($userId, $gymData) {
    return isset($userId) && isset($gymData['owner_id']) && $userId == $gymData['owner_id'];
}

// Set is_gym_owner flag
$is_gym_owner = isGymOwner($_SESSION['user_id'] ?? null, $gym);
?>

<!DOCTYPE html>
<html>
<head>
    <title><?php echo htmlspecialchars($gym['gym_name']); ?> - Admin View</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/mains.css">
    <link rel="stylesheet" href="../assets/css/user_view_gym.css">
</head>
<body>
    <div class="page-container">
        <!-- Navigation Bar -->
        <nav>
            <div class="back-to-explore-button-contianer">
                <a href="explore_gyms.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Explorer
                </a>
            </div>
           
        </nav>

        <!-- Admin Notice -->
        <div class="info-card admin-notice">
            <h3>
                <i class="<?php echo $is_superadmin ? 'fas fa-shield-alt' : 'fas fa-user-shield'; ?>"></i>
                Admin Access
            </h3>
            <p>
                <?php if ($is_superadmin): ?>
                    You are viewing this gym as a superadmin. You have full administrative privileges.
                <?php else: ?>
                    You are viewing this gym as an administrator.
                <?php endif; ?>
            </p>
        </div>

        <!-- Admin Actions -->
        <?php if ($is_superadmin): ?>
        <div class="info-card admin-actions">
            <h3><i class="fas fa-tools"></i> Admin Controls</h3>
            <div class="admin-buttons">
                <button class="join-btn edit-btn" onclick="window.location.href='edit_gym.php?gym_id=<?php echo $gym_id; ?>'">
                    <i class="fas fa-edit"></i> Edit Gym
                </button>
                <form action="../actions/delete_gym.php" method="POST" style="display: inline;">
                    <input type="hidden" name="gym_id" value="<?php echo $gym_id; ?>">
                    <button type="submit" class="join-btn delete-btn" 
                            onclick="return confirm('Are you sure you want to delete this gym? This action cannot be undone.');">
                        <i class="fas fa-trash-alt"></i> Delete Gym
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Main Content -->
        <div class="gym-details">
            <!-- Hero Section -->
            <div class="gym-hero">
                <img src="<?php echo !empty($gym['gym_thumbnail']) ? 
                    htmlspecialchars($gym['gym_thumbnail']) : 
                    '../assets/images/default-gym.jpg'; ?>" 
                    alt="<?php echo htmlspecialchars($gym['gym_name']); ?>"
                    class="gym-hero-image"
                    onload="this.style.opacity='1'"
                    onerror="this.src='../assets/images/default-gym.jpg';">
                <div class="gym-hero-overlay">
                    <h1><?php echo htmlspecialchars($gym['gym_name']); ?></h1>
                    <div class="gym-rating">
                        <div class="stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star <?php echo $i <= $avg_rating ? 'checked' : ''; ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <span><?php echo number_format($avg_rating, 1); ?> 
                            (<?php echo $reviews->num_rows; ?> reviews)</span>
                    </div>
                </div>
            </div>

            <!-- Info Grid -->
            <div class="info-grid">
                <div class="info-card">
                    <h3><i class="fas fa-map-marker-alt"></i> Location</h3>
                    <p><?php echo htmlspecialchars($gym['gym_location']); ?></p>
                </div>

                <div class="info-card">
                    <h3><i class="fas fa-phone"></i> Contact</h3>
                    <p><?php echo htmlspecialchars($gym['gym_phone_number']); ?></p>
                </div>

                <div class="info-card description">
                    <h3><i class="fas fa-info-circle"></i> About This Gym</h3>
                    <p><?php echo nl2br(htmlspecialchars($gym['gym_description'])); ?></p>
                </div>

                <div class="info-card">
                    <h3><i class="fas fa-dumbbell"></i> Amenities</h3>
                    <p><?php echo nl2br(htmlspecialchars($gym['gym_amenities'])); ?></p>
                </div>
            </div>

            <!-- Reviews Section -->
            <section class="reviews-section">
                <h2>Reviews & Ratings</h2>
                <div class="reviews-list">
                    <?php while ($review = $reviews->fetch_assoc()): ?>
                        <div class="review-card">
                            <div class="review-header">
                                <div class="reviewer-info">
                                    <strong><?php echo htmlspecialchars($review['full_name']); ?></strong>
                                    <div class="star-rating">
                                        <?php 
                                        $rating = (float)$review['rating'];
                                        for ($i = 1; $i <= 5; $i++): 
                                        ?>
                                            <i class="fas fa-star <?php echo ($i <= $rating) ? 'checked' : ''; ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <div class="review-actions">
                                    <span class="review-date">
                                        <?php echo date('M d, Y', strtotime($review['created_at'])); ?>
                                    </span>
                                    <?php if (canDeleteContent($review['user_id'], $gym)): ?>
                                        <form action="../actions/delete_review.php" method="POST" style="display: inline;">
                                            <input type="hidden" name="review_id" value="<?php echo $review['review_id']; ?>">
                                            <input type="hidden" name="gym_id" value="<?php echo $gym_id; ?>">
                                            <button type="submit" class="delete-btn" 
                                                    onclick="return confirm('Delete this review?');">Delete</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <p class="review-content"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>

                            <!-- Reply Button and Form -->
                            <button class="reply-btn" onclick="toggleReplyForm(<?php echo $review['review_id']; ?>)">
                                <i class="fas fa-reply"></i> Reply as Admin
                            </button>

                            <div id="reply-form-<?php echo $review['review_id']; ?>" class="comment-form" style="display: none;">
                                <form action="../actions/submit_comment.php" method="POST">
                                    <input type="hidden" name="review_id" value="<?php echo $review['review_id']; ?>">
                                    <input type="hidden" name="gym_id" value="<?php echo $gym_id; ?>">
                                    <textarea name="comment" required placeholder="Write your reply..."></textarea>
                                    <button type="submit" class="submit-btn">Submit Reply</button>
                                </form>
                            </div>

                            <!-- Comments -->
                            <?php 
                            // Fetch comments for this review with proper error handling
                            $comment_query = "SELECT c.*, 
                                             CONCAT(u.first_name, ' ', u.last_name) as full_name,
                                             u.username 
                                             FROM review_comments c 
                                             JOIN users u ON c.user_id = u.id 
                                             WHERE c.review_id = ? 
                                             ORDER BY c.created_at ASC";
                            
                            $comment_stmt = $db_connection->prepare($comment_query);
                            
                            if ($comment_stmt === false) {
                                error_log("Comment query preparation failed: " . $db_connection->error);
                                echo "<p>Error loading comments.</p>";
                            } else {
                                if (!$comment_stmt->bind_param("i", $review['review_id'])) {
                                    error_log("Comment binding parameters failed: " . $comment_stmt->error);
                                    echo "<p>Error loading comments.</p>";
                                } else {
                                    if (!$comment_stmt->execute()) {
                                        error_log("Comment query execution failed: " . $comment_stmt->error);
                                        echo "<p>Error loading comments.</p>";
                                    } else {
                                        $comments = $comment_stmt->get_result();
                                        if ($comments && $comments->num_rows > 0) {
                                            while ($comment = $comments->fetch_assoc()): ?>
                                                <div class="comment-item">
                                                    <strong>
                                                        <?php echo htmlspecialchars($comment['full_name']); ?>
                                                        <?php if ($comment['user_id'] == $gym['owner_id']): ?>
                                                            <span class="owner-badge">Gym Owner</span>
                                                        <?php endif; ?>
                                                    </strong>
                                                    <span class="review-date">
                                                        <?php echo date('M d, Y', strtotime($comment['created_at'])); ?>
                                                    </span>
                                                    <?php if (canDeleteContent($comment['user_id'], $gym)): ?>
                                                        <form action="../actions/delete_comment.php" method="POST" style="display: inline;">
                                                            <input type="hidden" name="comment_id" value="<?php echo $comment['comment_id']; ?>">
                                                            <input type="hidden" name="gym_id" value="<?php echo $gym_id; ?>">
                                                            <button type="submit" class="delete-btn" 
                                                                    onclick="return confirm('Are you sure you want to delete this comment?');">
                                                                Delete
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <p><?php echo nl2br(htmlspecialchars($comment['comment'])); ?></p>
                                                </div>
                                            <?php endwhile;
                                        } else {
                                            echo "<p>No comments yet.</p>";
                                        }
                                    }
                                }
                            }
                            ?>
                        </div>
                    <?php endwhile; ?>
                </div>
            </section>
        </div>
    </div>

    <script src="../assets/js/view_gym.js"></script>
</body>
</html>