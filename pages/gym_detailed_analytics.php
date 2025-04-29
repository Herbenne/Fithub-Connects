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
    <!-- Make sure the scripts load before the page renders -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
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

            <div class="members-section" data-type="members">
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
        </section>


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

    .chart-container[data-hidden="true"],
    .metric-card[data-hidden="true"] {
        display: none;
    }
    
    /* Fix for hidden sections */
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

    document.addEventListener('DOMContentLoaded', function() {
        // Initialize charts first
        initializeCharts();
        
        // Then initialize the rest of the functionality
        initializeAnalytics();
    });

    function initializeCharts() {
        // Member Growth Chart
        const memberGrowthCanvas = document.getElementById('memberGrowthChart');
        if (memberGrowthCanvas) {
            try {
                new Chart(memberGrowthCanvas, {
                    type: 'line',
                    data: {
                        labels: chartMonths,
                        datasets: [{
                            label: 'New Members',
                            data: chartMembers,
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
            } catch (e) {
                console.error("Error initializing member growth chart:", e);
                memberGrowthCanvas.parentNode.innerHTML = '<div class="chart-error">Error loading chart. Please try refreshing the page.</div>';
            }
        }

        // Revenue Chart
        const revenueChartCanvas = document.getElementById('revenueChart');
        if (revenueChartCanvas) {
            try {
                new Chart(revenueChartCanvas, {
                    type: 'bar',
                    data: {
                        labels: chartMonths,
                        datasets: [{
                            label: 'Monthly Revenue (₱)',
                            data: chartRevenue,
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
            } catch (e) {
                console.error("Error initializing revenue chart:", e);
                revenueChartCanvas.parentNode.innerHTML = '<div class="chart-error">Error loading chart. Please try refreshing the page.</div>';
            }
        }

        // Rating Chart
        const ratingChartCanvas = document.getElementById('ratingChart');
        if (ratingChartCanvas) {
            try {
                new Chart(ratingChartCanvas, {
                    type: 'pie',
                    data: {
                        labels: ['⭐', '⭐⭐', '⭐⭐⭐', '⭐⭐⭐⭐', '⭐⭐⭐⭐⭐'],
                        datasets: [{
                            data: chartRatings,
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
            } catch (e) {
                console.error("Error initializing rating chart:", e);
                ratingChartCanvas.parentNode.innerHTML = '<div class="chart-error">Error loading chart. Please try refreshing the page.</div>';
            }
        }
    }

    function initializeAnalytics() {
        // Initialize filter checkboxes
        const includeMembers = document.getElementById('include-members');
        const includeRatings = document.getElementById('include-ratings');
        const includeReviews = document.getElementById('include-reviews');
        const includeRevenue = document.getElementById('include-revenue');
        const includeCharts = document.getElementById('include-charts');
        
        // Initialize filters
        function initializeFilters() {
            // Make sure all checkboxes are checked by default
            if (includeMembers) includeMembers.checked = true;
            if (includeRatings) includeRatings.checked = true;
            if (includeReviews) includeReviews.checked = true;
            if (includeRevenue) includeRevenue.checked = true;
            if (includeCharts) includeCharts.checked = true;
            
            // Apply filters
            applyFilters();
        }
        
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
        
        // Set up PDF export button
        const downloadPdfBtn = document.getElementById('downloadPdfBtn');
        if (downloadPdfBtn) {
            downloadPdfBtn.addEventListener('click', generatePDF);
        }
        
        // Initialize member filtering
        initializeMemberFiltering();
        
        // Call initialize filters
        initializeFilters();
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
                
                // Set up PDF
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
                
                // Add gym key metrics
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
                        doc.text(`${label}: ${value}`, 20, currentY);
                        currentY += 10;
                    });
                    
                    currentY += 10;
                }
                
                // Process charts if selected
                if (includeCharts && includeCharts.checked) {
                    const visibleChartContainers = document.querySelectorAll('.chart-container[data-hidden="false"]');
                    
                    if (visibleChartContainers.length > 0) {
                        // Create array of promises for chart processing
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
                            
                            // Add summary page and finish
                            finalizePDF(doc);
                        });
                    } else {
                        // No charts to process
                        finalizePDF(doc);
                    }
                } else {
                    // Charts not included
                    finalizePDF(doc);
                }
            } catch (error) {
                console.error('Error generating PDF:', error);
                document.body.removeChild(loadingOverlay);
                alert('Error generating PDF. Please try again.');
            }
        }, 500);
        
        function finalizePDF(doc) {
            // Add summary page
            doc.addPage();
            doc.setFont('Helvetica-Bold');
            doc.setFontSize(16);
            doc.text("Analytics Summary", 105, 20, { align: 'center' });
            
            doc.setFont('Helvetica');
            doc.setFontSize(12);
            
            // Get metrics
            const gymName = document.querySelector('.gym-info h2').innerText;
            const gymLocation = document.querySelector('.gym-info p').innerText.replace(/^.*?\s/, "");
            const date = new Date().toLocaleDateString();
            
            // Get member counts
            const memberCounts = window.filteredMemberCounts || {
                active: document.querySelector('.member-stat-card.active .stat-value')?.textContent || '0',
                inactive: document.querySelector('.member-stat-card.inactive .stat-value')?.textContent || '0',
                total: document.querySelector('.member-stat-card.total .stat-value')?.textContent || '0'
            };
            
            // Create summary text
            let summaryText = `This report presents a comprehensive analysis of ${gymName} located at ${gymLocation}. `;
            summaryText += `The gym currently has ${memberCounts.active} active members and ${memberCounts.inactive} inactive members, for a total of ${memberCounts.total} members. `;
            
            // Add more details based on included sections
            const includeRatings = document.getElementById('include-ratings');
            const includeReviews = document.getElementById('include-reviews');
            const includeRevenue = document.getElementById('include-revenue');
            
            if (includeRatings && includeRatings.checked) {
                const ratingValue = document.querySelector('.metric-card[data-type="ratings"] .number')?.textContent?.replace('⭐', '') || '0';
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
                const reviewCount = document.querySelector('.metric-card[data-type="reviews"] .number')?.textContent || '0';
                summaryText += `Based on ${reviewCount} customer reviews, `;
            }
            
            if (includeRevenue && includeRevenue.checked) {
                const revenue = document.querySelector('.metric-card[data-type="revenue"] .number')?.textContent || '₱0';
                summaryText += `the gym has generated a total revenue of ${revenue}. `;
            }
            
            summaryText += `\n\nThis report was generated on ${date} and includes `;
            
            const includeCharts = document.getElementById('include-charts');
            if (includeCharts && includeCharts.checked) {
                summaryText += "visualizations of key performance indicators through charts and graphs. ";
            }
            
            summaryText += "The data provides valuable insights for gym management to make informed decisions ";
            summaryText += "regarding membership growth strategies, pricing optimization, and service improvements.";
            
            // Add the summary text with line breaks
            const splitText = doc.splitTextToSize(summaryText, 170);
            doc.text(splitText, 20, 40);
            
            // Add member list if included
            const includeMembers = document.getElementById('include-members');
            if (includeMembers && includeMembers.checked) {
                // Calculate next Y position
                const summaryY = 40 + (splitText.length * 6) + 15;
                
                // Check if we need a new page
                if (summaryY > 220) {
                    doc.addPage();
                    
                    // Add member section header
                    doc.setFont('Helvetica-Bold');
                    doc.setFontSize(14);
                    doc.text("Member Statistics", 20, 20);
                    
                    // Add member counts
                    doc.setFont('Helvetica');
                    doc.setFontSize(12);
                    doc.text(`Active Members: ${memberCounts.active}`, 25, 35);
                    doc.text(`Inactive Members: ${memberCounts.inactive}`, 25, 45);
                    doc.text(`Total Members: ${memberCounts.total}`, 25, 55);
                }
            }
            
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
            const filterSuffix = document.getElementById('include-members')?.checked ? '_Members' : '';
            const filename = `${gymName.replace(/\s+/g, '_')}_Analytics${filterSuffix}_${date.replace(/\//g, '-')}.pdf`;
            doc.save(filename);
        }
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
    </script>
</body>
</html>
