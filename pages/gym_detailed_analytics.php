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

    // Add this function after the DOM loaded event listener starts
    document.addEventListener('DOMContentLoaded', function() {
        console.log("DOM fully loaded");
        
        // Add the cleanTextForPDF function to standardize values for PDF output
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
        const includeRatings = document.getElementById('include-ratings');
        const includeReviews = document.getElementById('include-reviews');
        const includeRevenue = document.getElementById('include-revenue');
        const includeCharts = document.getElementById('include-charts');
        
        // Initialize filters to ensure data appears immediately
        function initializeFilters() {
            console.log("Initializing filters");
            // Make sure all checkboxes are checked by default
            [includeMembers, includeRatings, includeReviews, includeRevenue, includeCharts].forEach(checkbox => {
                if (checkbox) checkbox.checked = true;
            });
            
            // Apply filters to make all data visible
            applyFilters();
        }
        
        // Apply filters to the view
        function applyFilters() {
            console.log("Applying filters");
            // Filter metric cards
            const metricCards = document.querySelectorAll('.metric-card');
            metricCards.forEach(card => {
                const type = card.getAttribute('data-type');
                
                if (type === 'members') {
                    card.setAttribute('data-hidden', !includeMembers.checked);
                } else if (type === 'ratings') {
                    card.setAttribute('data-hidden', !includeRatings.checked);
                } else if (type === 'reviews') {
                    card.setAttribute('data-hidden', !includeReviews.checked);
                } else if (type === 'revenue') {
                    card.setAttribute('data-hidden', !includeRevenue.checked);
                }
            });
            
            // Filter charts
            const chartContainers = document.querySelectorAll('.chart-container');
            chartContainers.forEach(chart => {
                const type = chart.getAttribute('data-type');
                
                if (type === 'members') {
                    chart.setAttribute('data-hidden', !includeMembers.checked || !includeCharts.checked);
                } else if (type === 'ratings') {
                    chart.setAttribute('data-hidden', !includeRatings.checked || !includeCharts.checked);
                } else if (type === 'revenue') {
                    chart.setAttribute('data-hidden', !includeRevenue.checked || !includeCharts.checked);
                }
            });
        }
        
        // Call initialize on page load
        initializeFilters();
        
        // Add filter change listeners
        if (includeMembers) includeMembers.addEventListener('change', applyFilters);
        if (includeRatings) includeRatings.addEventListener('change', applyFilters);
        if (includeReviews) includeReviews.addEventListener('change', applyFilters);
        if (includeRevenue) includeRevenue.addEventListener('change', applyFilters);
        if (includeCharts) includeCharts.addEventListener('change', applyFilters);
        
        if (downloadPdfBtn) {
            console.log("Adding click event to PDF button");
            downloadPdfBtn.addEventListener('click', generatePDF);
        }
        
        function generatePDF() {
            console.log("Generating PDF");
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
                const gymName = document.querySelector('.gym-info h2').innerText;
                const date = new Date().toLocaleDateString();
                const gymLocation = document.querySelector('.gym-info p').innerText.replace(/^.*?\s/, ""); // Remove icon
                
                // Collect stats for summary
                const summaryStats = {
                    members: "",
                    rating: "",
                    reviews: "",
                    revenue: ""
                };
                
                // Get stats from metric cards for summary
                document.querySelectorAll('.metric-card').forEach(card => {
                    const type = card.getAttribute('data-type');
                    const value = card.querySelector('.number').innerText;
                    
                    if (type === 'members') {
                        summaryStats.members = value;
                    } else if (type === 'ratings') {
                        summaryStats.rating = value;
                    } else if (type === 'reviews') {
                        summaryStats.reviews = value;
                    } else if (type === 'revenue') {
                        summaryStats.revenue = value;
                    }
                });
                
                // Set up PDF
                doc.setFont(pdfFonts.bold);
                doc.setFontSize(22);
                doc.text(`${gymName} - Analytics Report`, 105, 20, { align: 'center' });
                
                doc.setFont(pdfFonts.normal);
                doc.setFontSize(12);
                doc.text(`Generated on: ${date}`, 105, 30, { align: 'center' });
                doc.text(`Location: ${gymLocation}`, 105, 35, { align: 'center' });
                
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
                
                let currentY = 60;
                
                // Add gym key metrics
                const visibleMetricCards = document.querySelectorAll('.metric-card[data-hidden="false"]');
                if (visibleMetricCards.length > 0) {
                    doc.setFont(pdfFonts.bold);
                    doc.setFontSize(16);
                    doc.text('Key Performance Metrics', 20, currentY);
                    currentY += 10;
                    
                    doc.setFont(pdfFonts.normal);
                    doc.setFontSize(12);
                    
                    visibleMetricCards.forEach((card) => {
                        const label = card.querySelector('h3').innerText;
                        let value = card.querySelector('.number').innerText;
                        
                        // Clean up the value based on what type of metric it is
                        if (label.includes('Rating')) {
                            value = cleanTextForPDF(value, 'rating');
                            doc.text(`${label}: ${value}`, 20, currentY);
                        } 
                        else if (label.includes('Revenue')) {
                            value = cleanTextForPDF(value, 'revenue');
                            doc.text(`${label}: ${value}`, 20, currentY);
                        }
                        else {
                            doc.text(`${label}: ${value}`, 20, currentY);
                        }
                        
                        currentY += 10;
                    });
                    
                    currentY += 10; // Add more space after metrics
                }
                
                // Process charts if selected
                if (includeCharts && includeCharts.checked) {
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
                            
                            // Always start each chart on a new page for better layout
                            doc.addPage();
                            currentY = 20;
                            
                            // Add chart title
                            doc.setFont(pdfFonts.bold);
                            doc.setFontSize(14);
                            doc.text(title, 105, currentY, { align: 'center' });
                            currentY += 10;
                            
                            // Capture chart canvas
                            const canvas = chart.querySelector('canvas');
                            
                            html2canvas(canvas, {
                                scale: 2, // Better quality
                                backgroundColor: null,
                                logging: false
                            }).then(canvas => {
                                // Add to PDF - center the chart horizontally on the page
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
                } else {
                    // Charts not included, add summary page
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
                    let summary = `This report presents a comprehensive analysis of ${gymName} located at ${gymLocation}. `;
                    
                    if (summaryStats.members) {
                        summary += `The gym currently has ${summaryStats.members} active members. `;
                    }
                    
                    if (summaryStats.rating) {
                        // Clean up rating for summary
                        summaryStats.rating = cleanTextForPDF(summaryStats.rating, 'rating');
                        summary += `The average customer satisfaction rating is ${summaryStats.rating} out of 5, `;
                        const ratingValue = parseFloat(summaryStats.rating);
                        if (ratingValue >= 4) {
                            summary += "indicating excellent customer satisfaction. ";
                        } else if (ratingValue >= 3) {
                            summary += "showing good customer satisfaction. ";
                        } else {
                            summary += "suggesting there are areas for improvement in customer satisfaction. ";
                        }
                    }
                    
                    if (summaryStats.reviews) {
                        summary += `Based on ${summaryStats.reviews} customer reviews, `;
                    }
                    
                    if (summaryStats.revenue) {
                        // Clean up revenue for summary
                        summaryStats.revenue = cleanTextForPDF(summaryStats.revenue, 'revenue');
                        summary += `the gym has generated a total revenue of ${summaryStats.revenue}. `;
                    }
                    
                    summary += `\n\nThis report was generated on ${date} and includes `;
                    if (includeCharts && includeCharts.checked) {
                        summary += "visualizations of key performance indicators through charts and graphs. ";
                    }
                    summary += "The data provides valuable insights for gym management to make informed decisions ";
                    summary += "regarding membership growth strategies, pricing optimization, and service improvements.";
                    
                    // Add the summary paragraph to the PDF with proper line breaks
                    const splitText = doc.splitTextToSize(summary, 170);
                    doc.text(splitText, 20, summaryY);
                    
                    // Finalize PDF - add footer and save
                    const pageCount = doc.getNumberOfPages();
                    for (let i = 1; i <= pageCount; i++) {
                        doc.setPage(i);
                        doc.setFontSize(10);
                        doc.setTextColor(150);
                        doc.text(`${gymName} Analytics Report - Page ${i} of ${pageCount}`, 105, 285, { align: 'center' });
                    }
                    
                    // Remove loading overlay and save PDF
                    document.body.removeChild(loadingOverlay);
                    
                    // Generate filename with gym name, date and filters
                    const filterSuffix = filterText.join('_');
                    const filename = `${gymName.replace(/\s+/g, '_')}_Analytics_${filterSuffix}_${date.replace(/\//g, '-')}.pdf`;
                    doc.save(filename);
                }
            }, 500);
        }
    });
    </script>
</body>
</html>
