<!-- filepath: c:\xampp\htdocs\Fithub-Connects\pages\all_gyms_analytics.php -->
<?php
session_start();
include '../config/database.php';

// Ensure user is superadmin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: login.php");
    exit();
}

// Get overall statistics
$overall_stats_query = "SELECT 
    COUNT(DISTINCT g.gym_id) as total_gyms,
    COUNT(DISTINCT m.user_id) as total_members,
    COUNT(DISTINCT r.review_id) as total_reviews,
    COALESCE(AVG(r.rating), 0) as overall_rating,
    SUM(p.price) as total_revenue
    FROM gyms g
    LEFT JOIN gym_members m ON g.gym_id = m.gym_id
    LEFT JOIN gym_reviews r ON g.gym_id = r.gym_id
    LEFT JOIN membership_plans p ON m.plan_id = p.plan_id
    WHERE g.status = 'approved'";
$overall_stats = $db_connection->query($overall_stats_query)->fetch_assoc();

// Get gym-specific data
$gyms_query = "SELECT g.*, 
    COALESCE(AVG(r.rating), 0) as avg_rating,
    COUNT(DISTINCT m.user_id) as member_count,
    COUNT(DISTINCT r.review_id) as review_count,
    SUM(p.price) as revenue
    FROM gyms g
    LEFT JOIN gym_reviews r ON g.gym_id = r.gym_id
    LEFT JOIN gym_members m ON g.gym_id = m.gym_id
    LEFT JOIN membership_plans p ON m.plan_id = p.plan_id
    WHERE g.status = 'approved'
    GROUP BY g.gym_id";
$gyms_result = $db_connection->query($gyms_query);
?>

<!DOCTYPE html>
<html>
<head>
    <title>All Gym Analytics - FitHub</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/mains.css">
    <link rel="stylesheet" href="../assets/css/analytics.css">
    <link rel="stylesheet" href="../assets/css/unified-theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <div class="analytics-container">
        <nav class="analytics-nav">
            <a href="dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
            <h1><i class="fas fa-chart-line"></i> FitHub Analytics Dashboard</h1>
        </nav>

        <!-- Overall Statistics Section -->
        <section class="summary-section">
            <h2><i class="fas fa-info-circle"></i> Platform Overview</h2>
            <div class="stats-overview">
                <div class="stat-card">
                    <i class="fas fa-dumbbell"></i>
                    <h3>Total Gyms</h3>
                    <p class="stat-number"><?php echo number_format($overall_stats['total_gyms']); ?></p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-users"></i>
                    <h3>Total Members</h3>
                    <p class="stat-number"><?php echo number_format($overall_stats['total_members']); ?></p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-star"></i>
                    <h3>Average Rating</h3>
                    <p class="stat-number"><?php echo number_format($overall_stats['overall_rating'], 1); ?> ⭐</p>
                </div>
                <div class="stat-card highlight">
                    <i class="fas fa-peso-sign"></i>
                    <h3>Total Revenue</h3>
                    <p class="stat-number">₱<?php echo number_format($overall_stats['total_revenue'], 2); ?></p>
                </div>
            </div>
        </section>

        <!-- Gym Cards Section -->
        <section class="gym-cards-section">
            <h2><i class="fas fa-building"></i> Registered Gyms</h2>
            <div class="gym-cards-grid">
                <?php while ($gym = $gyms_result->fetch_assoc()): ?>
                    <div class="gym-card" onclick="window.location='gym_detailed_analytics.php?gym_id=<?php echo $gym['gym_id']; ?>'">
                        <div class="gym-card-header">
                            <?php if ($gym['gym_thumbnail']): ?>
                                <img src="<?php echo htmlspecialchars($gym['gym_thumbnail']); ?>" alt="<?php echo htmlspecialchars($gym['gym_name']); ?>">
                            <?php endif; ?>
                            <h3><?php echo htmlspecialchars($gym['gym_name']); ?></h3>
                            <p class="location"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($gym['gym_location']); ?></p>
                        </div>
                        <div class="gym-card-stats">
                            <div class="stat">
                                <i class="fas fa-users"></i>
                                <span><?php echo number_format($gym['member_count']); ?></span>
                                <label>Members</label>
                            </div>
                            <div class="stat">
                                <i class="fas fa-star"></i>
                                <span><?php echo number_format($gym['avg_rating'], 1); ?></span>
                                <label>Rating</label>
                            </div>
                            <div class="stat">
                                <i class="fas fa-peso-sign"></i>
                                <span><?php echo number_format($gym['revenue']); ?></span>
                                <label>Revenue</label>
                            </div>
                        </div>
                        <div class="view-analytics">
                            <span>Click to View Analytics <i class="fas fa-arrow-right"></i></span>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </section>
    </div>

    <style>
    .gym-cards-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
        padding: 20px 0;
    }

    .gym-card {
        background: white;
        border-radius: 10px;
        padding: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        cursor: pointer;
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .gym-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    }

    .gym-card-header img {
        width: 100%;
        height: 150px;
        object-fit: cover;
        border-radius: 8px;
        margin-bottom: 15px;
    }

    .gym-card-stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 15px;
        margin: 15px 0;
        padding: 15px 0;
        border-top: 1px solid #eee;
        border-bottom: 1px solid #eee;
    }

    .stat {
        text-align: center;
    }

    .stat i {
        color: #4CAF50;
        margin-bottom: 5px;
    }

    .stat span {
        display: block;
        font-size: 18px;
        font-weight: bold;
    }

    .stat label {
        font-size: 12px;
        color: #666;
    }

    .view-analytics {
        text-align: center;
        padding: 10px;
        color: #4CAF50;
        font-weight: bold;
    }

    .view-analytics i {
        margin-left: 5px;
    }
    </style>
</body>
</html>