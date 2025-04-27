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
            <div class="section-header">
                <h2><i class="fas fa-info-circle"></i> Platform Overview</h2>
                <div class="report-actions">
                    <div class="filter-dropdown">
                        <button class="filter-btn"><i class="fas fa-filter"></i> Filter Report</button>
                        <div class="filter-menu">
                            <div class="filter-item">
                                <input type="checkbox" id="include-gyms" checked>
                                <label for="include-gyms">Gyms</label>
                            </div>
                            <div class="filter-item">
                                <input type="checkbox" id="include-members" checked>
                                <label for="include-members">Members</label>
                            </div>
                            <div class="filter-item">
                                <input type="checkbox" id="include-ratings" checked>
                                <label for="include-ratings">Ratings</label>
                            </div>
                            <div class="filter-item">
                                <input type="checkbox" id="include-revenue" checked>
                                <label for="include-revenue">Revenue</label>
                            </div>
                        </div>
                    </div>
                    <button id="downloadPdfBtn" class="pdf-download-btn">
                        <i class="fas fa-file-pdf"></i> Export as PDF
                    </button>
                </div>
            </div>
            <div class="stats-overview">
                <div class="stat-card" data-type="gyms">
                    <i class="fas fa-dumbbell"></i>
                    <h3>Total Gyms</h3>
                    <p class="stat-number"><?php echo number_format($overall_stats['total_gyms']); ?></p>
                </div>
                <div class="stat-card" data-type="members">
                    <i class="fas fa-users"></i>
                    <h3>Total Members</h3>
                    <p class="stat-number"><?php echo number_format($overall_stats['total_members']); ?></p>
                </div>
                <div class="stat-card" data-type="ratings">
                    <i class="fas fa-star"></i>
                    <h3>Average Rating</h3>
                    <p class="stat-number"><?php echo number_format($overall_stats['overall_rating'], 1); ?> ⭐</p>
                </div>
                <div class="stat-card highlight" data-type="revenue">
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
                    <div class="gym-card" data-type="gyms" onclick="window.location='gym_detailed_analytics.php?gym_id=<?php echo $gym['gym_id']; ?>'">
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

    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
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

    .gym-card[data-hidden="true"] {
        display: none;
    }

    .stat-card[data-hidden="true"] {
        display: none;
    }
    </style>

    <!-- PDF Generation Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
            const downloadPdfBtn = document.getElementById('downloadPdfBtn');
            // Filter checkboxes
            const includeGyms = document.getElementById('include-gyms');
            const includeMembers = document.getElementById('include-members');
            const includeRatings = document.getElementById('include-ratings');
            const includeRevenue = document.getElementById('include-revenue');
            
            // Initialize all checkboxes as checked
            function initializeFilters() {
                // Make sure all checkboxes are checked by default
                [includeGyms, includeMembers, includeRatings, includeRevenue].forEach(checkbox => {
                    checkbox.checked = true;
                });
                
                // Apply filters to make all data visible
                applyFilters();
            }
            
            // Apply filters to the view (not just for PDF)
            function applyFilters() {
                // Filter stat cards
                const statCards = document.querySelectorAll('.stat-card');
                statCards.forEach(card => {
                    const type = card.getAttribute('data-type');
                    
                    if (type === 'gyms') {
                        card.setAttribute('data-hidden', !includeGyms.checked);
                    } else if (type === 'members') {
                        card.setAttribute('data-hidden', !includeMembers.checked);
                    } else if (type === 'ratings') {
                        card.setAttribute('data-hidden', !includeRatings.checked);
                    } else if (type === 'revenue') {
                        card.setAttribute('data-hidden', !includeRevenue.checked);
                    }
                });
                
                // Filter gym cards if not including gyms
                const gymCards = document.querySelectorAll('.gym-card');
                gymCards.forEach(card => {
                    card.setAttribute('data-hidden', !includeGyms.checked);
                });
            }
            
            // Call initialize on page load
            initializeFilters();
            
            // Add filter change listeners
            [includeGyms, includeMembers, includeRatings, includeRevenue].forEach(checkbox => {
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
                    
                    // Setup PDF
                    const date = new Date().toLocaleDateString();
                    
                    doc.setFont(pdfFonts.bold);
                    doc.setFontSize(22);
                    doc.text('FitHub Platform Analytics Report', 105, 20, { align: 'center' });
                    
                    doc.setFont(pdfFonts.normal);
                    doc.setFontSize(12);
                    doc.text(`Generated on: ${date}`, 105, 30, { align: 'center' });
                    
                    doc.setFontSize(10);
                    doc.text('Filters applied:', 20, 40);
                    const filterText = [];
                    if (includeGyms.checked) filterText.push('Gyms');
                    if (includeMembers.checked) filterText.push('Members');
                    if (includeRatings.checked) filterText.push('Ratings');
                    if (includeRevenue.checked) filterText.push('Revenue');
                    doc.text(`Included: ${filterText.join(', ')}`, 20, 45);
                    
                    let currentY = 55;
                    
                    // Collect stats for summary
                    const summaryStats = {
                        totalGyms: 0,
                        totalMembers: 0,
                        avgRating: 0,
                        totalRevenue: 0
                    };
                    
                    // Add platform overview
                    if (filterText.length > 0) {
                        doc.setFont(pdfFonts.bold);
                        doc.setFontSize(16);
                        doc.text('Platform Overview', 20, currentY);
                        currentY += 10;
                        
                        doc.setFont(pdfFonts.normal);
                        doc.setFontSize(12);
                        
                        const statCards = document.querySelectorAll('.stats-overview .stat-card[data-hidden="false"]');
                        statCards.forEach((card) => {
                            const label = card.querySelector('h3').innerText;
                            let value = card.querySelector('.stat-number').innerText;
                            
                            // Fix currency and rating symbols
                            if (label.includes('Revenue')) {
                                // Replace ± with ₱ for proper peso sign
                                value = value.replace('±', '₱').trim();
                                summaryStats.totalRevenue = value;
                            } else if (label.includes('Rating')) {
                                // Remove any unusual characters from rating
                                value = value.replace('+P', '').replace('±', '').trim();
                                summaryStats.avgRating = value;
                            } else if (label.includes('Gyms')) {
                                summaryStats.totalGyms = value;
                            } else if (label.includes('Members')) {
                                summaryStats.totalMembers = value;
                            }
                            
                            doc.text(`${label}: ${value}`, 20, currentY);
                            currentY += 10;
                        });
                        
                        currentY += 10; // Add more space after overview
                    }
                    
                    // Add gym listings
                    if (includeGyms.checked) {
                        // Add a section title for gyms
                        doc.setFont(pdfFonts.bold);
                        doc.setFontSize(16);
                        doc.text('Registered Gyms', 20, currentY);
                        currentY += 10;
                        
                        doc.setFont(pdfFonts.normal);
                        doc.setFontSize(12);
                        
                        const gyms = document.querySelectorAll('.gym-card[data-hidden="false"]');
                        
                        gyms.forEach((gym, index) => {
                            // Check if we need a new page
                            if (currentY > 250) {
                                doc.addPage();
                                currentY = 20;
                            }
                            
                            // Get gym data
                            const gymName = gym.querySelector('h3').innerText;
                            const location = gym.querySelector('.location').innerText;
                            
                            // Get stats
                            const stats = [];
                            if (includeMembers.checked) {
                                const memberStat = gym.querySelector('.stat:nth-child(1)');
                                if (memberStat) {
                                    stats.push({
                                        label: memberStat.querySelector('label').innerText,
                                        value: memberStat.querySelector('span').innerText
                                    });
                                }
                            }
                            if (includeRatings.checked) {
                                const ratingStat = gym.querySelector('.stat:nth-child(2)');
                                if (ratingStat) {
                                    stats.push({
                                        label: ratingStat.querySelector('label').innerText,
                                        value: ratingStat.querySelector('span').innerText
                                    });
                                }
                            }
                            if (includeRevenue.checked) {
                                const revenueStat = gym.querySelector('.stat:nth-child(3)');
                                if (revenueStat) {
                                    stats.push({
                                        label: revenueStat.querySelector('label').innerText,
                                        value: revenueStat.querySelector('span').innerText
                                    });
                                }
                            }
                            
                            // Add gym name
                            doc.setFont(pdfFonts.bold);
                            doc.setFontSize(14);
                            doc.text(`${index + 1}. ${gymName}`, 20, currentY);
                            currentY += 7;
                            
                            // Add location
                            doc.setFont(pdfFonts.normal);
                            doc.setFontSize(10);
                            doc.text(`Location: ${location}`, 25, currentY);
                            currentY += 7;
                            
                            // Add stats
                            if (stats.length > 0) {
                                doc.text('Statistics:', 25, currentY);
                                currentY += 5;
                                
                                stats.forEach((stat) => {
                                    let value = stat.value;
                                    
                                    // Fix currency and rating symbols for each gym
                                    if (stat.label.includes('Revenue')) {
                                        value = `₱${value}`;
                                    }
                                    
                                    doc.text(`• ${stat.label}: ${value}`, 30, currentY);
                                    currentY += 5;
                                });
                            }
                            
                            // Add separator
                            doc.setDrawColor(200, 200, 200);
                            doc.line(20, currentY, 190, currentY);
                            
                            currentY += 10;
                        });
                    }
                    
                    // Add a summary page at the end
                    doc.addPage();
                    doc.setFont(pdfFonts.bold);
                    doc.setFontSize(16);
                    doc.text("Analytics Summary", 105, 20, { align: 'center' });
                    
                    doc.setFont(pdfFonts.normal);
                    doc.setFontSize(12);
                    let summaryY = 40;
                    
                    // Create a paragraph summary based on collected stats
                    let summary = `This report presents a comprehensive overview of the FitHub platform performance. `;
                    
                    if (summaryStats.totalGyms) {
                        summary += `The platform currently hosts ${summaryStats.totalGyms} registered gyms. `;
                    }
                    
                    if (summaryStats.totalMembers) {
                        summary += `There are ${summaryStats.totalMembers} active members across all gyms. `;
                    }
                    
                    if (summaryStats.avgRating) {
                        summary += `The average customer satisfaction rating is ${summaryStats.avgRating} out of 5 stars, `;
                        if (parseFloat(summaryStats.avgRating) >= 4) {
                            summary += "indicating excellent overall customer satisfaction. ";
                        } else if (parseFloat(summaryStats.avgRating) >= 3) {
                            summary += "showing good overall customer satisfaction. ";
                        } else {
                            summary += "suggesting there are areas for improvement in customer satisfaction. ";
                        }
                    }
                    
                    if (summaryStats.totalRevenue) {
                        summary += `The platform has generated a total revenue of ${summaryStats.totalRevenue}. `;
                    }
                    
                    summary += `\n\nThis report was generated on ${date} and includes `;
                    summary += "detailed metrics on gym performance, membership distribution, and revenue generation. ";
                    summary += "The data provides insights into overall platform health and can be used for strategic business planning.";
                    
                    // Add the summary paragraph to the PDF with proper line breaks
                    const splitText = doc.splitTextToSize(summary, 170);
                    doc.text(splitText, 20, summaryY);
                    
                    // Add footer with page numbers
                    const pageCount = doc.getNumberOfPages();
                    for (let i = 1; i <= pageCount; i++) {
                        doc.setPage(i);
                        doc.setFont(pdfFonts.normal);
                        doc.setFontSize(10);
                        doc.setTextColor(150);
                        doc.text(`FitHub Analytics Report - Page ${i} of ${pageCount}`, 105, 285, { align: 'center' });
                    }
                    
                    // Remove loading overlay and save the PDF
                    document.body.removeChild(loadingOverlay);
                    
                    // Generate filename with date and filters
                    const filterSuffix = filterText.join('_');
                    const filename = `FitHub_Analytics_${filterSuffix}_${date.replace(/\//g, '-')}.pdf`;
                    doc.save(filename);
                }, 500);
                }
            });
        </script>
    </body>
</html>