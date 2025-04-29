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
        // Member filtering for gym_analytics.php
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
                let activeCount = 0;
                let inactiveCount = 0;
                let totalVisible = 0;
                
                rows.forEach(row => {
                    const rowStatus = row.getAttribute('data-status');
                    const regDate = row.getAttribute('data-reg-date') ? new Date(row.getAttribute('data-reg-date')) : null;
                    
                    let visible = true;
                    
                    // Apply status filter
                    if (statusValue !== 'all' && rowStatus !== statusValue) {
                        visible = false;
                    }
                    
                    // Apply date filters to registration date
                    if (visible && startDate && regDate && regDate < startDate) {
                        visible = false;
                    }
                    
                    if (visible && endDate && regDate && regDate > endDate) {
                        visible = false;
                    }
                    
                    // Show/hide row
                    row.style.display = visible ? '' : 'none';
                    
                    // Update counters
                    if (visible) {
                        totalVisible++;
                        if (rowStatus === 'active') {
                            activeCount++;
                        } else if (rowStatus === 'inactive') {
                            inactiveCount++;
                        }
                    }
                });
                
                // Update the count displays
                const activeCountElem = document.querySelector('.stat-pill.active .value');
                const inactiveCountElem = document.querySelector('.stat-pill.inactive .value');
                const totalCountElem = document.querySelector('.stat-pill.total .value');
                
                if (activeCountElem) activeCountElem.textContent = activeCount;
                if (inactiveCountElem) inactiveCountElem.textContent = inactiveCount;
                if (totalCountElem) totalCountElem.textContent = totalVisible;
                
                // Show/hide no data message
                const noDataRow = membersTable.querySelector('.no-data-row');
                if (totalVisible === 0 && !noDataRow) {
                    const tbody = membersTable.querySelector('tbody');
                    const tr = document.createElement('tr');
                    tr.className = 'no-data-row';
                    const td = document.createElement('td');
                    td.className = 'no-data';
                    td.setAttribute('colspan', '6');
                    td.textContent = 'No members match the selected filters.';
                    tr.appendChild(td);
                    tbody.appendChild(tr);
                } else if (totalVisible > 0 && noDataRow) {
                    noDataRow.remove();
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

    const downloadPdfBtn = document.getElementById('downloadPdfBtn');
            if (downloadPdfBtn) {
                // Remove any existing event listeners first
                const newBtn = downloadPdfBtn.cloneNode(true);
                downloadPdfBtn.parentNode.replaceChild(newBtn, downloadPdfBtn);
                
                // Add the correct event listener
                newBtn.addEventListener('click', function() {
                    if (typeof generatePDF === 'function') {
                        generatePDF();
                    }
                });
            }
            
            // Initialize member filtering
            initializeMemberFiltering();
        });

        // Member filtering functionality
        function initializeMemberFiltering() {
            // For gym_analytics.php and gym_detailed_analytics.php
            const statusFilter = document.querySelectorAll('#memberStatusFilter, #filter-active-members, #filter-inactive-members');
            const startDateInput = document.querySelectorAll('#startDateFilter, #start-date-filter, #member-start-date');
            const endDateInput = document.querySelectorAll('#endDateFilter, #end-date-filter, #member-end-date');
            const resetBtn = document.querySelectorAll('#resetFilters, #reset-filters, #reset-member-filters');
            
            // Set initial date range (past 12 months)
            const today = new Date();
            const twelveMonthsAgo = new Date();
            twelveMonthsAgo.setMonth(today.getMonth() - 12);
            
            const dateInputs = [...startDateInput, ...endDateInput].filter(el => el);
            dateInputs.forEach(input => {
                if (input.id.includes('start')) {
                    input.value = formatDateForInput(twelveMonthsAgo);
                } else {
                    input.value = formatDateForInput(today);
                }
            });
            
            // Add event listeners to all filter elements
            statusFilter.forEach(filter => {
                if (filter) {
                    filter.addEventListener('change', applyFilters);
                }
            });
            
            dateInputs.forEach(input => {
                if (input) {
                    input.addEventListener('change', applyFilters);
                }
            });
            
            resetBtn.forEach(btn => {
                if (btn) {
                    btn.addEventListener('click', resetFilters);
                }
            });
            
            // Apply initial filtering
            applyFilters();
        }

        // Helper function to format date for input fields
        function formatDateForInput(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }

        // Apply filters function - works for both dropdown and checkbox approaches
        function applyFilters() {
            // Get the members table - different pages may use different table IDs
            const membersTable = document.querySelector('#membersTable, #allMembersTable');
            if (!membersTable) return;
            
            // Determine filter state based on available controls
            let showActive = true;
            let showInactive = true;
            let startDate = null;
            let endDate = null;
            let gymId = 'all';
            
            // Check for status dropdown
            const statusDropdown = document.getElementById('memberStatusFilter');
            if (statusDropdown) {
                const statusValue = statusDropdown.value;
                showActive = statusValue === 'all' || statusValue === 'active';
                showInactive = statusValue === 'all' || statusValue === 'inactive';
            }
            
            // Check for status checkboxes
            const activeCheck = document.getElementById('filter-active-members');
            const inactiveCheck = document.getElementById('filter-inactive-members');
            if (activeCheck) showActive = activeCheck.checked;
            if (inactiveCheck) showInactive = inactiveCheck.checked;
            
            // Get date filters - try different possible IDs
            const startInput = document.querySelector('#startDateFilter, #start-date-filter, #member-start-date');
            const endInput = document.querySelector('#endDateFilter, #end-date-filter, #member-end-date');
            
            if (startInput && startInput.value) {
                startDate = new Date(startInput.value);
            }
            if (endInput && endInput.value) {
                endDate = new Date(endInput.value);
            }
            
            // Check for gym filter (specific to all_gyms_analytics.php)
            const gymFilter = document.getElementById('gym-filter');
            if (gymFilter) {
                gymId = gymFilter.value;
            }
            
            // Apply filters to the rows
            const rows = membersTable.querySelectorAll('tbody tr.member-row');
            let activeCount = 0;
            let inactiveCount = 0;
            let totalCount = 0;
            
            rows.forEach(row => {
                const rowStatus = row.getAttribute('data-status');
                const rowGymId = row.getAttribute('data-gym-id'); // May be null for gym-specific pages
                const regDate = row.getAttribute('data-reg-date') ? new Date(row.getAttribute('data-reg-date')) : null;
                
                let visible = true;
                
                // Apply status filter
                if ((rowStatus === 'active' && !showActive) || (rowStatus === 'inactive' && !showInactive)) {
                    visible = false;
                }
                
                // Apply gym filter if available
                if (visible && gymId !== 'all' && rowGymId && rowGymId !== gymId) {
                    visible = false;
                }
                
                // Apply date range filter
                if (visible && startDate && regDate && regDate < startDate) {
                    visible = false;
                }
                
                if (visible && endDate && regDate && regDate > endDate) {
                    visible = false;
                }
                
                // Show/hide row
                row.style.display = visible ? '' : 'none';
                
                // Update counters for visible rows
                if (visible) {
                    totalCount++;
                    if (rowStatus === 'active') {
                        activeCount++;
                    } else if (rowStatus === 'inactive') {
                        inactiveCount++;
                    }
                }
            });
            
            // Update count displays (handle different display elements across pages)
            updateFilteredCounts(activeCount, inactiveCount, totalCount);
            
            // Show no-data message if no visible rows
            displayNoDataMessage(totalCount, membersTable);
            
            // Store filtered data for PDF export
            storeFilteredData(activeCount, inactiveCount, totalCount, rows);
        }

        // Reset filters to default values
        function resetFilters() {
            // Reset status filters
            const statusDropdown = document.getElementById('memberStatusFilter');
            if (statusDropdown) statusDropdown.value = 'all';
            
            const activeCheck = document.getElementById('filter-active-members');
            const inactiveCheck = document.getElementById('filter-inactive-members');
            if (activeCheck) activeCheck.checked = true;
            if (inactiveCheck) inactiveCheck.checked = true;
            
            // Reset date inputs
            const today = new Date();
            const twelveMonthsAgo = new Date();
            twelveMonthsAgo.setMonth(today.getMonth() - 12);
            
            const startInput = document.querySelector('#startDateFilter, #start-date-filter, #member-start-date');
            const endInput = document.querySelector('#endDateFilter, #end-date-filter, #member-end-date');
            
            if (startInput) startInput.value = formatDateForInput(twelveMonthsAgo);
            if (endInput) endInput.value = formatDateForInput(today);
            
            // Reset gym filter if present
            const gymFilter = document.getElementById('gym-filter');
            if (gymFilter) gymFilter.value = 'all';
            
            // Apply the reset filters
            applyFilters();
        }

        // Update count displays across all possible element types
        function updateFilteredCounts(activeCount, inactiveCount, totalCount) {
            // Try different element selectors used across the various pages
            
            // Active count
            updateElement('.member-stat-card.active .stat-value', activeCount);
            updateElement('.stat-pill.active .value', activeCount);
            updateElement('#active-members-count', activeCount);
            updateElement('#active-count', activeCount);
            
            // Inactive count
            updateElement('.member-stat-card.inactive .stat-value', inactiveCount);
            updateElement('.stat-pill.inactive .value', inactiveCount);
            updateElement('#inactive-members-count', inactiveCount);
            updateElement('#inactive-count', inactiveCount);
            
            // Total count
            updateElement('.member-stat-card.total .stat-value', totalCount);
            updateElement('.stat-pill.total .value', totalCount);
            updateElement('#total-members-count', totalCount);
            updateElement('#total-count', totalCount);
        }

        // Helper to update element text if element exists
        function updateElement(selector, value) {
            const element = document.querySelector(selector);
            if (element) {
                element.textContent = value;
            }
        }

        // Show no data message if needed
        function displayNoDataMessage(visibleCount, table) {
            // Remove existing message if any
            const existingMessage = table.querySelector('.no-data-row');
            if (existingMessage) {
                existingMessage.remove();
            }
            
            // Add message if no visible rows
            if (visibleCount === 0) {
                const tbody = table.querySelector('tbody');
                if (tbody) {
                    const tr = document.createElement('tr');
                    tr.className = 'no-data-row';
                    
                    const td = document.createElement('td');
                    // Get number of columns from thead
                    const colCount = table.querySelector('thead tr th')?.length || 6;
                    td.setAttribute('colspan', colCount);
                    td.className = 'no-data';
                    td.textContent = 'No members match the selected filters.';
                    
                    tr.appendChild(td);
                    tbody.appendChild(tr);
                }
            }
        }

        // Store filtered data for PDF export
        function storeFilteredData(activeCount, inactiveCount, totalCount, rows) {
            // Create global object to store filtered counts for PDF
            window.filteredMemberCounts = {
                active: activeCount,
                inactive: inactiveCount,
                total: totalCount,
                byGym: {}
            };
            
            // If we have gym data, track it for the PDF
            const gymColumn = document.querySelector('#allMembersTable thead th:first-child');
            if (gymColumn && gymColumn.textContent.includes('Gym')) {
                // Process visible rows to group by gym
                Array.from(rows).forEach(row => {
                    if (row.style.display !== 'none') {
                        const gymName = row.cells[0].textContent.trim();
                        const gymId = row.getAttribute('data-gym-id') || gymName; // Use ID if available, otherwise name
                        const status = row.getAttribute('data-status');
                        
                        if (!window.filteredMemberCounts.byGym[gymId]) {
                            window.filteredMemberCounts.byGym[gymId] = {
                                name: gymName,
                                active: 0,
                                inactive: 0,
                                total: 0
                            };
                        }
                        
                        window.filteredMemberCounts.byGym[gymId].total++;
                        
                        if (status === 'active') {
                            window.filteredMemberCounts.byGym[gymId].active++;
                        } else if (status === 'inactive') {
                            window.filteredMemberCounts.byGym[gymId].inactive++;
                        }
                    }
                });
            }
        }

    </script>
</body>
</html>