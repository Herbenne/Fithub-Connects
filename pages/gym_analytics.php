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

//Get members
$member_count_query = "SELECT 
    SUM(CASE WHEN m.end_date >= CURDATE() THEN 1 ELSE 0 END) as active_count,
    SUM(CASE WHEN m.end_date < CURDATE() THEN 1 ELSE 0 END) as inactive_count
    FROM gym_members m
    WHERE m.gym_id = ?";
    
$stmt = $db_connection->prepare($member_count_query);
$stmt->bind_param("i", $gym_id);
$stmt->execute();
$member_counts = $stmt->get_result()->fetch_assoc();

$members_list_query = "SELECT 
    u.id as user_id, 
    CONCAT(u.first_name, ' ', u.last_name) as member_name,
    u.reg_date,
    m.start_date,
    m.end_date,
    p.plan_name,
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
        <div class="analytics-header">
            <div class="header-left">
                <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
                <h2>Analytics for <?php echo htmlspecialchars($gym['gym_name']); ?></h2>
            </div>
            <div class="header-right">
                <div class="filter-dropdown">
                    <button class="filter-btn"><i class="fas fa-filter"></i> Filter Report</button>
                    <div class="filter-menu">
                        <div class="filter-item">
                            <input type="checkbox" id="include-members" checked>
                            <label for="include-members">Members</label>
                        </div>
                        <div class="filter-item">
                            <input type="checkbox" id="include-revenue" checked>
                            <label for="include-revenue">Revenue</label>
                        </div>
                        <div class="filter-item">
                            <input type="checkbox" id="include-ratings" checked>
                            <label for="include-ratings">Ratings</label>
                        </div>
                    </div>
                </div>
                <button id="downloadPdfBtn" class="pdf-download-btn">
                    <i class="fas fa-file-pdf"></i> Export as PDF
                </button>
            </div>
        </div>

        <div class="analytics-grid">
            <div class="chart-container" data-type="members">
                <h3>Member Growth</h3>
                <canvas id="memberChart"></canvas>
            </div>

            <div class="chart-container" data-type="revenue">
                <h3>Monthly Revenue</h3>
                <canvas id="revenueChart"></canvas>
            </div>

            <div class="chart-container" data-type="ratings">
                <h3>Rating Distribution</h3>
                <canvas id="ratingChart"></canvas>
            </div>
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
            </div>

            <style>
            /* Member Section Styles for Analytics Pages */
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

            .filter-row {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 20px;
            }

            .filter-group {
                display: flex;
                flex-direction: column;
                gap: 10px;
            }

            .filter-group label {
                font-size: 14px;
                color: #555;
                font-weight: 500;
            }

            .checkbox-group {
                display: flex;
                flex-direction: column;
                gap: 10px;
            }

            .checkbox-item {
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .checkbox-item input[type="checkbox"] {
                width: 16px;
                height: 16px;
            }

            .date-input {
                padding: 10px 15px;
                border: 1px solid #ddd;
                border-radius: 5px;
                width: 100%;
            }

            .reset-filter-btn {
                background: #f1f1f1;
                border: 1px solid #ddd;
                border-radius: 5px;
                padding: 10px 15px;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                cursor: pointer;
                transition: all 0.3s;
                margin-top: 28px; /* To align with input fields */
            }

            .reset-filter-btn:hover {
                background: #e0e0e0;
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

            /* Responsive adjustments */
            @media (max-width: 992px) {
                .filter-row {
                    grid-template-columns: 1fr 1fr;
                }
            }

            @media (max-width: 768px) {
                .member-stats-cards {
                    grid-template-columns: 1fr;
                }
                
                .filter-row {
                    grid-template-columns: 1fr;
                }
                
                .reset-filter-btn {
                    margin-top: 0;
                }
            }
            </style>
    </div>

    </div>

    
    <style>
    .analytics-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        width: 100%;
    }
    
    .header-left {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .header-right {
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
    
    .chart-container[data-hidden="true"] {
        display: none;
    }

    .chart-container {
        height: 350px; /* Fixed height */
        position: relative;
        overflow: hidden;
    }

    .chart-container canvas {
        max-height: 280px !important;
        width: 100% !important;
        margin-bottom: 70px;
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

    /* Make sure charts have proper height */
    .chart-container {
        height: 300px;
        position: relative;
        margin-bottom: 30px;
    }

    </style>

    <!-- PDF Generation Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

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

    document.addEventListener('DOMContentLoaded', function() {
        // Initialize charts
        initializeCharts();
        
        // Initialize filter functionality
        initializeFilters();
        
        // Set up PDF export button
        const downloadPdfBtn = document.getElementById('downloadPdfBtn');
        if (downloadPdfBtn) {
            downloadPdfBtn.addEventListener('click', generatePDF);
        }
        
        // Initialize member filtering
        initializeMemberFiltering();
    });

    function initializeCharts() {
        // Create charts - using the global variables defined in your existing PHP code
        
        // Member Growth Chart
        const memberChartCanvas = document.getElementById('memberChart');
        if (memberChartCanvas) {
            try {
                new Chart(memberChartCanvas, {
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
            } catch (e) {
                console.error("Error initializing member chart:", e);
                memberChartCanvas.parentNode.innerHTML = '<div class="chart-error">Error loading chart. Please try refreshing the page.</div>';
            }
        }

        // Revenue Chart
        const revenueChartCanvas = document.getElementById('revenueChart');
        if (revenueChartCanvas) {
            try {
                new Chart(revenueChartCanvas, {
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
                    type: 'doughnut',
                    data: ratingData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false
                    }
                });
            } catch (e) {
                console.error("Error initializing rating chart:", e);
                ratingChartCanvas.parentNode.innerHTML = '<div class="chart-error">Error loading chart. Please try refreshing the page.</div>';
            }
        }
    }

    function initializeFilters() {
        // Filter checkboxes
        const includeMembers = document.getElementById('include-members');
        const includeRevenue = document.getElementById('include-revenue');
        const includeRatings = document.getElementById('include-ratings');
        
        // Initialize
        function setInitialFilters() {
            [includeMembers, includeRevenue, includeRatings].forEach(checkbox => {
                if (checkbox) checkbox.checked = true;
            });
            applyFilters();
        }
        
        // Apply filters
        function applyFilters() {
            // Filter charts
            const chartContainers = document.querySelectorAll('.chart-container');
            chartContainers.forEach(chart => {
                const type = chart.getAttribute('data-type');
                
                if (type === 'members' && includeMembers) {
                    chart.setAttribute('data-hidden', !includeMembers.checked);
                } else if (type === 'revenue' && includeRevenue) {
                    chart.setAttribute('data-hidden', !includeRevenue.checked);
                } else if (type === 'ratings' && includeRatings) {
                    chart.setAttribute('data-hidden', !includeRatings.checked);
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
        if (includeRevenue) includeRevenue.addEventListener('change', applyFilters);
        if (includeRatings) includeRatings.addEventListener('change', applyFilters);
        
        // Initialize
        setInitialFilters();
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
                const gymName = document.querySelector('h2').innerText.replace('Analytics for ', '');
                const date = new Date().toLocaleDateString();
                
                // Set up PDF
                doc.setFont('Helvetica-Bold');
                doc.setFontSize(22);
                doc.text(`${gymName} - Analytics Report`, 105, 20, { align: 'center' });
                
                doc.setFont('Helvetica');
                doc.setFontSize(12);
                doc.text(`Generated on: ${date}`, 105, 30, { align: 'center' });
                
                // Add filter information
                const includeMembers = document.getElementById('include-members');
                const includeRatings = document.getElementById('include-ratings');
                const includeRevenue = document.getElementById('include-revenue');
                
                doc.setFontSize(10);
                doc.text('Filters applied:', 20, 40);
                const filterText = [];
                if (includeMembers && includeMembers.checked) filterText.push('Members');
                if (includeRatings && includeRatings.checked) filterText.push('Ratings');
                if (includeRevenue && includeRevenue.checked) filterText.push('Revenue');
                doc.text(`Included: ${filterText.join(', ')}`, 20, 45);
                
                // Process charts
                const visibleChartContainers = document.querySelectorAll('.chart-container[data-hidden="false"]');
                
                // Create a queue of promises for chart processing
                const chartPromises = [];
                
                visibleChartContainers.forEach((chart, index) => {
                    const title = chart.querySelector('h3').innerText;
                    const canvas = chart.querySelector('canvas');
                    
                    if (!canvas) {
                        console.error('Canvas not found in chart container');
                        return;
                    }
                    
                    const chartPromise = new Promise((resolve) => {
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
                            resolve(null); // Resolve with null to continue processing other charts
                        });
                    });
                    
                    chartPromises.push(chartPromise);
                });
                
                // Process all charts in parallel
                Promise.all(chartPromises).then(chartResults => {
                    // Remove null results
                    const validCharts = chartResults.filter(result => result !== null);
                    
                    // Add charts to PDF, one per page
                    validCharts.forEach(chart => {
                        doc.addPage();
                        let currentY = 20;
                        
                        // Add chart title
                        doc.setFont('Helvetica-Bold');
                        doc.setFontSize(14);
                        doc.text(chart.title, 105, currentY, { align: 'center' });
                        currentY += 15;
                        
                        // Calculate image dimensions
                        const imgWidth = 160;
                        const imgHeight = chart.height * imgWidth / chart.width;
                        
                        // Center horizontally
                        const leftPos = (210 - imgWidth) / 2;
                        
                        // Add chart image
                        doc.addImage(chart.imgData, 'PNG', leftPos, currentY, imgWidth, imgHeight);
                    });

                    // Add members section if included and if members filter is enabled
                    if (includeMembers && includeMembers.checked) {
                        // Add a new page for members
                        doc.addPage();
                        
                        // Add member section header
                        doc.setFont('Helvetica-Bold');
                        doc.setFontSize(16);
                        doc.text("Member Statistics", 105, 20, { align: 'center' });
                        
                        // Get member counts
                        const memberCounts = window.filteredMemberCounts || {
                            active: document.querySelector('.member-stat-card.active .stat-value')?.textContent || '0',
                            inactive: document.querySelector('.member-stat-card.inactive .stat-value')?.textContent || '0',
                            total: document.querySelector('.member-stat-card.total .stat-value')?.textContent || '0'
                        };
                        
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
                                // Define column headers and positions
                                const headers = ["Name", "Registration Date", "Plan", "Start Date", "End Date", "Status"];
                                const colWidths = [40, 45, 35, 35, 35, 20];
                                const startY = 90;
                                const rowHeight = 10;
                                
                                // Calculate total width and center position
                                const tableWidth = colWidths.reduce((sum, width) => sum + width, 0);
                                const leftMargin = (210 - tableWidth) / 2;
                                
                                // Draw table header
                                let currentY = startY;
                                doc.setFillColor(240, 240, 240);
                                doc.rect(leftMargin, currentY - 5, tableWidth, rowHeight, 'F');
                                
                                let currentX = leftMargin;
                                headers.forEach((header, index) => {
                                    doc.text(header, currentX + 2, currentY);
                                    currentX += colWidths[index];
                                });
                                
                                currentY += rowHeight;
                                doc.setFont('Helvetica');
                                
                                // Draw rows (max 20 per page)
                                const maxRows = Math.min(visibleRows.length, 20);
                                
                                for (let i = 0; i < maxRows; i++) {
                                    const row = visibleRows[i];
                                    
                                    // Add alternating row colors
                                    if (i % 2 === 1) {
                                        doc.setFillColor(248, 248, 248);
                                        doc.rect(leftMargin, currentY - 5, tableWidth, rowHeight, 'F');
                                    }
                                    
                                    // Extract data from cells
                                    const name = row.cells[0].textContent.trim();
                                    const regDate = row.cells[1].textContent.trim();
                                    const plan = row.cells[2].textContent.trim();
                                    const startDate = row.cells[3].textContent.trim();
                                    const endDate = row.cells[4].textContent.trim();
                                    const status = row.cells[5].textContent.trim();
                                    
                                    // Draw row data
                                    currentX = leftMargin;
                                    
                                    // Name - truncate if too long
                                    doc.text(name.length > 18 ? name.substring(0, 15) + '...' : name, currentX + 2, currentY);
                                    currentX += colWidths[0];
                                    
                                    // Registration date
                                    doc.text(regDate, currentX + 2, currentY);
                                    currentX += colWidths[1];
                                    
                                    // Plan - truncate if too long
                                    doc.text(plan.length > 15 ? plan.substring(0, 12) + '...' : plan, currentX + 2, currentY);
                                    currentX += colWidths[2];
                                    
                                    // Start date
                                    doc.text(startDate, currentX + 2, currentY);
                                    currentX += colWidths[3];
                                    
                                    // End date
                                    doc.text(endDate, currentX + 2, currentY);
                                    currentX += colWidths[4];
                                    
                                    if (status.toLowerCase().includes('active')) {
                                        doc.setTextColor(76, 175, 80); // Green
                                    } else {
                                        doc.setTextColor(244, 67, 54); // Red
                                    }
                                
                                    doc.text(status, currentX + 3, currentY);
                                    doc.setTextColor(0); // Reset to black
                                    
                                    currentY += rowHeight;
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
                    // Add summary page
                    doc.addPage();
                    doc.setFont('Helvetica-Bold');
                    doc.setFontSize(16);
                    doc.text("Analytics Summary", 105, 20, { align: 'center' });
                    
                    doc.setFont('Helvetica');
                    doc.setFontSize(12);
                    
                    // Get filtered member counts
                    const memberCounts = window.filteredMemberCounts || {
                        active: document.querySelector('.member-stat-card.active .stat-value')?.textContent || '0',
                        inactive: document.querySelector('.member-stat-card.inactive .stat-value')?.textContent || '0',
                        total: document.querySelector('.member-stat-card.total .stat-value')?.textContent || '0'
                    };
                    
                    // Create summary text
                    let summaryText = `This report presents analytics data for ${gymName}. `;
                    summaryText += `The gym currently has ${memberCounts.active} active members and ${memberCounts.inactive} inactive members, `;
                    summaryText += `for a total of ${memberCounts.total} members. `;
                    
                    // Add chart-specific summary
                    if (includeRevenue && includeRevenue.checked) {
                        summaryText += "The report includes financial performance data through revenue tracking. ";
                    }
                    
                    if (includeRatings && includeRatings.checked) {
                        summaryText += "Customer satisfaction is measured through rating distribution analysis. ";
                    }
                    
                    summaryText += `\n\nThis report was generated on ${date} and provides valuable insights for gym management. `;
                    summaryText += "The data can be used to identify trends, make data-driven decisions, and develop strategies ";
                    summaryText += "for improving membership growth, revenue generation, and customer satisfaction.";
                    
                    // Add summary text with proper line wrapping
                    const splitText = doc.splitTextToSize(summaryText, 170);
                    doc.text(splitText, 20, 40);
                    
                    // Add footer with page numbers
                    const pageCount = doc.getNumberOfPages();
                    for (let i = 1; i <= pageCount; i++) {
                        doc.setPage(i);
                        doc.setFontSize(10);
                        doc.setTextColor(150);
                        doc.text(`${gymName} Analytics Report - Page ${i} of ${pageCount}`, 105, 285, { align: 'center' });
                    }
                    
                    // Remove loading overlay and save PDF
                    document.body.removeChild(loadingOverlay);
                    
                    // Generate filename with gym name and date
                    const filename = `${gymName.replace(/\s+/g, '_')}_Analytics_${date.replace(/\//g, '-')}.pdf`;
                    doc.save(filename);
                }).catch(error => {
                    console.error('Error processing charts:', error);
                    document.body.removeChild(loadingOverlay);
                    alert('Error generating PDF. Please try again.');
                });
            } catch (error) {
                console.error('Error generating PDF:', error);
                document.body.removeChild(loadingOverlay);
                alert('Error generating PDF. Please try again.');
            }
        }, 500);
    }

    function initializeMemberFiltering() {
        // Member filtering
        const activeFilter = document.getElementById('filter-active-members');
        const inactiveFilter = document.getElementById('filter-inactive-members');
        const startDateInput = document.getElementById('start-date-filter');
        const endDateInput = document.getElementById('end-date-filter');
        const resetBtn = document.getElementById('reset-filters');
        const membersTable = document.getElementById('membersTable');
        
        if (!membersTable) return;
        
        // Set initial date range (past 12 months to today)
        const today = new Date();
        const twelveMonthsAgo = new Date();
        twelveMonthsAgo.setMonth(today.getMonth() - 12);
        
        // Format dates for input fields
        if (startDateInput) startDateInput.value = formatDateForInput(twelveMonthsAgo);
        if (endDateInput) endDateInput.value = formatDateForInput(today);
        
        // Add event listeners
        if (activeFilter) activeFilter.addEventListener('change', applyFilters);
        if (inactiveFilter) inactiveFilter.addEventListener('change', applyFilters);
        if (startDateInput) startDateInput.addEventListener('change', applyFilters);
        if (endDateInput) endDateInput.addEventListener('change', applyFilters);
        if (resetBtn) resetBtn.addEventListener('click', resetFilters);
        
        // Apply initial filtering
        applyFilters();
        
        function formatDateForInput(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }
        
        function resetFilters() {
            if (activeFilter) activeFilter.checked = true;
            if (inactiveFilter) inactiveFilter.checked = true;
            
            if (startDateInput) startDateInput.value = formatDateForInput(twelveMonthsAgo);
            if (endDateInput) endDateInput.value = formatDateForInput(today);
            
            applyFilters();
        }
        
        function applyFilters() {
            const showActive = activeFilter ? activeFilter.checked : true;
            const showInactive = inactiveFilter ? inactiveFilter.checked : true;
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