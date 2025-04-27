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
                        
                        // Check if we need a new page
                        if (currentY > 220) {
                            doc.addPage();
                            currentY = 20;
                        }
                        
                        // Add chart title
                        doc.setFont(pdfFonts.bold);
                        doc.setFontSize(14);
                        doc.text(title, 20, currentY);
                        currentY += 10;
                        
                        // Capture chart canvas
                        const canvas = chart.querySelector('canvas');
                        
                        html2canvas(canvas, {
                            scale: 2, // Better quality
                            backgroundColor: null,
                            logging: false
                        }).then(canvas => {
                            // Add to PDF
                            const imgData = canvas.toDataURL('image/png');
                            const imgWidth = 170;
                            const imgHeight = canvas.height * imgWidth / canvas.width;
                            
                            doc.addImage(imgData, 'PNG', 20, currentY, imgWidth, imgHeight);
                            currentY += imgHeight + 20; // Add space after chart
                            
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
                        if (summaryStats.members > 0) {
                            summary += `A total of ${summaryStats.members} new member registrations have been recorded in the analyzed period. `;
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
                    
                    // Add footer
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
    });
    </script>
</body>
</html>