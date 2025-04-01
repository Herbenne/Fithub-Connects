<?php
session_start();
include '../config/database.php';

// Ensure user is logged in and is a regular user/member
if (!isset($_SESSION['user_id']) || $_SESSION['role'] === 'admin' || $_SESSION['role'] === 'superadmin') {
    header("Location: login.php");
    exit();
}

// Get gym_id from URL
$gym_id = $_GET['gym_id'] ?? null;

if (!$gym_id) {
    header("Location: explore_gyms.php");
    exit();
}

// Fetch gym details with ratings
$query = "SELECT g.*, 
          COALESCE(AVG(gr.rating), 0) as avg_rating,
          COUNT(DISTINCT gr.review_id) as review_count,
          CONCAT(u.first_name, ' ', u.last_name) as owner_name
          FROM gyms g 
          LEFT JOIN gym_reviews gr ON g.gym_id = gr.gym_id
          LEFT JOIN users u ON g.owner_id = u.id
          WHERE g.gym_id = ? AND g.status = 'approved'
          GROUP BY g.gym_id";

$stmt = $db_connection->prepare($query);
$stmt->bind_param("i", $gym_id);
$stmt->execute();
$gym = $stmt->get_result()->fetch_assoc();

if (!$gym) {
    header("Location: explore_gyms.php?error=gym_not_found");
    exit();
}

// Add this after the initial gym query
$avg_rating = $gym['avg_rating'] ?? 0;

// Fetch membership plans
$plans_query = "SELECT * FROM membership_plans WHERE gym_id = ? ORDER BY price ASC";
$stmt = $db_connection->prepare($plans_query);
$stmt->bind_param("i", $gym_id);
$stmt->execute();
$plans = $stmt->get_result();

// Fetch equipment images
$equipment_images = json_decode($gym['equipment_images'] ?? '[]', true);

// Fetch reviews with user details
$reviews_query = "SELECT r.*, 
                  CONCAT(u.first_name, ' ', u.last_name) as full_name,
                  u.username
                  FROM gym_reviews r
                  JOIN users u ON r.user_id = u.id
                  WHERE r.gym_id = ?
                  ORDER BY r.created_at DESC";
$stmt = $db_connection->prepare($reviews_query);
$stmt->bind_param("i", $gym_id);
$stmt->execute();
$reviews = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head></head>
    <title><?php echo htmlspecialchars($gym['gym_name']); ?> - GymHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/mains.css">
    <link rel="stylesheet" href="../assets/css/user_view_gyms.css">
    <!-- Add page loader styles -->
    <!-- <style>
        .page-loader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: #fff;
            z-index: 9999;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .page-loader.hidden {
            display: none;
        }
    </style> -->    
</head>
<body>
    <div class="page-container">
        <!-- Navigation Bar -->
        <nav>    
           <div class="back-to-explore-button-contianer">
           <a href="explore_gyms.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Explore
            </a>
           </div>
            <div class="nav-buttons">
                <?php if ($_SESSION['role'] === 'member'): ?>
                    <a href="dashboard.php" class="nav-btn">Dashboard</a>
                <?php endif; ?>
            </div>
        </nav>

        <!-- Main Content -->
        <div class="gym-details">
            <!-- Hero Section -->
            <div class="gym-hero">
                <img src="../uploads/<?php echo htmlspecialchars($gym['gym_thumbnail']); ?>" 
                     alt="<?php echo htmlspecialchars($gym['gym_name']); ?>"
                     class="gym-hero-image"
                     onload="this.style.opacity='1'"
                     onerror="this.onerror=null; this.src='../assets/images/default-gym.jpg';">
                <div class="gym-hero-overlay">
                    <h1><?php echo htmlspecialchars($gym['gym_name']); ?></h1>
                    <div class="gym-rating">
                        <div class="stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <?php if ($i <= $gym['avg_rating']): ?>
                                    <i class="fas fa-star"></i>
                                <?php elseif ($i - $gym['avg_rating'] < 1): ?>
                                    <i class="fas fa-star-half-alt"></i>
                                <?php else: ?>
                                    <i class="far fa-star"></i>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </div>
                        <span><?php echo number_format($gym['avg_rating'], 1); ?> 
                              (<?php echo $gym['review_count']; ?> reviews)</span>
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

                <div class="info-card">
                    <h3><i class="fas fa-dumbbell"></i> Amenities</h3>
                    <p><?php echo nl2br(htmlspecialchars($gym['gym_amenities'])); ?></p>
                </div>

                <div class="info-card full-width">
                    <h3><i class="fas fa-info-circle"></i> About This Gym</h3>
                    <p><?php echo nl2br(htmlspecialchars($gym['gym_description'])); ?></p>
                </div>
            </div>

            <!-- Membership Plans -->   
            <div class="membership-plans">
                <h2>Membership Plans</h2>
                <div class="plans-grid">
                    <?php if ($plans->num_rows > 0): 
                        while ($plan = $plans->fetch_assoc()): ?>
                            <div class="plan-card">
                                <h3 class="plan-name"><?php echo htmlspecialchars($plan['plan_name']); ?></h3>
                                <div class="plan-duration"><?php echo htmlspecialchars($plan['duration']); ?></div>
                                <div class="plan-price">₱<?php echo number_format($plan['price'], 2); ?></div>
                                <p class="plan-description"><?php echo nl2br(htmlspecialchars($plan['description'])); ?></p>
                                <!-- Add the form for payment processing -->
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
            <!-- Equipment Gallery -->
            <section class="equipment-section">
                <h2>Our Equipment</h2>
                <div class="equipment-grid">
                    <?php foreach ($equipment_images as $image): ?>
                        <div class="equipment-item">
                            <img src="../uploads/<?php echo htmlspecialchars($image); ?>" 
                                 alt="Gym Equipment"
                                 onload="this.style.opacity='1'"
                                 onclick="showLightbox(this.src)"
                                 onerror="this.onerror=null; this.src='../assets/images/default-equipment.jpg';">
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <!-- Reviews Section -->
            <section class="reviews-section">
                <h2>Reviews & Ratings</h2>
                
                <?php if (isset($_SESSION['user_id'])): ?>
                    <button class="write-review-btn" onclick="toggleReviewForm()">
                        <i class="fas fa-pen"></i> Write a Review
                    </button>

                    <div id="review-form" class="review-form" style="display: none;">
                        <form action="../actions/submit_review.php" method="POST">
                            <input type="hidden" name="gym_id" value="<?php echo $gym_id; ?>">
                            <div class="rating-select">
                                <label>Your Rating</label>
                                <div class="star-rating-input">
                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                        <input type="radio" id="star<?php echo $i; ?>" 
                                               name="rating" value="<?php echo $i; ?>" required>
                                        <label for="star<?php echo $i; ?>">
                                            <i class="fas fa-star"></i>
                                        </label>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <div class="review-input">
                                <textarea name="comment" required rows="4" 
                                          placeholder="Share your experience..."></textarea>
                            </div>
                            <button type="submit" class="submit-btn">Submit Review</button>
                        </form>
                    </div>
                <?php endif; ?>

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
                                    <?php if ($_SESSION['user_id'] == $review['user_id']): ?>
                                        <form action="../actions/delete_review.php" method="POST" style="display: inline;"
                                            onsubmit="return confirm('Are you sure you want to delete this review?');">
                                            <input type="hidden" name="review_id" value="<?php echo $review['review_id']; ?>">
                                            <input type="hidden" name="gym_id" value="<?php echo $gym_id; ?>">
                                            <button type="submit" class="delete-btn">Delete</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <p class="review-content"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                            
                            <!-- Reply Button -->
                            <?php if (isset($_SESSION['user_id'])): ?>
                                <button class="reply-btn" onclick="toggleReplyForm(<?php echo $review['review_id']; ?>)">
                                    <i class="fas fa-reply"></i> Reply
                                </button>

                                <!-- Reply Form -->
                                <div id="reply-form-<?php echo $review['review_id']; ?>" class="comment-form" style="display: none;">
                                    <form action="../actions/submit_comment.php" method="POST">
                                        <input type="hidden" name="review_id" value="<?php echo $review['review_id']; ?>">
                                        <input type="hidden" name="gym_id" value="<?php echo $gym_id; ?>">
                                        <textarea name="comment" required placeholder="Write your reply..."></textarea>
                                        <button type="submit" class="submit-btn">Submit Reply</button>
                                    </form>
                                </div>
                            <?php endif; ?>

                            <!-- Comments Section -->
                            <div class="comments-section">
                                <?php
                                $comments_query = "SELECT c.*, CONCAT(u.first_name, ' ', u.last_name) as commenter_name 
                                                 FROM review_comments c 
                                                 JOIN users u ON c.user_id = u.id 
                                                 WHERE c.review_id = ? 
                                                 ORDER BY c.created_at ASC";
                                $comments_stmt = $db_connection->prepare($comments_query);
                                $comments_stmt->bind_param("i", $review['review_id']);
                                $comments_stmt->execute();
                                $comments = $comments_stmt->get_result();

                                while ($comment = $comments->fetch_assoc()): ?>
                                    <div class="comment-item">
                                        <div class="comment-header">
                                            <strong><?php echo htmlspecialchars($comment['commenter_name']); ?></strong>
                                            <span class="comment-date">
                                                <?php echo date('M d, Y', strtotime($comment['created_at'])); ?>
                                            </span>
                                        </div>
                                        <p><?php echo nl2br(htmlspecialchars($comment['comment'])); ?></p>
                                        <?php if ($_SESSION['user_id'] == $comment['user_id']): ?>
                                            <form action="../actions/delete_comment.php" method="POST" class="delete-comment-form">
                                                <input type="hidden" name="comment_id" value="<?php echo $comment['comment_id']; ?>">
                                                <input type="hidden" name="gym_id" value="<?php echo $gym_id; ?>">
                                                <button type="submit" class="delete-btn" onclick="return confirm('Delete this comment?');">Delete</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </section>
<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-error">
        <?php
        $error = $_GET['error'];
        switch($error) {
            case 'missing_fields':
                echo "Please fill in all fields.";
                break;
            case 'already_reviewed':
                echo "You have already reviewed this gym.";
                break;
            case 'db_error':
                echo "An error occurred. Please try again.";
                break;
            default:
                echo "An unknown error occurred.";
        }
        ?>
    </div>
<?php endif; ?>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">
        <?php
        $success = $_GET['success'];
        switch($success) {
            case 'review_submitted':
                echo "Your review has been submitted successfully.";
                break;
            case 'review_deleted':
                echo "Review deleted successfully.";
                break;
            default:
                echo "Operation completed successfully.";
        }
        ?>
    </div>
<?php endif; ?>
        </div>
    </div>

    <script>
    function toggleReviewForm() {
        const form = document.getElementById('review-form');
        form.style.display = form.style.display === 'none' ? 'block' : 'none';
    }

    function showLightbox(src) {
        const lightbox = document.createElement('div');
        lightbox.className = 'lightbox';
        lightbox.innerHTML = `
            <div class="lightbox-content">
                <img src="${src}" alt="Equipment">
                <button class="close-lightbox">&times;</button>
            </div>
        `;
        document.body.appendChild(lightbox);
        document.body.style.overflow = 'hidden';

        lightbox.addEventListener('click', function(e) {
            if (e.target === lightbox || e.target.className === 'close-lightbox') {
                lightbox.remove();
                document.body.style.overflow = '';
            }
        });
    }
    </script>
    <script src="../assets/js/user_view_gym.js"></script>
</body>
</html>