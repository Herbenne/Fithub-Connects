<?php
session_start();
include '../config/database.php';

// Ensure user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$gym_id = $_GET['gym_id'] ?? null;

// Verify this gym belongs to the admin
$verify_query = "SELECT * FROM gyms WHERE gym_id = ? AND owner_id = ?";
$stmt = $db_connection->prepare($verify_query);
$stmt->bind_param("ii", $gym_id, $_SESSION['user_id']);
$stmt->execute();
$gym = $stmt->get_result()->fetch_assoc();

if (!$gym) {
    header("Location: dashboard.php");
    exit();
}

// Get monthly member signups for the past 12 months
$member_query = "SELECT 
                    DATE_FORMAT(start_date, '%Y-%m') as month,
                    COUNT(*) as new_members
                FROM gym_members 
                WHERE gym_id = ? 
                AND start_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                GROUP BY month
                ORDER BY month ASC";
$stmt = $db_connection->prepare($member_query);
$stmt->bind_param("i", $gym_id);
$stmt->execute();
$member_stats = $stmt->get_result();

// Get monthly revenue data
$revenue_query = "SELECT 
                    DATE_FORMAT(m.start_date, '%Y-%m') as month,
                    SUM(p.price) as revenue
                FROM gym_members m
                JOIN membership_plans p ON m.plan_id = p.plan_id
                WHERE m.gym_id = ?
                AND m.start_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                GROUP BY month
                ORDER BY month ASC";
$stmt = $db_connection->prepare($revenue_query);
$stmt->bind_param("i", $gym_id);
$stmt->execute();
$revenue_stats = $stmt->get_result();

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
    <title>Gym Analytics - GymHub</title>
    <link rel="stylesheet" href="../assets/css/mains.css">
    <link rel="stylesheet" href="../assets/css/analytics.css">
    <link rel="stylesheet" href="../assets/css/unified-theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="dashboard-container">
        <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
        <h2>Analytics for <?php echo htmlspecialchars($gym['gym_name']); ?></h2>

        <div class="analytics-grid">
            <div class="chart-container">
                <h3>Member Growth</h3>
                <canvas id="memberChart"></canvas>
            </div>

            <div class="chart-container">
                <h3>Monthly Revenue</h3>
                <canvas id="revenueChart"></canvas>
            </div>

            <div class="chart-container">
                <h3>Rating Distribution</h3>
                <canvas id="ratingChart"></canvas>
            </div>
        </div>
    </div>

    <script>
    // Prepare data for member growth chart
    const memberData = {
        labels: <?php 
            $labels = [];
            $data = [];
            $member_stats->data_seek(0);
            while ($row = $member_stats->fetch_assoc()) {
                $labels[] = date('M Y', strtotime($row['month']));
                $data[] = $row['new_members'];
            }
            echo json_encode($labels);
        ?>,
        datasets: [{
            label: 'New Members',
            data: <?php echo json_encode($data); ?>,
            backgroundColor: 'rgba(54, 162, 235, 0.2)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1
        }]
    };

    // Prepare data for revenue chart
    const revenueData = {
        labels: <?php 
            $labels = [];
            $data = [];
            $revenue_stats->data_seek(0);
            while ($row = $revenue_stats->fetch_assoc()) {
                $labels[] = date('M Y', strtotime($row['month']));
                $data[] = $row['revenue'];
            }
            echo json_encode($labels);
        ?>,
        datasets: [{
            label: 'Revenue (₱)',
            data: <?php echo json_encode($data); ?>,
            backgroundColor: 'rgba(75, 192, 192, 0.2)',
            borderColor: 'rgba(75, 192, 192, 1)',
            borderWidth: 1
        }]
    };

    // Prepare data for rating distribution
    const ratingData = {
        labels: <?php 
            $labels = [];
            $data = [];
            $rating_stats->data_seek(0);
            while ($row = $rating_stats->fetch_assoc()) {
                $labels[] = $row['rating'] . ' Stars';
                $data[] = $row['count'];
            }
            echo json_encode($labels);
        ?>,
        datasets: [{
            label: 'Number of Reviews',
            data: <?php echo json_encode($data); ?>,
            backgroundColor: [
                'rgba(255, 99, 132, 0.2)',
                'rgba(255, 159, 64, 0.2)',
                'rgba(255, 205, 86, 0.2)',
                'rgba(75, 192, 192, 0.2)',
                'rgba(54, 162, 235, 0.2)'
            ],
            borderColor: [
                'rgb(255, 99, 132)',
                'rgb(255, 159, 64)',
                'rgb(255, 205, 86)',
                'rgb(75, 192, 192)',
                'rgb(54, 162, 235)'
            ],
            borderWidth: 1
        }]
    };

    // Create charts
    new Chart(document.getElementById('memberChart'), {
        type: 'line',
        data: memberData,
        options: {
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            },
            responsive: true,
            maintainAspectRatio: false
        }
    });

    new Chart(document.getElementById('revenueChart'), {
        type: 'bar',
        data: revenueData,
        options: {
            scales: {
                y: {
                    beginAtZero: true
                }
            },
            responsive: true,
            maintainAspectRatio: false
        }
    });

    new Chart(document.getElementById('ratingChart'), {
        type: 'doughnut',
        data: ratingData,
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
    </script>
</body>
</html>