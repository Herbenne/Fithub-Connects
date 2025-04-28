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

// For gym_analytics.php (Admin View) - Add this after existing queries
$member_list_query = "SELECT 
    u.id as user_id, 
    CONCAT(u.first_name, ' ', u.last_name) as member_name,
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

$stmt = $db_connection->prepare($member_list_query);
$stmt->bind_param("i", $gym_id);
$stmt->execute();
$members_list = $stmt->get_result();

// Also add a count query for active/inactive members
$member_count_query = "SELECT 
    SUM(CASE WHEN m.end_date >= CURDATE() THEN 1 ELSE 0 END) as active_count,
    SUM(CASE WHEN m.end_date < CURDATE() THEN 1 ELSE 0 END) as inactive_count
    FROM gym_members m
    WHERE m.gym_id = ?";
    
$stmt = $db_connection->prepare($member_count_query);
$stmt->bind_param("i", $gym_id);
$stmt->execute();
$member_counts = $stmt->get_result()->fetch_assoc();

// For gym_detailed_analytics.php (Superadmin View) - Add this after existing queries
$member_list_query = "SELECT 
    u.id as user_id, 
    CONCAT(u.first_name, ' ', u.last_name) as member_name,
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

$stmt = $db_connection->prepare($member_list_query);
$stmt->bind_param("i", $gym_id);
$stmt->execute();
$members_list = $stmt->get_result();

// Member count query for active/inactive
$member_count_query = "SELECT 
    SUM(CASE WHEN m.end_date >= CURDATE() THEN 1 ELSE 0 END) as active_count,
    SUM(CASE WHEN m.end_date < CURDATE() THEN 1 ELSE 0 END) as inactive_count
    FROM gym_members m
    WHERE m.gym_id = ?";
    
$stmt = $db_connection->prepare($member_count_query);
$stmt->bind_param("i", $gym_id);
$stmt->execute();
$member_counts = $stmt->get_result()->fetch_assoc();

// For all_gyms_analytics.php (Superadmin View) - Add to overall_stats_query
// Modify the existing overall_stats_query to include active/inactive counts:

$overall_stats_query = "SELECT 
    COUNT(DISTINCT g.gym_id) as total_gyms,
    COUNT(DISTINCT m.user_id) as total_members,
    SUM(CASE WHEN m.end_date >= CURDATE() THEN 1 ELSE 0 END) as active_members,
    SUM(CASE WHEN m.end_date < CURDATE() THEN 1 ELSE 0 END) as inactive_members,
    COUNT(DISTINCT r.review_id) as total_reviews,
    COALESCE(AVG(r.rating), 0) as overall_rating,
    SUM(p.price) as total_revenue
    FROM gyms g
    LEFT JOIN gym_members m ON g.gym_id = m.gym_id
    LEFT JOIN gym_reviews r ON g.gym_id = r.gym_id
    LEFT JOIN membership_plans p ON m.plan_id = p.plan_id
    WHERE g.status = 'approved'";

// And add a new query to get all members across gyms:
$all_members_query = "SELECT 
    g.gym_id,
    g.gym_name,
    u.id as user_id, 
    CONCAT(u.first_name, ' ', u.last_name) as member_name,
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
    JOIN gyms g ON m.gym_id = g.gym_id
    WHERE g.status = 'approved'
    ORDER BY g.gym_name, status, m.start_date DESC";

$all_members_result = $db_connection->query($all_members_query);
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
        <div class="members-section" data-type="members">
            <div class="section-header">
                <h3>Member List</h3>
                <div class="filter-controls">
                    <select id="memberStatusFilter" class="status-filter">
                        <option value="all">All Members</option>
                        <option value="active">Active Only</option>
                        <option value="inactive">Inactive Only</option>
                    </select>
                    
                    <div class="date-filters">
                        <div class="date-filter">
                            <label for="startDateFilter">From:</label>
                            <input type="date" id="startDateFilter" class="date-input">
                        </div>
                        <div class="date-filter">
                            <label for="endDateFilter">To:</label>
                            <input type="date" id="endDateFilter" class="date-input">
                        </div>
                        <button id="resetFilters" class="reset-btn">Reset</button>
                    </div>
                </div>
            </div>
            
            <div class="member-stats">
                <div class="stat-pill active">
                    <span class="label">Active Members:</span>
                    <span class="value"><?php echo number_format($member_counts['active_count']); ?></span>
                </div>
                <div class="stat-pill inactive">
                    <span class="label">Inactive Members:</span>
                    <span class="value"><?php echo number_format($member_counts['inactive_count']); ?></span>
                </div>
                <div class="stat-pill total">
                    <span class="label">Total:</span>
                    <span class="value"><?php echo number_format($member_counts['active_count'] + $member_counts['inactive_count']); ?></span>
                </div>
            </div>
            
            <div class="members-table-container">
                <table id="membersTable" class="members-table">
                    <thead>
                        <tr>
                            <th>Name</th>
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
                                    data-start-date="<?php echo $member['start_date']; ?>"
                                    data-end-date="<?php echo $member['end_date']; ?>"
                                    data-status="<?php echo strtolower($member['status']); ?>">
                                    <td><?php echo htmlspecialchars($member['member_name']); ?></td>
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
                                <td colspan="5" class="no-data">No members found for this gym.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <style>
        /* Member Section Styles */
        .members-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-top: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .filter-controls {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .status-filter {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background-color: white;
        }

        .date-filters {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .date-filter {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .date-input {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .reset-btn {
            padding: 8px 12px;
            background-color: #f0f0f0;
            border: 1px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
        }

        .reset-btn:hover {
            background-color: #e0e0e0;
        }

        .member-stats {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-pill {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 8px 15px;
            border-radius: 20px;
            background-color: #f0f0f0;
        }

        .stat-pill.active {
            background-color: rgba(76, 175, 80, 0.1);
            border: 1px solid #4CAF50;
        }

        .stat-pill.inactive {
            background-color: rgba(244, 67, 54, 0.1);
            border: 1px solid #F44336;
        }

        .stat-pill.total {
            background-color: rgba(33, 150, 243, 0.1);
            border: 1px solid #2196F3;
        }

        .stat-pill .value {
            font-weight: bold;
        }

        .members-table-container {
            overflow-x: auto;
        }

        .members-table {
            width: 100%;
            border-collapse: collapse;
        }

        .members-table th,
        .members-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .members-table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }

        .members-table tr:hover {
            background-color: #f8f9fa;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 500;
        }

        .status-badge.active {
            background-color: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
        }

        .status-badge.inactive {
            background-color: rgba(244, 67, 54, 0.1);
            color: #F44336;
        }

        .no-data {
            text-align: center;
            color: #666;
            padding: 20px;
        }

        /* Ensure this section is hidden when filters are applied */
        .members-section[data-hidden="true"] {
            display: none;
        }
        </style>

            <div class="chart-container" data-type="revenue">
                <h3>Monthly Revenue</h3>
                <canvas id="revenueChart"></canvas>
            </div>

            <div class="chart-container" data-type="ratings">
                <h3>Rating Distribution</h3>
                <canvas id="ratingChart"></canvas>
            </div>
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

    document.addEventListener('DOMContentLoaded', function() {
        function cleanTextForPDF(text, type) {
            if (!text) return "";
            
            let cleanedText = text;
            
            // Handle different types of values
            if (type === 'rating') {
                // Remove any unexpected characters from rating
                cleanedText = text.replace(/\+P/g, '').replace(/±/g, '').replace(/⭐/g, '').trim();
                
                // Make sure it just shows the number with up to one decimal place
                const ratingNumber = parseFloat(cleanedText);
                if (!isNaN(ratingNumber)) {
                    cleanedText = ratingNumber.toFixed(1);
                }
                
                // Return just the number - no emoji or extra text
                return cleanedText;
            } 
            else if (type === 'revenue') {
                // Remove any currency symbols and clean up
                cleanedText = text.replace(/[₱±]/g, '').replace(/\s+/g, '').trim();
                
                // Parse and format the number properly
                const numericValue = parseFloat(cleanedText.replace(/,/g, ''));
                if (!isNaN(numericValue)) {
                    // Format with commas and 2 decimal places
                    cleanedText = numericValue.toLocaleString('en-US', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                    
                    // Add PHP prefix
                    return "PHP " + cleanedText;
                }
                
                // If parsing failed, just return original with PHP
                return "PHP " + cleanedText;
            }
            
            return cleanedText;
        }
        
        const downloadPdfBtn = document.getElementById('downloadPdfBtn');
        
        // Filter checkboxes
        const includeMembers = document.getElementById('include-members');
        const includeRevenue = document.getElementById('include-revenue');
        const includeRatings = document.getElementById('include-ratings');
        
        // Initialize filters to ensure data appears immediately
        function initializeFilters() {
            // Make sure all checkboxes are checked by default
            [includeMembers, includeRevenue, includeRatings].forEach(checkbox => {
                checkbox.checked = true;
            });
            
            // Apply filters to make all data visible
            applyFilters();
        }
        
        // Apply filters to the view
        function applyFilters() {
            // Filter charts
            const chartContainers = document.querySelectorAll('.chart-container');
            chartContainers.forEach(chart => {
                const type = chart.getAttribute('data-type');
                
                if (type === 'members') {
                    chart.setAttribute('data-hidden', !includeMembers.checked);
                } else if (type === 'revenue') {
                    chart.setAttribute('data-hidden', !includeRevenue.checked);
                } else if (type === 'ratings') {
                    chart.setAttribute('data-hidden', !includeRatings.checked);
                }
            });
        }
        
        // Call initialize on page load
        initializeFilters();
        
        // Add filter change listeners
        [includeMembers, includeRevenue, includeRatings].forEach(checkbox => {
            checkbox.addEventListener('change', applyFilters);
        });
        
        if (downloadPdfBtn) {
            downloadPdfBtn.addEventListener('click', generatePDF);
        }
        
        function generatePDF() {
            // Make sure filters are properly initialized
            initializeFilters();
            
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
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF('p', 'mm', 'a4');
                
                // Define fonts for proper symbol display
                const pdfFonts = {
                    normal: 'Helvetica',
                    bold: 'Helvetica-Bold'
                };
                
                // Get gym info
                const gymName = document.querySelector('h2').innerText.replace('Analytics for ', '');
                const date = new Date().toLocaleDateString();
                
                // Collect data for summary
                const summaryStats = {
                    members: 0,
                    revenue: 0,
                    rating: 0
                };
                
                // Try to extract data from charts
                try {
                    // Member data from chart
                    const memberChart = document.getElementById('memberChart');
                    if (memberChart && memberChart.chart) {
                        const memberData = memberChart.chart.data.datasets[0].data;
                        summaryStats.members = memberData.reduce((a, b) => a + b, 0);
                    }
                    
                    // Revenue data from chart
                    const revenueChart = document.getElementById('revenueChart');
                    if (revenueChart && revenueChart.chart) {
                        const revenueData = revenueChart.chart.data.datasets[0].data;
                        summaryStats.revenue = revenueData.reduce((a, b) => a + b, 0);
                    }
                    
                    // Rating data from chart
                    const ratingChart = document.getElementById('ratingChart');
                    if (ratingChart && ratingChart.chart) {
                        const ratingData = ratingChart.chart.data.datasets[0].data;
                        // Calculate average rating
                        let totalReviews = ratingData.reduce((a, b) => a + b, 0);
                        let weightedSum = 0;
                        for (let i = 0; i < ratingData.length; i++) {
                            weightedSum += (i + 1) * ratingData[i];
                        }
                        if (totalReviews > 0) {
                            summaryStats.rating = (weightedSum / totalReviews).toFixed(1);
                        }
                    }
                } catch (e) {
                    console.log("Error extracting data for summary:", e);
                }
                
                // Set up PDF
                doc.setFont(pdfFonts.bold);
                doc.setFontSize(22);
                doc.text(`${gymName} - Analytics Report`, 105, 20, { align: 'center' });
                
                doc.setFont(pdfFonts.normal);
                doc.setFontSize(12);
                doc.text(`Generated on: ${date}`, 105, 30, { align: 'center' });
                
                // Add filter information
                doc.setFontSize(10);
                doc.text('Filters applied:', 20, 40);
                const filterText = [];
                if (includeMembers.checked) filterText.push('Members');
                if (includeRevenue.checked) filterText.push('Revenue');
                if (includeRatings.checked) filterText.push('Ratings');
                doc.text(`Included: ${filterText.join(', ')}`, 20, 45);
                
                let currentY = 55;
                
                // Process charts based on filters
                const visibleChartContainers = document.querySelectorAll('.chart-container[data-hidden="false"]');

                if (visibleChartContainers.length > 0) {
                    // Function to process charts one by one
                    const processChart = (index) => {
                        if (index >= visibleChartContainers.length) {
                            // All charts processed, add summary page
                            addSummaryPage();
                            return;
                        }
                        
                        const chart = visibleChartContainers[index];
                        const title = chart.querySelector('h3').innerText;
                        
                        // Always start a new page for each chart
                        doc.addPage();
                        currentY = 20;
                        
                        // Add chart title
                        doc.setFont(pdfFonts.bold);
                        doc.setFontSize(14);
                        doc.text(title, 105, currentY, { align: 'center' });
                        currentY += 15;
                        
                        // Capture chart canvas
                        const canvas = chart.querySelector('canvas');
                        
                        html2canvas(canvas, {
                            scale: 2, // Better quality
                            backgroundColor: null,
                            logging: false
                        }).then(canvas => {
                            // Add to PDF - center the chart horizontally
                            const imgData = canvas.toDataURL('image/png');
                            const imgWidth = 160; // Slightly narrower than before
                            const imgHeight = canvas.height * imgWidth / canvas.width;
                            
                            // Calculate left position to center the chart
                            const leftPos = (210 - imgWidth) / 2;
                            
                            doc.addImage(imgData, 'PNG', leftPos, currentY, imgWidth, imgHeight);
                            
                            // Process next chart
                            processChart(index + 1);
                        });
                    };
                    
                    // Start processing charts
                    processChart(0);
                } else {
                    // No charts to process, add summary page
                    addSummaryPage();
                }
                
                function addSummaryPage() {
                    // Add summary page
                    doc.addPage();
                    doc.setFont(pdfFonts.bold);
                    doc.setFontSize(16);
                    doc.text("Analytics Summary", 105, 20, { align: 'center' });
                    
                    doc.setFont(pdfFonts.normal);
                    doc.setFontSize(12);
                    let summaryY = 40;
                    
                    // Create a paragraph summary based on collected stats
                    let summary = `This report presents the analytics data for ${gymName}. `;
                    
                    if (includeMembers.checked) {
                        summary += `The gym has been tracking membership growth over time. `;
                        // Add member stats from filtered view if available
                        if (window.filteredMemberCounts) {
                            summary += `There are currently ${window.filteredMemberCounts.active} active members and ${window.filteredMemberCounts.inactive} inactive members, for a total of ${window.filteredMemberCounts.total} members. `;
                        } else if (summaryStats.members > 0) {
                            summary += `A total of ${summaryStats.members} members have been recorded in the analyzed period. `;
                        }
                    }
                    
                    if (includeRevenue.checked) {
                        summary += `Revenue tracking shows the financial performance of the gym. `;
                        if (summaryStats.revenue > 0) {
                            // Clean revenue value for summary
                            const cleanRevenue = cleanTextForPDF(summaryStats.revenue.toString(), 'revenue');
                            summary += `The gym has generated ${cleanRevenue} in revenue during the analyzed period. `;
                        }
                    }
                    
                    if (includeRatings.checked) {
                        summary += `Customer satisfaction is measured through ratings. `;
                        if (summaryStats.rating > 0) {
                            // Clean rating value for summary
                            const cleanRating = cleanTextForPDF(summaryStats.rating.toString(), 'rating');
                            summary += `The gym has an average rating of ${cleanRating} out of 5 stars. `;
                            
                            const ratingValue = parseFloat(cleanRating);
                            if (ratingValue >= 4) {
                                summary += "This indicates excellent customer satisfaction. ";
                            } else if (ratingValue >= 3) {
                                summary += "This shows good customer satisfaction with room for improvement. ";
                            } else {
                                summary += "This suggests significant areas for improvement in customer satisfaction. ";
                            }
                        }
                    }
                    
                    summary += `\n\nThis report was generated on ${date} and provides valuable insights for gym management. `;
                    summary += "The data can be used to identify trends, make data-driven decisions, and develop strategies ";
                    summary += "for improving membership growth, revenue generation, and customer satisfaction.";
                    
                    // Add the summary paragraph to the PDF with proper line breaks
                    const splitText = doc.splitTextToSize(summary, 170);
                    doc.text(splitText, 20, summaryY);
                    
                    // Add member section to PDF if visible
                    if (includeMembers.checked) {
                        // Get current Y position after summary text
                        summaryY += (splitText.length * 6) + 15;
                        
                        // Check if we need a new page for member data
                        if (summaryY > 220) {
                            doc.addPage();
                            summaryY = 40;
                        }
                        
                        // Add member statistics header
                        doc.setFont(pdfFonts.bold);
                        doc.setFontSize(14);
                        doc.text("Member Statistics", 20, summaryY);
                        summaryY += 10;
                        
                        doc.setFont(pdfFonts.normal);
                        doc.setFontSize(12);
                        
                        // Add active/inactive counts
                        if (window.filteredMemberCounts) {
                            doc.text(`Active Members: ${window.filteredMemberCounts.active}`, 25, summaryY);
                            summaryY += 8;
                            doc.text(`Inactive Members: ${window.filteredMemberCounts.inactive}`, 25, summaryY);
                            summaryY += 8;
                            doc.text(`Total Members: ${window.filteredMemberCounts.total}`, 25, summaryY);
                            summaryY += 15;
                        }
                        
                        // Add filtered members table if there are visible members
                        const visibleMemberRows = membersTable.querySelectorAll('tbody tr.member-row[data-visible-for-pdf="true"]');
                        if (visibleMemberRows.length > 0) {
                            // Check if we need a new page for the table
                            if (summaryY > 180) {
                                doc.addPage();
                                summaryY = 40;
                            }
                            
                            // Add table header
                            doc.setFont(pdfFonts.bold);
                            doc.setFontSize(12);
                            doc.text("Member List (Filtered View)", 20, summaryY);
                            summaryY += 10;
                            
                            // Define table columns
                            const columns = ["Name", "Plan", "Start Date", "End Date", "Status"];
                            const columnWidths = [50, 35, 35, 35, 25];
                            
                            // Draw table header
                            let xPos = 20;
                            doc.setFillColor(240, 240, 240);
                            doc.rect(xPos, summaryY - 5, 180, 10, 'F');
                            
                            columns.forEach((column, index) => {
                                doc.text(column, xPos, summaryY);
                                xPos += columnWidths[index];
                            });
                            
                            summaryY += 8;
                            
                            // Draw table rows (max 15 rows per page)
                            let rowCount = 0;
                            const maxRowsPerPage = 25;
                            
                            visibleMemberRows.forEach((row) => {
                                // Check if we need a new page
                                if (rowCount >= maxRowsPerPage) {
                                    doc.addPage();
                                    summaryY = 20;
                                    
                                    // Redraw header on new page
                                    xPos = 20;
                                    doc.setFont(pdfFonts.bold);
                                    doc.setFillColor(240, 240, 240);
                                    doc.rect(xPos, summaryY - 5, 180, 10, 'F');
                                    
                                    columns.forEach((column, index) => {
                                        doc.text(column, xPos, summaryY);
                                        xPos += columnWidths[index];
                                    });
                                    
                                    summaryY += 8;
                                    rowCount = 0;
                                }
                                
                                // Get cell values
                                const name = row.cells[0].textContent.trim();
                                const plan = row.cells[1].textContent.trim();
                                const startDate = row.cells[2].textContent.trim();
                                const endDate = row.cells[3].textContent.trim();
                                const status = row.cells[4].textContent.trim();
                                
                                // Draw row
                                xPos = 20;
                                doc.setFont(pdfFonts.normal);
                                
                                // Add alternating row background
                                if (rowCount % 2 === 1) {
                                    doc.setFillColor(247, 247, 247);
                                    doc.rect(xPos, summaryY - 5, 180, 10, 'F');
                                }
                                
                                doc.text(name.length > 25 ? name.substring(0, 22) + '...' : name, xPos, summaryY);
                                xPos += columnWidths[0];
                                
                                doc.text(plan.length > 15 ? plan.substring(0, 12) + '...' : plan, xPos, summaryY);
                                xPos += columnWidths[1];
                                
                                doc.text(startDate, xPos, summaryY);
                                xPos += columnWidths[2];
                                
                                doc.text(endDate, xPos, summaryY);
                                xPos += columnWidths[3];
                                
                                // Set color based on status
                                if (status.toLowerCase().includes('active')) {
                                    doc.setTextColor(76, 175, 80); // Green for active
                                } else {
                                    doc.setTextColor(244, 67, 54); // Red for inactive
                                }
                                
                                doc.text(status, xPos, summaryY);
                                doc.setTextColor(0); // Reset to black
                                
                                summaryY += 10;
                                rowCount++;
                            });
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
                    
                    document.body.removeChild(loadingOverlay);
                    const filterSuffix = filterText.join('_');
                    const filename = `${gymName.replace(/\s+/g, '_')}_Analytics_${filterSuffix}_${date.replace(/\//g, '-')}.pdf`;
                    doc.save(filename);
                }
            }, 500);
        }
        // Member filtering logic
        const statusFilter = document.getElementById('memberStatusFilter');
        const startDateFilter = document.getElementById('startDateFilter');
        const endDateFilter = document.getElementById('endDateFilter');
        const resetFiltersBtn = document.getElementById('resetFilters');
        const membersTable = document.getElementById('membersTable');
        
        if (statusFilter && membersTable) {
            // Set default dates (last 6 months to today)
            const today = new Date();
            const sixMonthsAgo = new Date();
            sixMonthsAgo.setMonth(today.getMonth() - 6);
            
            // Format dates for input fields
            startDateFilter.value = formatDateForInput(sixMonthsAgo);
            endDateFilter.value = formatDateForInput(today);
            
            // Apply initial filtering
            applyFilters();
            
            // Add event listeners
            statusFilter.addEventListener('change', applyFilters);
            startDateFilter.addEventListener('change', applyFilters);
            endDateFilter.addEventListener('change', applyFilters);
            resetFiltersBtn.addEventListener('click', resetFilters);
            
            // Include member filtering in the PDF export
            const downloadPdfBtn = document.getElementById('downloadPdfBtn');
            if (downloadPdfBtn) {
                const originalGeneratePDF = downloadPdfBtn.onclick;
                downloadPdfBtn.onclick = function() {
                    // Make sure visible members are reflected in PDF
                    updateVisibleMembersForPDF();
                    // Then call the original function
                    if (typeof originalGeneratePDF === 'function') {
                        originalGeneratePDF();
                    }
                };
            }
        }
        
        function formatDateForInput(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }
        
        function resetFilters() {
            statusFilter.value = 'all';
            const today = new Date();
            const sixMonthsAgo = new Date();
            sixMonthsAgo.setMonth(today.getMonth() - 6);
            
            startDateFilter.value = formatDateForInput(sixMonthsAgo);
            endDateFilter.value = formatDateForInput(today);
            
            applyFilters();
        }
        
        function applyFilters() {
            const statusValue = statusFilter.value;
            const startDate = startDateFilter.value ? new Date(startDateFilter.value) : null;
            const endDate = endDateFilter.value ? new Date(endDateFilter.value) : null;
            
            const rows = membersTable.querySelectorAll('tbody tr.member-row');
            let visibleCount = 0;
            
            rows.forEach(row => {
                const rowStatus = row.getAttribute('data-status');
                const rowStartDate = row.getAttribute('data-start-date') ? new Date(row.getAttribute('data-start-date')) : null;
                
                let visible = true;
                
                // Apply status filter
                if (statusValue !== 'all' && rowStatus !== statusValue) {
                    visible = false;
                }
                
                // Apply date filters
                if (visible && startDate && rowStartDate && rowStartDate < startDate) {
                    visible = false;
                }
                
                if (visible && endDate && rowStartDate && rowStartDate > endDate) {
                    visible = false;
                }
                
                // Show/hide row
                row.style.display = visible ? '' : 'none';
                
                if (visible) {
                    visibleCount++;
                }
            });
            
            // Show "No data" message if no visible rows
            const noDataRow = membersTable.querySelector('.no-data');
            if (visibleCount === 0 && !noDataRow) {
                const tbody = membersTable.querySelector('tbody');
                const tr = document.createElement('tr');
                tr.className = 'no-data-row';
                const td = document.createElement('td');
                td.className = 'no-data';
                td.setAttribute('colspan', '5');
                td.textContent = 'No members match the selected filters.';
                tr.appendChild(td);
                tbody.appendChild(tr);
            } else if (visibleCount > 0) {
                const noDataRow = membersTable.querySelector('.no-data-row');
                if (noDataRow) {
                    noDataRow.remove();
                }
            }
        }
        
        function updateVisibleMembersForPDF() {
            // This function sets a data attribute to track which members are currently visible
            // The PDF generation code can then use this to include only filtered members
            const rows = membersTable.querySelectorAll('tbody tr.member-row');
            rows.forEach(row => {
                const isVisible = row.style.display !== 'none';
                row.setAttribute('data-visible-for-pdf', isVisible ? 'true' : 'false');
            });
        }
    });
    </script>
</body>
</html>