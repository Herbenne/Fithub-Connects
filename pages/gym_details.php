<?php
session_start();
include '../config/database.php';

// Initialize variables
$equipment_images = [];
$comments = null;
$owner_id = null;

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get gym_id from URL and validate
$gym_id = $_GET['gym_id'] ?? null;
if (!$gym_id) {
    header("Location: explore_gyms.php");
    exit();
}

// Redirect based on role
if ($_SESSION['role'] === 'admin') {
    header("Location: view_gym.php?gym_id=" . $gym_id);
    exit();
} elseif ($_SESSION['role'] === 'user' || $_SESSION['role'] === 'member') {
    header("Location: user_view_gym.php?gym_id=" . $gym_id);
    exit();
}

$gym_id = $_GET['gym_id'];

// First, add error checking for the prepare statement
$query = "SELECT g.*, g.owner_id, u.id as owner_user_id, 
          CONCAT(u.first_name, ' ', u.last_name) as owner_name 
          FROM gyms g 
          LEFT JOIN users u ON g.owner_id = u.id 
          WHERE g.gym_id = ? AND g.status = 'approved'";

$stmt = $db_connection->prepare($query);

if ($stmt === false) {
    // Log the error for debugging
    error_log("Query preparation failed: " . $db_connection->error);
    header("Location: explore_gyms.php?error=database_error");
    exit();
}

$stmt->bind_param("i", $gym_id);
$stmt->execute();
$gym = $stmt->get_result()->fetch_assoc();

// Add after fetching gym data
$equipment_images = [];
if (!empty($gym['equipment_images'])) {
    $equipment_images = json_decode($gym['equipment_images'], true) ?? [];
}

// Add error checking for results
if (!$gym) {
    header("Location: explore_gyms.php?error=invalid_gym");
    exit();
}

// Update any code that uses full_name to use concatenated first_name and last_name
$owner_name = $gym['owner_name'] ?? 'Unknown Owner';

// Define owner_id right after fetching gym data
$owner_id = $gym['owner_id'] ?? null;

// Modified owner check logic
function isGymOwner($userId, $gymData)
{
    return isset($userId) && isset($gymData['owner_id']) && $userId == $gymData['owner_id'];
}

function canDeleteContent($contentUserId, $gymData)
{
    if (!isset($_SESSION['user_id'])) {
        return false;
    }

    // User can delete their own content
    if ($_SESSION['user_id'] == $contentUserId) {
        return true;
    }

    // Superadmin can delete anything
    if ($_SESSION['role'] == 'superadmin') {
        return true;
    }

    // Gym owner can delete content on their gym
    if ($_SESSION['role'] == 'admin' && isGymOwner($_SESSION['user_id'], $gymData)) {
        return true;
    }

    return false;
}

// Set is_gym_owner flag
$is_gym_owner = isGymOwner($_SESSION['user_id'] ?? null, $gym);

// Fetch membership plans
$query = "SELECT * FROM membership_plans WHERE gym_id = ?";
$stmt = $db_connection->prepare($query);
$stmt->bind_param("i", $gym_id);
$stmt->execute();
$memberships = $stmt->get_result();

// Fetch reviews
$review_query = "SELECT r.review_id, r.user_id, r.rating, r.comment, r.created_at,
                 u.username, CONCAT(u.first_name, ' ', u.last_name) as full_name 
                 FROM gym_reviews r 
                 JOIN users u ON r.user_id = u.id 
                 WHERE r.gym_id = ? 
                 ORDER BY r.created_at DESC";

$stmt = $db_connection->prepare($review_query);

// Add error checking for the prepare statement
if ($stmt === false) {
    error_log("Review query preparation failed: " . $db_connection->error);
    $reviews = [];
} else {
    $stmt->bind_param("i", $gym_id);
    $stmt->execute();
    $reviews = $stmt->get_result();
}

// Calculate average rating with error handling
$rating_query = "SELECT AVG(rating) as avg_rating FROM gym_reviews WHERE gym_id = ?";
$stmt = $db_connection->prepare($rating_query);

if ($stmt === false) {
    error_log("Rating query preparation failed: " . $db_connection->error);
    $avg_rating = 0;
} else {
    $stmt->bind_param("i", $gym_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $avg_rating = $result->fetch_assoc()['avg_rating'] ?? 0;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title><?php echo htmlspecialchars($gym['gym_name']); ?> - GymHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/gym_details.css">
    <link rel="stylesheet" href="../assets/css/unified-theme.css">
    <style>
        .page-loader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            transition: opacity 0.3s;
        }
        .page-loader.hidden {
            opacity: 0;
            pointer-events: none;
        }
        .loader {
            width: 48px;
            height: 48px;
            border: 5px solid #007bff;
            border-bottom-color: transparent;
            border-radius: 50%;
            animation: rotation 1s linear infinite;
        }
        @keyframes rotation {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="page-loader">
        <div class="loader"></div>
    </div>
    <div class="gym-details-container">
        <div class="gym-header">
            <div>
                <a href="dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
                <h1 class="gym-title"><?php echo htmlspecialchars($gym['gym_name']); ?></h1>
            </div>
            <div class="average-rating-header">
                <div class="star-rating">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <?php if ($i <= $avg_rating): ?>
                            <i class="fas fa-star"></i>
                        <?php elseif ($i - $avg_rating < 1): ?>
                            <i class="fas fa-star-half-alt"></i>
                        <?php else: ?>
                            <i class="far fa-star"></i>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
                <span><?php echo number_format($avg_rating, 1); ?> out of 5</span>
            </div>
        </div>

        <div class="gym-thumbnail-container">
            <img data-src="<?php echo htmlspecialchars($gym['gym_thumbnail']); ?>"
                 alt="<?php echo htmlspecialchars($gym['gym_name']); ?>"
                 class="gym-thumbnail"
                 onerror="this.onerror=null; this.src='../assets/images/default-gym.jpg';">
        </div>

        <div class="gym-info-grid">
            <div class="info-card">
                <h3><i class="fas fa-map-marker-alt"></i> Location</h3>
                <p><?php echo htmlspecialchars($gym['gym_location']); ?></p>
            </div>

            <div class="info-card">
                <h3><i class="fas fa-phone"></i> Contact</h3>
                <p><?php echo htmlspecialchars($gym['gym_phone_number']); ?></p>
            </div>

            <div class="info-card">
                <h3><i class="fas fa-info-circle"></i> Description</h3>
                <p><?php echo nl2br(htmlspecialchars($gym['gym_description'])); ?></p>
            </div>

            <div class="info-card">
                <h3><i class="fas fa-dumbbell"></i> Amenities</h3>
                <p><?php echo nl2br(htmlspecialchars($gym['gym_amenities'])); ?></p>
            </div>
        </div>

        <div class="membership-plans">
            <h2>Membership Plans</h2>
            <div class="plans-grid">
                <?php if ($memberships->num_rows > 0): 
                    while ($plan = $memberships->fetch_assoc()): ?>
                        <div class="plan-card">
                            <h3 class="plan-name"><?php echo htmlspecialchars($plan['plan_name']); ?></h3>
                            <div class="plan-duration"><?php echo htmlspecialchars($plan['duration']); ?></div>
                            <div class="plan-price">₱<?php echo number_format($plan['price'], 2); ?></div>
                            <p class="plan-description"><?php echo nl2br(htmlspecialchars($plan['description'])); ?></p>
                            <form action="../actions/process_payment.php" method="POST">
                                <input type="hidden" name="gym_id" value="<?php echo $gym_id; ?>">
                                <input type="hidden" name="membership_plans" value="<?php echo $plan['plan_id']; ?>">
                                <button type="submit" class="join-btn">Join Now</button>
                            </form>
                        </div>
                    <?php endwhile;
                else: ?>
                    <p class="no-plans">No membership plans available at the moment.</p>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($equipment_images)): ?>
        <div class="equipment-gallery">
            <h2>Our Equipment</h2>
            <div class="gallery-grid">
                <?php foreach ($equipment_images as $image): ?>
                    <div class="gallery-item">
                        <img data-src="<?php echo htmlspecialchars($image); ?>"
                             alt="Gym Equipment"
                             onerror="this.onerror=null; this.src='../assets/images/default-equipment.jpg';"
                             onclick="showLightbox(this.src || this.dataset.src)">
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Reviews Section - Keep existing code but wrap in reviews-section div -->
        <div class="reviews-section">
            <h2>Reviews & Ratings</h2>

            <?php if ($avg_rating): ?>
                <div class="average-rating">
                    <h3>Average Rating: <?php echo number_format($avg_rating, 1); ?> / 5</h3>
                    <div class="star-rating">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <?php if ($i <= $avg_rating): ?>
                                <i class="fas fa-star"></i>
                            <?php elseif ($i - $avg_rating < 1): ?>
                                <i class="fas fa-star-half-alt"></i>
                            <?php else: ?>
                                <i class="far fa-star"></i>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="review-form">
                    <h3>Write a Review</h3>
                    <form action="../actions/submit_review.php" method="POST">
                        <input type="hidden" name="gym_id" value="<?php echo $gym_id; ?>">
                        
                        <div class="rating-select">
                            <label>Your Rating:</label>
                            <div class="star-rating-input">
                                <?php for($i = 5; $i >= 1; $i--): ?>
                                    <input type="radio" id="star<?php echo $i; ?>" 
                                           name="rating" value="<?php echo $i; ?>" required>
                                    <label for="star<?php echo $i; ?>">
                                        <i class="fas fa-star"></i>
                                    </label>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <div class="review-input">
                            <label>Your Review:</label>
                            <textarea name="comment" required rows="4" 
                                      placeholder="Share your experience..."></textarea>
                        </div>

                        <button type="submit" class="submit-btn">Submit Review</button
                    </form>
                </div>
            <?php endif; ?>

            <div class="reviews-list">
                <?php while ($review = $reviews->fetch_assoc()):
                    // Fetch comments for this review with error handling
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
                        $comments = [];
                    } else {
                        $comment_stmt->bind_param("i", $review['review_id']);
                        $comment_stmt->execute();
                        $comments = $comment_stmt->get_result();
                    }
                ?>
                    <div class="review-item">
                        <!-- Existing review content -->
                        <div class="review-header">
                            <div>
                                <strong>
                                    <?php echo htmlspecialchars($review['full_name']); ?>
                                    <?php if (isset($owner_id) && $review['user_id'] == $owner_id): ?>
                                        <span class="owner-badge">Gym Owner</span>
                                    <?php endif; ?>
                                </strong>
                                <div class="star-rating">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star <?php echo $i <= $review['rating'] ? 'checked' : ''; ?>"></i>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <div>
                                <span class="review-date">
                                    <?php echo date('M d, Y', strtotime($review['created_at'])); ?>
                                </span>
                                <?php if (canDeleteContent($review['user_id'], $gym)): ?>
                                    <form action="../actions/delete_review.php" method="POST" style="display: inline;"
                                        onsubmit="return confirm('Are you sure you want to delete this review?');">
                                        <input type="hidden" name="review_id" value="<?php echo $review['review_id']; ?>">
                                        <input type="hidden" name="gym_id" value="<?php echo $gym_id; ?>">
                                        <button type="submit" class="delete-btn">Delete</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        <p><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>

                        <!-- Comments section -->
                        <button class="reply-btn" onclick="toggleReplyForm(<?php echo $review['review_id']; ?>)">
                            Reply
                        </button>

                        <div id="reply-form-<?php echo $review['review_id']; ?>" class="comment-form" style="display: none;">
                            <?php if (isset($_SESSION['user_id'])): ?>
                                <form action="../actions/submit_comment.php" method="POST">
                                    <input type="hidden" name="review_id" value="<?php echo $review['review_id']; ?>">
                                    <input type="hidden" name="gym_id" value="<?php echo $gym_id; ?>">
                                    <textarea name="comment" required placeholder="Write your reply..." rows="2"></textarea>
                                    <button type="submit">Submit Reply</button>
                                </form>
                            <?php else: ?>
                                <p>Please <a href="../pages/login.php">login</a> to reply.</p>
                            <?php endif; ?>
                        </div>

                        <!-- Display existing comments with error handling -->
                        <div class="comment-section">
                            <?php 
                            // Fetch comments for this review
                            $comment_query = "SELECT c.*, 
                                             CONCAT(u.first_name, ' ', u.last_name) as full_name,
                                             u.username 
                                             FROM review_comments c 
                                             JOIN users u ON c.user_id = u.id 
                                             WHERE c.review_id = ? 
                                             ORDER BY c.created_at ASC";

                            $comment_stmt = $db_connection->prepare($comment_query);
                            $review_comments = [];

                            if ($comment_stmt) {
                                $comment_stmt->bind_param("i", $review['review_id']);
                                $comment_stmt->execute();
                                $review_comments = $comment_stmt->get_result();
                            }

                            if ($review_comments && $review_comments->num_rows > 0): 
                                while ($comment = $review_comments->fetch_assoc()): 
                            ?>
                                <div class="comment-item">
                                    <strong>
                                        <?php echo htmlspecialchars($comment['full_name']); ?>
                                        <?php if (isset($owner_id) && $comment['user_id'] == $owner_id): ?>
                                            <span class="owner-badge">Gym Owner</span>
                                        <?php endif; ?>
                                    </strong>
                                    <span class="review-date">
                                        <?php echo date('M d, Y', strtotime($comment['created_at'])); ?>
                                    </span>
                                    <?php if (canDeleteContent($comment['user_id'], $gym)): ?>
                                        <form action="../actions/delete_comment.php" method="POST" style="display: inline;"
                                            onsubmit="return confirm('Are you sure you want to delete this comment?');">
                                            <input type="hidden" name="comment_id" value="<?php echo $comment['comment_id']; ?>">
                                            <input type="hidden" name="gym_id" value="<?php echo $gym_id; ?>">
                                            <button type="submit" class="delete-btn">Delete</button>
                                        </form>
                                    <?php endif; ?>
                                    <p><?php echo nl2br(htmlspecialchars($comment['comment'])); ?></p>
                                </div>
                            <?php 
                                endwhile;
                            else: 
                            ?>
                                <p class="no-comments">No comments yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>

    <script src="../assets/js/gym_details.js"></script>
    <script>
        window.addEventListener('load', function() {
            document.querySelector('.page-loader').classList.add('hidden');
        });
    </script>
</body>
</html>