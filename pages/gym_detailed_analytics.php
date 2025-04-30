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

// Get member counts by status
$member_count_query = "SELECT 
    SUM(CASE WHEN m.end_date >= CURDATE() THEN 1 ELSE 0 END) as active_count,
    SUM(CASE WHEN m.end_date < CURDATE() THEN 1 ELSE 0 END) as inactive_count
    FROM gym_members m
    WHERE m.gym_id = ?";
    
$stmt = $db_connection->prepare($member_count_query);
$stmt->bind_param("i", $gym_id);
$stmt->execute();
$member_counts = $stmt->get_result()->fetch_assoc();

// Get detailed member list
$members_list_query = "SELECT 
    u.id as user_id, 
    CONCAT(u.first_name, ' ', u.last_name) as member_name,
    u.reg_date,
    m.start_date,
    m.end_date,
    p.plan_name,
    p.price,
    CASE 
        WHEN m.end_date >= CURDATE() THEN 'Active' 
        ELSE 'Inactive' 
    END as status
    FROM gym_members m
    JOIN users u ON m.user_id = u.id
    JOIN membership_plans p ON m.plan_id = p.plan_id
    WHERE m.gym_id = ?
    ORDER BY status ASC, m.start_date DESC";

$stmt = $db_connection->prepare($members_list_query);
$stmt->bind_param("i", $gym_id);
$stmt->execute();
$members_list = $stmt->get_result();

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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    
    <script>
    // Prepare chart data variables
    <?php
    // Convert PHP data to JavaScript variables
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
    
    // Convert to JSON for JavaScript
    $months_json = json_encode($months);
    $members_json = json_encode($members);
    $revenue_json = json_encode($revenue);
    $ratings_json = json_encode(array_values($ratings));
    ?>

    // Global variables for charts
    window.chartMonths = <?php echo $months_json; ?>;
    window.chartMembers = <?php echo $members_json; ?>;
    window.chartRevenue = <?php echo $revenue_json; ?>;
    window.chartRatings = <?php echo $ratings_json; ?>;
    </script>
    
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
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 20px;
    }

    .gym-info-container {
        display: flex;
        align-items: center;
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
        margin-bottom: 30px;
    }

    .chart-container {
        background: white;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        min-height: 300px;
    }

    .chart-container h3 {
        margin-bottom: 15px;
        text-align: center;
    }

    .report-actions {
        display: flex;
        gap: 15px;
        align-items: center;
    }

    .pdf-download-btn {
        display: flex;
        align-items: center;
        gap: 8px;
        background-color: #f44336;
        color: white;
        border: none;
        padding: 10px 16px;
        border-radius: 4px;
        cursor: pointer;
        font-weight: 500;
        transition: background-color 0.3s;
    }

    .pdf-download-btn:hover {
        background-color: #d32f2f;
    }

    .filter-dropdown {
        position: relative;
        display: inline-block;
    }

    .filter-btn {
        display: flex;
        align-items: center;
        gap: 8px;
        background-color: #4CAF50;
        color: white;
        border: none;
        padding: 10px 16px;
        border-radius: 4px;
        cursor: pointer;
        font-weight: 500;
        transition: background-color 0.3s;
    }

    .filter-btn:hover {
        background-color: #3d8b40;
    }

    .filter-menu {
        display: none;
        position: absolute;
        right: 0;
        background-color: white;
        min-width: 200px;
        box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
        padding: 12px;
        z-index: 1;
        border-radius: 4px;
    }

    .filter-dropdown:hover .filter-menu {
        display: block;
    }

    .filter-item {
        padding: 8px 0;
        display: flex;
        align-items: center;
    }

    .filter-item input {
        margin-right: 10px;
    }

    /* Members section styles */
    .members-section {
        background: white;
        border-radius: 10px;
        padding: 25px;
        margin-top: 30px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    .section-title {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 1.8rem;
        color: #333;
        margin-top: 0;
        margin-bottom: 25px;
        border-bottom: 2px solid #f0f0f0;
        padding-bottom: 15px;
    }
    
    .section-title i {
        color: #4CAF50;
    }
    
    .member-stats-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 25px;
    }
    
    .member-stat-card {
        display: flex;
        align-items: center;
        padding: 25px;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        transition: transform 0.3s, box-shadow 0.3s;
    }
    
    .member-stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    
    .member-stat-card.active {
        background-color: rgba(76, 175, 80, 0.1);
        border-left: 5px solid #4CAF50;
    }
    
    .member-stat-card.inactive {
        background-color: rgba(244, 67, 54, 0.1);
        border-left: 5px solid #F44336;
    }
    
    .member-stat-card.total {
        background-color: rgba(33, 150, 243, 0.1);
        border-left: 5px solid #2196F3;
    }
    
    .stat-icon {
        font-size: 28px;
        margin-right: 20px;
    }
    
    .member-stat-card.active .stat-icon {
        color: #4CAF50;
    }
    
    .member-stat-card.inactive .stat-icon {
        color: #F44336;
    }
    
    .member-stat-card.total .stat-icon {
        color: #2196F3;
    }
    
    .stat-info {
        display: flex;
        flex-direction: column;
    }
    
    .stat-label {
        font-size: 16px;
        color: #666;
    }
    
    .stat-value {
        font-size: 28px;
        font-weight: bold;
        color: #333;
    }
    
    .filter-panel {
        background: #f8f9fa;
        border-radius: 10px;
        margin-bottom: 25px;
        overflow: hidden;
        border: 1px solid #eee;
    }
    
    .filter-title {
        margin: 0;
        padding: 15px 20px;
        background: #f1f3f5;
        border-bottom: 1px solid #eee;
        font-size: 16px;
        color: #555;
    }
    
    .filter-options {
        padding: 20px;
    }
    
    .members-table-wrapper {
        border: 1px solid #eee;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    
    .data-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .data-table th {
        background: #f8f9fa;
        padding: 15px;
        text-align: left;
        font-weight: 600;
        color: #444;
        border-bottom: 2px solid #eee;
    }
    
    .data-table td {
        padding: 15px;
        border-bottom: 1px solid #eee;
    }
    
    .data-table tr:hover {
        background: #f9f9f9;
    }
    
    .data-table tr:last-child td {
        border-bottom: none;
    }
    
    .status-badge {
        display: inline-block;
        padding: 6px 12px;
        border-radius: 50px;
        font-size: 13px;
        font-weight: 500;
    }
    
    .status-badge.active {
        background-color: rgba(76, 175, 80, 0.1);
        color: #4CAF50;
        border: 1px solid rgba(76, 175, 80, 0.2);
    }
    
    .status-badge.inactive {
        background-color: rgba(244, 67, 54, 0.1);
        color: #F44336;
        border: 1px solid rgba(244, 67, 54, 0.2);
    }
    
    .no-data {
        text-align: center;
        padding: 30px;
        color: #666;
        font-style: italic;
    }

    /* Fix for hidden sections and chart display */
    .members-section[data-hidden="true"],
    .all-members-section[data-hidden="true"],
    .chart-container[data-hidden="true"],
    .metric-card[data-hidden="true"],
    .stat-card[data-hidden="true"],
    .gym-card[data-hidden="true"] {
        display: none !important;
    }

    /* Chart error styling */
    .chart-error {
        padding: 20px;
        background-color: #fff3cd;
        border: 1px solid #ffeeba;
        color: #856404;
        border-radius: 5px;
        text-align: center;
        margin: 20px 0;
    }

    /* Loading overlay for PDF generation */
    .loading-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.7);
        z-index: 9999;
        display: flex;
        justify-content: center;
        align-items: center;
        flex-direction: column;
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

    .loading-text {
        color: white;
        font-size: 18px;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    </style>
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
                <div class="gym-info-container">
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
                <div class="report-actions">
                    <div class="filter-dropdown">
                        <button class="filter-btn"><i class="fas fa-filter"></i> Filter Report</button>
                        <div class="filter-menu">
                            <div class="filter-item">
                                <input type="checkbox" id="include-members" checked>
                                <label for="include-members">Members</label>
                            </div>
                            <div class="filter-item">
                                <input type="checkbox" id="include-ratings" checked>
                                <label for="include-ratings">Ratings</label>
                            </div>
                            <div class="filter-item">
                                <input type="checkbox" id="include-reviews" checked>
                                <label for="include-reviews">Reviews</label>
                            </div>
                            <div class="filter-item">
                                <input type="checkbox" id="include-revenue" checked>
                                <label for="include-revenue">Revenue</label>
                            </div>
                            <div class="filter-item">
                                <input type="checkbox" id="include-charts" checked>
                                <label for="include-charts">Charts & Graphs</label>
                            </div>
                        </div>
                    </div>
                    <button id="downloadPdfBtn" class="pdf-download-btn">
                        <i class="fas fa-file-pdf"></i> Export as PDF
                    </button>
                </div>
            </div>

            <!-- Key Metrics -->
            <div class="metrics-grid">
                <div class="metric-card" data-type="members">
                    <i class="fas fa-users"></i>
                    <h3>Total Members</h3>
                    <p class="number"><?php echo number_format($gym['member_count']); ?></p>
                </div>
                <div class="metric-card" data-type="ratings">
                    <i class="fas fa-star"></i>
                    <h3>Average Rating</h3>
                    <p class="number"><?php echo number_format($gym['avg_rating'], 1); ?> ⭐</p>
                </div>
                <div class="metric-card" data-type="reviews">
                    <i class="fas fa-comment"></i>
                    <h3>Total Reviews</h3>
                    <p class="number"><?php echo number_format($gym['review_count']); ?></p>
                </div>
                <div class="metric-card" data-type="revenue">
                    <i class="fas fa-peso-sign"></i>
                    <h3>Total Revenue</h3>
                    <p class="number">₱<?php echo number_format($gym['revenue'], 2); ?></p>
                </div>
            </div>
        </section>

        <!-- Charts Section -->
        <section class="charts-section">
            <div class="chart-container" data-type="members">
                <h3>Member Growth (Last 12 Months)</h3>
                <canvas id="memberGrowthChart"></canvas>
            </div>
            
            <div class="chart-container" data-type="revenue">
                <h3>Monthly Revenue</h3>
                <canvas id="revenueChart"></canvas>
            </div>

            <div class="chart-container" data-type="ratings">
                <h3>Rating Distribution</h3>
                <canvas id="ratingChart"></canvas>
            </div>
        </section>

        <!-- Members Section -->
        <section class="members-section" data-type="members">
            <h2 class="section-title"><i class="fas fa-users"></i> Platform Members</h2>
            
            <div class="member-stats-cards">
                <div class="member-stat-card active">
                    <div class="stat-icon"><i class="fas fa-user-check"></i></div>
                    <div class="stat-info">
                        <span class="stat-label">Active Members</span>
                        <span class="stat-value"><?php echo number_format($member_counts['active_count']); ?></span>
                    </div>
                </div>
                
                <div class="member-stat-card inactive">
                    <div class="stat-icon"><i class="fas fa-user-times"></i></div>
                    <div class="stat-info">
                        <span class="stat-label">Inactive Members</span>
                        <span class="stat-value"><?php echo number_format($member_counts['inactive_count']); ?></span>
                    </div>
                </div>
                
                <div class="member-stat-card total">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-info">
                        <span class="stat-label">Total Members</span>
                        <span class="stat-value"><?php echo number_format($member_counts['active_count'] + $member_counts['inactive_count']); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="filter-panel">
                <h3 class="filter-title">Filter Members</h3>
                
                <div class="filter-options">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label>Member Status:</label>
                            <div class="checkbox-group">
                                <div class="checkbox-item">
                                    <input type="checkbox" id="filter-active-members" checked>
                                    <label for="filter-active-members">Active Members</label>
                                </div>
                                <div class="checkbox-item">
                                    <input type="checkbox" id="filter-inactive-members" checked>
                                    <label for="filter-inactive-members">Inactive Members</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="filter-group">
                            <label for="start-date-filter">Start Date:</label>
                            <input type="date" id="start-date-filter" class="date-input">
                        </div>
                        
                        <div class="filter-group">
                            <label for="end-date-filter">End Date:</label>
                            <input type="date" id="end-date-filter" class="date-input">
                        </div>
                        
                        <button id="reset-filters" class="reset-filter-btn">
                            <i class="fas fa-sync-alt"></i> Reset Filters
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="members-table-wrapper">
                <table id="membersTable" class="data-table">
                    <thead>
                        <tr>
                            <th>Member Name</th>
                            <th>Registration Date</th>
                            <th>Membership Plan</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($members_list->num_rows > 0): ?>
                            <?php while($member = $members_list->fetch_assoc()): ?>
                                <tr class="member-row <?php echo strtolower($member['status']); ?>" 
                                    data-reg-date="<?php echo $member['reg_date']; ?>"
                                    data-start-date="<?php echo $member['start_date']; ?>"
                                    data-end-date="<?php echo $member['end_date']; ?>"
                                    data-status="<?php echo strtolower($member['status']); ?>">
                                    <td><?php echo htmlspecialchars($member['member_name']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($member['reg_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($member['plan_name']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($member['start_date'])); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($member['end_date'])); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo strtolower($member['status']); ?>">
                                            <?php echo $member['status']; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="no-data">No members found for this gym.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <script>
    window.chartsInitialized = false;

    document.addEventListener('DOMContentLoaded', function() {
        console.log("DOM loaded, initializing page");

        // Initialize charts first
        initializeCharts();
        
        // Then other functionality
        initializeFilters();
        initializeMemberFiltering();
        
        // Set up PDF export button
        const downloadPdfBtn = document.getElementById('downloadPdfBtn');
        if (downloadPdfBtn) {
            // Remove any existing handlers
            downloadPdfBtn.replaceWith(downloadPdfBtn.cloneNode(true));
            
            // Add the event listener to the fresh button
            document.getElementById('downloadPdfBtn').addEventListener('click', generatePDF);
        }
    });

    function initializeCharts() {
    // Skip if already initialized
    if (window.chartsInitialized) {
        console.log("Charts already initialized, skipping");
        return;
    }
    
    console.log("Starting chart initialization");
    
    try {
        // Check if Chart.js is properly loaded
        if (typeof Chart === 'undefined') {
            console.error("Chart.js not loaded!");
            return;
        }
        
        // Verify data is available
        if (!window.chartMonths || !window.chartMembers || !window.chartRevenue || !window.chartRatings) {
            console.error("Chart data not available:", { 
                months: window.chartMonths, 
                members: window.chartMembers,
                revenue: window.chartRevenue,
                ratings: window.chartRatings
            });
            return;
        }
        
        // Destroy any existing charts first
        const canvases = ['memberGrowthChart', 'revenueChart', 'ratingChart'];
        canvases.forEach(id => {
            const canvas = document.getElementById(id);
            if (canvas && canvas.chart instanceof Chart) {
                canvas.chart.destroy();
            }
        });
        
        // Member Growth Chart
        const memberGrowthCanvas = document.getElementById('memberGrowthChart');
        if (memberGrowthCanvas) {
            console.log("Creating member growth chart");
            memberGrowthCanvas.chart = new Chart(memberGrowthCanvas, {
                type: 'line',
                data: {
                    labels: window.chartMonths.map(month => {
                        try {
                            return new Date(month + "-01").toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
                        } catch(e) {
                            return month;
                        }
                    }),
                    datasets: [{
                        label: 'New Members',
                        data: window.chartMembers,
                        backgroundColor: 'rgba(76, 175, 80, 0.2)',
                        borderColor: '#4CAF50',
                        borderWidth: 2,
                        tension: 0.1,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        }

        // Revenue Chart
        const revenueChartCanvas = document.getElementById('revenueChart');
        if (revenueChartCanvas) {
            console.log("Creating revenue chart");
            revenueChartCanvas.chart = new Chart(revenueChartCanvas, {
                type: 'bar',
                data: {
                    labels: window.chartMonths.map(month => {
                        try {
                            return new Date(month + "-01").toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
                        } catch(e) {
                            return month;
                        }
                    }),
                    datasets: [{
                        label: 'Monthly Revenue (₱)',
                        data: window.chartRevenue,
                        backgroundColor: 'rgba(33, 150, 243, 0.2)',
                        borderColor: 'rgba(33, 150, 243, 1)',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        }

        // Rating Chart
        const ratingChartCanvas = document.getElementById('ratingChart');
        if (ratingChartCanvas) {
            console.log("Creating rating chart");
            ratingChartCanvas.chart = new Chart(ratingChartCanvas, {
                type: 'doughnut',
                data: {
                    labels: ['1 Star', '2 Stars', '3 Stars', '4 Stars', '5 Stars'],
                    datasets: [{
                        data: window.chartRatings,
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
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        }
        
        // Mark as initialized
        window.chartsInitialized = true;
        console.log("Chart initialization complete");
        
    } catch (e) {
        console.error("Error in chart initialization:", e);
    }
}

    function initializeFilters() {
        // Initialize filter checkboxes
        const includeMembers = document.getElementById('include-members');
        const includeRatings = document.getElementById('include-ratings');
        const includeReviews = document.getElementById('include-reviews');
        const includeRevenue = document.getElementById('include-revenue');
        const includeCharts = document.getElementById('include-charts');
        
        // Apply filters
        function applyFilters() {
            // Filter metric cards
            const metricCards = document.querySelectorAll('.metric-card');
            metricCards.forEach(card => {
                const type = card.getAttribute('data-type');
                
                if (type === 'members' && includeMembers) {
                   card.setAttribute('data-hidden', !includeMembers.checked);
               } else if (type === 'ratings' && includeRatings) {
                   card.setAttribute('data-hidden', !includeRatings.checked);
               } else if (type === 'reviews' && includeReviews) {
                   card.setAttribute('data-hidden', !includeReviews.checked);
               } else if (type === 'revenue' && includeRevenue) {
                   card.setAttribute('data-hidden', !includeRevenue.checked);
               }
           });
           
           // Filter charts
           const chartContainers = document.querySelectorAll('.chart-container');
           chartContainers.forEach(chart => {
               const type = chart.getAttribute('data-type');
               const showCharts = includeCharts ? includeCharts.checked : true;
               
               if (type === 'members' && includeMembers) {
                   chart.setAttribute('data-hidden', !includeMembers.checked || !showCharts);
               } else if (type === 'ratings' && includeRatings) {
                   chart.setAttribute('data-hidden', !includeRatings.checked || !showCharts);
               } else if (type === 'revenue' && includeRevenue) {
                   chart.setAttribute('data-hidden', !includeRevenue.checked || !showCharts);
               }
           });
           
           // Filter member section
           if (includeMembers) {
               const memberSection = document.querySelector('.members-section');
               if (memberSection) {
                   memberSection.setAttribute('data-hidden', !includeMembers.checked);
               }
           }
       }
       
       // Add filter change listeners
       if (includeMembers) includeMembers.addEventListener('change', applyFilters);
       if (includeRatings) includeRatings.addEventListener('change', applyFilters);
       if (includeReviews) includeReviews.addEventListener('change', applyFilters);
       if (includeRevenue) includeRevenue.addEventListener('change', applyFilters);
       if (includeCharts) includeCharts.addEventListener('change', applyFilters);
       
       // Apply initial filters
       applyFilters();
   }

   function initializeMemberFiltering() {
       // Get elements
       const activeCheckbox = document.getElementById('filter-active-members');
       const inactiveCheckbox = document.getElementById('filter-inactive-members');
       const startDateInput = document.getElementById('start-date-filter');
       const endDateInput = document.getElementById('end-date-filter');
       const resetBtn = document.getElementById('reset-filters');
       const membersTable = document.getElementById('membersTable');
       
       if (!membersTable) return;
       
       // Set initial date range
       const today = new Date();
       const twelveMonthsAgo = new Date();
       twelveMonthsAgo.setMonth(today.getMonth() - 12);
       
       // Format dates for input fields
       if (startDateInput) startDateInput.value = formatDateForInput(twelveMonthsAgo);
       if (endDateInput) endDateInput.value = formatDateForInput(today);
       
       // Add event listeners
       if (activeCheckbox) activeCheckbox.addEventListener('change', applyMemberFilters);
       if (inactiveCheckbox) inactiveCheckbox.addEventListener('change', applyMemberFilters);
       if (startDateInput) startDateInput.addEventListener('change', applyMemberFilters);
       if (endDateInput) endDateInput.addEventListener('change', applyMemberFilters);
       if (resetBtn) resetBtn.addEventListener('click', resetMemberFilters);
       
       // Apply initial filtering
       applyMemberFilters();
       
       function formatDateForInput(date) {
           const year = date.getFullYear();
           const month = String(date.getMonth() + 1).padStart(2, '0');
           const day = String(date.getDate()).padStart(2, '0');
           return `${year}-${month}-${day}`;
       }
       
       function resetMemberFilters() {
           if (activeCheckbox) activeCheckbox.checked = true;
           if (inactiveCheckbox) inactiveCheckbox.checked = true;
           
           if (startDateInput) startDateInput.value = formatDateForInput(twelveMonthsAgo);
           if (endDateInput) endDateInput.value = formatDateForInput(today);
           
           applyMemberFilters();
       }
       
       function applyMemberFilters() {
           const showActive = activeCheckbox ? activeCheckbox.checked : true;
           const showInactive = inactiveCheckbox ? inactiveCheckbox.checked : true;
           const startDate = startDateInput && startDateInput.value ? new Date(startDateInput.value) : null;
           const endDate = endDateInput && endDateInput.value ? new Date(endDateInput.value) : null;
           
           const rows = membersTable.querySelectorAll('tbody tr.member-row');
           let activeCount = 0;
           let inactiveCount = 0;
           let totalCount = 0;
           
           rows.forEach(row => {
               const rowStatus = row.getAttribute('data-status');
               const startDateValue = row.getAttribute('data-start-date') ? new Date(row.getAttribute('data-start-date')) : null;
               
               let visible = true;
               
               // Apply status filter
               if ((rowStatus === 'active' && !showActive) || (rowStatus === 'inactive' && !showInactive)) {
                   visible = false;
               }
               
               // Apply date filters
               if (visible && startDate && startDateValue && startDateValue < startDate) {
                   visible = false;
               }
               
               if (visible && endDate && startDateValue && startDateValue > endDate) {
                   visible = false;
               }
               
               // Show/hide row
               row.style.display = visible ? '' : 'none';
               
               // Update counters
               if (visible) {
                   totalCount++;
                   if (rowStatus === 'active') {
                       activeCount++;
                   } else if (rowStatus === 'inactive') {
                       inactiveCount++;
                   }
               }
           });
           
           // Update the visible count displays
           updateCount('.member-stat-card.active .stat-value', activeCount);
           updateCount('.member-stat-card.inactive .stat-value', inactiveCount);
           updateCount('.member-stat-card.total .stat-value', totalCount);
           
           // Show no-data message if no visible rows
           const noDataRow = membersTable.querySelector('.no-data-row');
           if (totalCount === 0 && !noDataRow) {
               const tbody = membersTable.querySelector('tbody');
               const tr = document.createElement('tr');
               tr.className = 'no-data-row';
               const td = document.createElement('td');
               td.className = 'no-data';
               td.setAttribute('colspan', '6');
               td.textContent = 'No members match the selected filters.';
               tr.appendChild(td);
               tbody.appendChild(tr);
           } else if (totalCount > 0 && noDataRow) {
               noDataRow.remove();
           }
           
           // Store filtered data for PDF export
           window.filteredMemberCounts = {
               active: activeCount,
               inactive: inactiveCount,
               total: totalCount
           };
       }
       
       function updateCount(selector, value) {
           const elements = document.querySelectorAll(selector);
           elements.forEach(el => {
               if (el) el.textContent = value;
           });
       }
   }

   function generatePDF() {
       // Create loading overlay
       const loadingOverlay = document.createElement('div');
       loadingOverlay.className = 'loading-overlay';
       
       const spinner = document.createElement('div');
       spinner.className = 'spinner';
       
       const loadingText = document.createElement('div');
       loadingText.className = 'loading-text';
       loadingText.innerText = 'Generating PDF...';
       
       loadingOverlay.appendChild(spinner);
       loadingOverlay.appendChild(loadingText);
       document.body.appendChild(loadingOverlay);
       
       // Use setTimeout to allow the loading indicator to appear
       setTimeout(function() {
           try {
               const { jsPDF } = window.jspdf;
               const doc = new jsPDF('p', 'mm', 'a4');
               
               // Get gym info
               const gymName = document.querySelector('.gym-info h2').innerText;
               const date = new Date().toLocaleDateString();
               const gymLocation = document.querySelector('.gym-info p').innerText.replace(/^.*?\s/, ""); // Remove icon
               
               // Set up PDF header
               doc.setFont('Helvetica-Bold');
               doc.setFontSize(22);
               doc.text(`${gymName} - Analytics Report`, 105, 20, { align: 'center' });
               
               doc.setFont('Helvetica');
               doc.setFontSize(12);
               doc.text(`Generated on: ${date}`, 105, 30, { align: 'center' });
               doc.text(`Location: ${gymLocation}`, 105, 35, { align: 'center' });
               
               // Get filter info
               const includeMembers = document.getElementById('include-members');
               const includeRatings = document.getElementById('include-ratings');
               const includeReviews = document.getElementById('include-reviews');
               const includeRevenue = document.getElementById('include-revenue');
               const includeCharts = document.getElementById('include-charts');
               
               // Add filter information
               doc.setFontSize(10);
               doc.text('Filters applied:', 20, 45);
               const filterText = [];
               if (includeMembers && includeMembers.checked) filterText.push('Members');
               if (includeRatings && includeRatings.checked) filterText.push('Ratings');
               if (includeReviews && includeReviews.checked) filterText.push('Reviews');
               if (includeRevenue && includeRevenue.checked) filterText.push('Revenue');
               if (includeCharts && includeCharts.checked) filterText.push('Charts');
               doc.text(`Included: ${filterText.join(', ')}`, 20, 50);
               
               // Add key metrics
               let currentY = 60;
               const visibleMetricCards = document.querySelectorAll('.metric-card[data-hidden="false"]');
               
               if (visibleMetricCards.length > 0) {
                   doc.setFont('Helvetica-Bold');
                   doc.setFontSize(16);
                   doc.text('Key Performance Metrics', 20, currentY);
                   currentY += 10;
                   
                   doc.setFont('Helvetica');
                   doc.setFontSize(12);
                   
                   visibleMetricCards.forEach((card) => {
                       const label = card.querySelector('h3').innerText;
                       let value = card.querySelector('.number').innerText;
                       
                       // Clean up values for PDF 
                       if (label.includes('Rating')) {
                           value = value.replace(/[⭐+P±]/g, '').trim();
                           value = value + " rating"; // Add "rating" text instead of star emoji
                       } else if (label.includes('Revenue')) {
                           value = value.replace(/[₱]/g, '').trim();
                           value = "PHP " + value; // Add PHP prefix instead of ₱ symbol
                       }
                       
                       doc.text(`${label}: ${value}`, 20, currentY);
                       currentY += 10;
                   });
                   
                   currentY += 10;
               }
               
               // Process charts if selected
               if (includeCharts && includeCharts.checked) {
                   const visibleChartContainers = document.querySelectorAll('.chart-container[data-hidden="false"]');
                   
                   if (visibleChartContainers.length > 0) {
                       // Create an array of promises for chart processing
                       const chartPromises = Array.from(visibleChartContainers).map((chart, index) => {
                           return new Promise((resolve) => {
                               const title = chart.querySelector('h3').innerText;
                               const canvas = chart.querySelector('canvas');
                               
                               if (!canvas) {
                                   console.error('Canvas not found in chart container');
                                   resolve(null);
                                   return;
                               }
                               
                               html2canvas(canvas, {
                                   scale: 2,
                                   backgroundColor: null,
                                   logging: false
                               }).then(canvas => {
                                   resolve({
                                       title: title,
                                       imgData: canvas.toDataURL('image/png'),
                                       width: canvas.width,
                                       height: canvas.height,
                                       index: index
                                   });
                               }).catch(error => {
                                   console.error('Error capturing chart:', error);
                                   resolve(null);
                               });
                           });
                       });
                       
                       // Process all charts in parallel
                       Promise.all(chartPromises).then(chartResults => {
                           // Filter out null results
                           const validCharts = chartResults.filter(result => result !== null);
                           
                           // Add charts to PDF
                           validCharts.forEach(chart => {
                               doc.addPage();
                               doc.setFont('Helvetica-Bold');
                               doc.setFontSize(14);
                               doc.text(chart.title, 105, 20, { align: 'center' });
                               
                               // Add chart image
                               const imgWidth = 160;
                               const imgHeight = chart.height * imgWidth / chart.width;
                               const leftPos = (210 - imgWidth) / 2;
                               
                               doc.addImage(chart.imgData, 'PNG', leftPos, 30, imgWidth, imgHeight);
                           });
                           
                           // Add a members section and summary page
                           addMembersSection(doc);
                           addSummaryPage(doc);
                           
                           // Add footer and save
                           finalizeAndSavePDF(doc, gymName, date);
                       }).catch(error => {
                           console.error('Error processing charts:', error);
                           // If charts fail, still add members and summary
                           addMembersSection(doc);
                           addSummaryPage(doc);
                           finalizeAndSavePDF(doc, gymName, date);
                       });
                   } else {
                       // No charts to process
                       addMembersSection(doc);
                       addSummaryPage(doc);
                       finalizeAndSavePDF(doc, gymName, date);
                   }
               } else {
                   // Charts not included
                   addMembersSection(doc);
                   addSummaryPage(doc);
                   finalizeAndSavePDF(doc, gymName, date);
               }
               
               function addMembersSection(doc) {
                   // Only add if members section is included
                    const headers = ["Name", "Plan", "Start Date", "End Date", "Status"];
                    const colWidths = [45, 40, 35, 35, 25]; // Wider columns
                    const tableWidth = colWidths.reduce((sum, width) => sum + width, 0);
                    const leftMargin = (210 - tableWidth) / 2; // Center table
                    const startY = 90;
                    const rowHeight = 10;

                    // Draw table header
                    let currentY = startY;
                    doc.setFillColor(240, 240, 240);
                    doc.rect(leftMargin, currentY - 5, tableWidth, rowHeight, 'F');

                    let currentX = leftMargin;
                    headers.forEach((header, index) => {
                        doc.text(header, currentX + 3, currentY); // Added padding
                        currentX += colWidths[index];
                    });

                    // Added spacing between rows
                    currentY += rowHeight + 2;

                   const includeMembers = document.getElementById('include-members');
                   if (!(includeMembers && includeMembers.checked)) return;
                   
                   // Add a new page for members
                   doc.addPage();
                   
                   // Get member counts
                   const memberCounts = window.filteredMemberCounts || {
                       active: document.querySelector('.member-stat-card.active .stat-value')?.textContent || '0',
                       inactive: document.querySelector('.member-stat-card.inactive .stat-value')?.textContent || '0',
                       total: document.querySelector('.member-stat-card.total .stat-value')?.textContent || '0'
                   };
                   
                   // Add member section header
                   doc.setFont('Helvetica-Bold');
                   doc.setFontSize(16);
                   doc.text("Member Statistics", 105, 20, { align: 'center' });
                   
                   // Add member stats
                   doc.setFont('Helvetica');
                   doc.setFontSize(12);
                   doc.text(`Active Members: ${memberCounts.active}`, 30, 40);
                   doc.text(`Inactive Members: ${memberCounts.inactive}`, 30, 50);
                   doc.text(`Total Members: ${memberCounts.total}`, 30, 60);
                   
                   // Add member table if available
                   const membersTable = document.getElementById('membersTable');
                   if (membersTable) {
                       // Add table header
                       doc.setFont('Helvetica-Bold');
                       doc.setFontSize(14);
                       doc.text("Member List", 105, 80, { align: 'center' });
                       
                       // Get visible rows (not hidden by filter)
                       const visibleRows = Array.from(membersTable.querySelectorAll('tbody tr.member-row'))
                           .filter(row => row.style.display !== 'none' && !row.classList.contains('no-data-row'));
                       
                       if (visibleRows.length > 0) {
                           // Define column headers and positions - IMPROVED SPACING
                           const headers = ["Name", "Plan", "Start Date", "End Date", "Status"];
                           const colWidths = [45, 40, 35, 35, 25]; // Wider columns
                           const tableWidth = colWidths.reduce((sum, width) => sum + width, 0);
                           const leftMargin = (210 - tableWidth) / 2; // Center table
                           const startY = 90;
                           const rowHeight = 10;
                           
                           // Draw table header
                           let currentY = startY;
                           doc.setFillColor(240, 240, 240);
                           doc.rect(leftMargin, currentY - 5, tableWidth, rowHeight, 'F');
                           
                           let currentX = leftMargin;
                           headers.forEach((header, index) => {
                               doc.text(header, currentX + 3, currentY); // Added padding
                               currentX += colWidths[index];
                           });
                           
                           currentY += rowHeight;
                           doc.setFont('Helvetica');
                           
                           // Draw rows (max 15 per page - fewer rows for better spacing)
                           const maxRows = Math.min(visibleRows.length, 15);
                           
                           for (let i = 0; i < maxRows; i++) {
                               const row = visibleRows[i];
                               
                               // Add alternating row colors
                               if (i % 2 === 1) {
                                   doc.setFillColor(248, 248, 248);
                                   doc.rect(leftMargin, currentY - 5, tableWidth, rowHeight, 'F');
                               }
                               
                               // Extract data from cells
                               const name = row.cells[0].textContent.trim();
                               const plan = row.cells[2].textContent.trim();
                               const startDate = row.cells[3].textContent.trim();
                               const endDate = row.cells[4].textContent.trim();
                               const status = row.cells[5].textContent.trim();
                               
                               // Draw row data
                               currentX = leftMargin;
                               
                               // Name - truncate if too long (more space)
                               doc.text(name.length > 20 ? name.substring(0, 17) + '...' : name, currentX + 3, currentY);
                               currentX += colWidths[0];
                               
                               // Plan - truncate if too long (more space)
                               doc.text(plan.length > 18 ? plan.substring(0, 15) + '...' : plan, currentX + 3, currentY);
                               currentX += colWidths[1];
                               
                               // Start date
                               doc.text(startDate, currentX + 3, currentY);
                               currentX += colWidths[2];
                               
                               // End date
                               doc.text(endDate, currentX + 3, currentY);
                               currentX += colWidths[3];
                               
                               // Status with color
                               if (status.toLowerCase().includes('active')) {
                                   doc.setTextColor(76, 175, 80); // Green
                               } else {
                                   doc.setTextColor(244, 67, 54); // Red
                               }
                               
                               doc.text(status, currentX + 3, currentY);
                               doc.setTextColor(0); // Reset to black
                               
                               currentY += rowHeight + 2; // Added spacing between rows
                           }
                           
                           // Add note if more members than shown
                           if (visibleRows.length > maxRows) {
                               currentY += 5;
                               doc.text(`Note: Showing ${maxRows} of ${visibleRows.length} members.`, leftMargin, currentY);
                           }
                       } else {
                           // No members visible
                           doc.setFont('Helvetica');
                           doc.text("No members match the current filter criteria.", 105, 100, { align: 'center' });
                       }
                   }
               }
               
               function addSummaryPage(doc) {
                   // Add summary page
                   doc.addPage();
                   doc.setFont('Helvetica-Bold');
                   doc.setFontSize(16);
                   doc.text("Analytics Summary", 105, 20, { align: 'center' });
                   
                   doc.setFont('Helvetica');
                   doc.setFontSize(12);
                   
                   // Get metrics with proper formatting
                   const gymName = document.querySelector('.gym-info h2').innerText;
                   const gymLocation = document.querySelector('.gym-info p').innerText.replace(/^.*?\s/, "");
                   const date = new Date().toLocaleDateString();
                   
                   // Get member counts from filtering
                   const memberCounts = window.filteredMemberCounts || {
                       active: document.querySelector('.member-stat-card.active .stat-value')?.textContent || '0',
                       inactive: document.querySelector('.member-stat-card.inactive .stat-value')?.textContent || '0',
                       total: document.querySelector('.member-stat-card.total .stat-value')?.textContent || '0'
                   };
                   
                   // Get metrics with clean formats
                   let ratingValue = document.querySelector('.metric-card[data-type="ratings"] .number')?.textContent || '0';
                   ratingValue = ratingValue.replace(/[⭐+P±]/g, '').trim();
                   
                   let revenueValue = document.querySelector('.metric-card[data-type="revenue"] .number')?.textContent || '₱0';
                   revenueValue = "PHP " + revenueValue.replace(/[₱]/g, '').trim();
                   
                   const reviewsValue = document.querySelector('.metric-card[data-type="reviews"] .number')?.textContent || '0';
                   
                   // Create summary text
                   let summaryText = `This report presents a comprehensive analysis of ${gymName} located at ${gymLocation}. `;
                   summaryText += `The gym currently has ${memberCounts.active} active members and ${memberCounts.inactive} inactive members, for a total of ${memberCounts.total} members. `;
                   
                   // Add more details based on included sections
                   if (includeRatings && includeRatings.checked) {
                       summaryText += `The average customer satisfaction rating is ${ratingValue} out of 5, `;
                       if (parseFloat(ratingValue) >= 4) {
                           summaryText += "indicating excellent customer satisfaction. ";
                       } else if (parseFloat(ratingValue) >= 3) {
                           summaryText += "showing good customer satisfaction. ";
                       } else {
                           summaryText += "suggesting there are areas for improvement in customer satisfaction. ";
                       }
                   }
                   
                   if (includeReviews && includeReviews.checked) {
                       summaryText += `Based on ${reviewsValue} customer reviews, `;
                   }
                   
                   if (includeRevenue && includeRevenue.checked) {
                       summaryText += `the gym has generated a total revenue of ${revenueValue}. `;
                   }
                   
                   summaryText += `\n\nThis report was generated on ${date} and includes `;
                   
                   if (includeCharts && includeCharts.checked) {
                       summaryText += "visualizations of key performance indicators through charts and graphs. ";
                   }
                   
                   summaryText += "The data provides valuable insights for gym management to make informed decisions ";
                   summaryText += "regarding membership growth strategies, pricing optimization, and service improvements.";
                   
                   // Add the summary text with proper line breaks
                   const splitText = doc.splitTextToSize(summaryText, 170);
                   doc.text(splitText, 20, 40);
               }
               
               function finalizeAndSavePDF(doc, gymName, date) {
                   // Add footer with page numbers
                   const pageCount = doc.getNumberOfPages();
                   for (let i = 1; i <= pageCount; i++) {
                       doc.setPage(i);
                       doc.setFontSize(10);
                       doc.setTextColor(150);
                       doc.text(`${gymName} Analytics Report - Page ${i} of ${pageCount}`, 105, 285, { align: 'center' });
                   }
                   
                   // Remove loading overlay and save
                   document.body.removeChild(loadingOverlay);
                   
                   // Generate filename
                   const filename = `${gymName.replace(/\s+/g, '_')}_Analytics_${date.replace(/\//g, '-')}.pdf`;
                   doc.save(filename);
               }
           } catch (error) {
               console.error('Error generating PDF:', error);
               document.body.removeChild(loadingOverlay);
               alert('Error generating PDF. Please try again.');
           }
       }, 500);
   }
   </script>
</body>
</html>