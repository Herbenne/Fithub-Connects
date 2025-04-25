<?php
session_start();
include '../config/database.php';

// Ensure user is superadmin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: login.php");
    exit();
}

$gym_id = $_GET['gym_id'] ?? null;

if (!$gym_id) {
    header("Location: all_gyms_analytics.php");
    exit();
}

// Fetch gym details
$gym_query = "SELECT g.*, 
    COALESCE(AVG(r.rating), 0) as avg_rating,
    COUNT(DISTINCT m.user_id) as member_count,
    COUNT(DISTINCT r.review_id) as review_count,
    SUM(p.price) as revenue
    FROM gyms g
    LEFT JOIN gym_reviews r ON g.gym_id = r.gym_id
    LEFT JOIN gym_members m ON g.gym_id = m.gym_id
    LEFT JOIN membership_plans p ON m.plan_id = p.plan_id
    WHERE g.gym_id = ? AND g.status = 'approved'
    GROUP BY g.gym_id";
$stmt = $db_connection->prepare($gym_query);
$stmt->bind_param("i", $gym_id);
$stmt->execute();
$gym = $stmt->get_result()->fetch_assoc();

if (!$gym) {
    header("Location: all_gyms_analytics.php");
    exit();
}

// Get monthly data for the gym
$monthly_query = "SELECT 
    DATE_FORMAT(m.start_date, '%Y-%m') as month,
    COUNT(DISTINCT m.user_id) as new_members,
    SUM(p.price) as revenue
    FROM gym_members m
    JOIN membership_plans p ON m.plan_id = p.plan_id
    WHERE m.gym_id = ?
    AND m.start_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY month
    ORDER BY month ASC";
$stmt = $db_connection->prepare($monthly_query);
$stmt->bind_param("i", $gym_id);
$stmt->execute();
$monthly_stats = $stmt->get_result();

// Get rating distribution
$rating_query = "SELECT 
    rating,
    COUNT(*) as count
    FROM gym_reviews
    WHERE gym_id = ?
    GROUP BY rating
    ORDER BY rating DESC";
$stmt = $db_connection->prepare($rating_query);
$stmt->bind_param("i", $gym_id);
$stmt->execute();
$rating_stats = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title><?php echo htmlspecialchars($gym['gym_name']); ?> Analytics - FitHub</title>
    <link rel="stylesheet" href="../assets/css/mains.css">
    <link rel="stylesheet" href="../assets/css/dashboards.css">
    <link rel="stylesheet" href="../assets/css/unified-theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="analytics-container">
        <!-- Navigation Header -->
        <nav class="analytics-nav">
            <a href="all_gyms_analytics.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to All Gyms
            </a>
            <h1><?php echo htmlspecialchars($gym['gym_name']); ?> Analytics</h1>
        </nav>

        <!-- Gym Overview Section -->
        <section class="gym-overview">
            <div class="gym-header">
                <?php if ($gym['gym_thumbnail']): ?>
                    <img src="<?php echo htmlspecialchars($gym['gym_thumbnail']); ?>" 
                         alt="<?php echo htmlspecialchars($gym['gym_name']); ?>"
                         class="gym-thumbnail">
                <?php endif; ?>
                <div class="gym-info">
                    <h2><?php echo htmlspecialchars($gym['gym_name']); ?></h2>
                    <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($gym['gym_location']); ?></p>
                </div>
            </div>

            <!-- Key Metrics -->
            <div class="metrics-grid">
                <div class="metric-card">
                    <i class="fas fa-users"></i>
                    <h3>Total Members</h3>
                    <p class="number"><?php echo number_format($gym['member_count']); ?></p>
                </div>
                <div class="metric-card">
                    <i class="fas fa-star"></i>
                    <h3>Average Rating</h3>
                    <p class="number"><?php echo number_format($gym['avg_rating'], 1); ?> ⭐</p>
                </div>
                <div class="metric-card">
                    <i class="fas fa-comment"></i>
                    <h3>Total Reviews</h3>
                    <p class="number"><?php echo number_format($gym['review_count']); ?></p>
                </div>
                <div class="metric-card">
                    <i class="fas fa-peso-sign"></i>
                    <h3>Total Revenue</h3>
                    <p class="number">₱<?php echo number_format($gym['revenue'], 2); ?></p>
                </div>
            </div>
        </section>

        <!-- Charts Section -->
        <section class="charts-section">
            <div class="chart-container">
                <h3>Member Growth (Last 12 Months)</h3>
                <canvas id="memberGrowthChart"></canvas>
            </div>
            
            <div class="chart-container">
                <h3>Monthly Revenue</h3>
                <canvas id="revenueChart"></canvas>
            </div>

            <div class="chart-container">
                <h3>Rating Distribution</h3>
                <canvas id="ratingChart"></canvas>
            </div>
        </section>
    </div>

    <style>
    .analytics-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
    }

    .analytics-nav {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        padding: 15px;
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        
    }

    .back-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    color: #000000;
    text-decoration: none;
    border: 2px solid #FFB22C;
    border-radius: 6px;
    font-weight: 500;
    transition: all 0.3s ease;
}

    .back-btn:hover {
    background: #FFB22C;
    transform: translateX(-5px);
}

    .gym-overview {
        background: white;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 30px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .gym-header {
        display: flex;
        align-items: center;
        margin-bottom: 20px;
    }

    .gym-thumbnail {
        width: 120px;
        height: 120px;
        object-fit: cover;
        border-radius: 10px;
        margin-right: 20px;
    }

    .metrics-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }

    .metric-card {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
        text-align: center;
    }

    .metric-card i {
        font-size: 24px;
        color: #4CAF50;
        margin-bottom: 10px;
    }

    .number {
        font-size: 24px;
        font-weight: bold;
        margin: 10px 0;
    }

    .charts-section {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
    }

    .chart-container {
        background: white;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .chart-container h3 {
        margin-bottom: 15px;
        text-align: center;
    }
    </style>

    <script>
    // Prepare chart data
    <?php
    $months = [];
    $members = [];
    $revenue = [];
    while ($row = $monthly_stats->fetch_assoc()) {
        $months[] = $row['month'];
        $members[] = $row['new_members'];
        $revenue[] = $row['revenue'];
    }

    $ratings = array_fill(1, 5, 0);
    while ($row = $rating_stats->fetch_assoc()) {
        $ratings[$row['rating']] = $row['count'];
    }
    ?>

    // Member Growth Chart
    new Chart(document.getElementById('memberGrowthChart'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode($months); ?>,
            datasets: [{
                label: 'New Members',
                data: <?php echo json_encode($members); ?>,
                borderColor: '#4CAF50',
                tension: 0.1,
                fill: false
            }]
        },
        options: {
            responsive: true,
            plugins: {
                title: {
                    display: true,
                    text: 'Monthly Member Growth'
                }
            }
        }
    });

    // Revenue Chart
    new Chart(document.getElementById('revenueChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($months); ?>,
            datasets: [{
                label: 'Monthly Revenue (₱)',
                data: <?php echo json_encode($revenue); ?>,
                backgroundColor: '#2196F3'
            }]
        },
        options: {
            responsive: true,
            plugins: {
                title: {
                    display: true,
                    text: 'Monthly Revenue'
                }
            }
        }
    });

    // Rating Distribution Chart
    new Chart(document.getElementById('ratingChart'), {
        type: 'pie',
        data: {
            labels: ['⭐', '⭐⭐', '⭐⭐⭐', '⭐⭐⭐⭐', '⭐⭐⭐⭐⭐'],
            datasets: [{
                data: <?php echo json_encode(array_values($ratings)); ?>,
                backgroundColor: [
                    '#ff9800',
                    '#ffc107',
                    '#ffeb3b',
                    '#cddc39',
                    '#8bc34a'
                ]
            }]
        },
        options: {
            responsive: true,
            plugins: {
                title: {
                    display: true,
                    text: 'Rating Distribution'
                }
            }
        }
    });
    </script>
</body>
</html>