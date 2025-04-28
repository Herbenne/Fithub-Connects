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

        <section class="all-members-section" data-type="members">
            <div class="section-header">
                <h2><i class="fas fa-users"></i> Platform Members</h2>
                <div class="filter-dropdown">
                    <button class="member-filter-btn"><i class="fas fa-filter"></i> Filter Members</button>
                    <div class="member-filter-menu">
                        <div class="filter-group">
                            <label>Status:</label>
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
                            <label>Date Range:</label>
                            <div class="date-inputs">
                                <div class="date-field">
                                    <label for="member-start-date">From:</label>
                                    <input type="date" id="member-start-date">
                                </div>
                                <div class="date-field">
                                    <label for="member-end-date">To:</label>
                                    <input type="date" id="member-end-date">
                                </div>
                            </div>
                        </div>
                        <div class="filter-group">
                            <label>Gym:</label>
                            <select id="gym-filter">
                                <option value="all">All Gyms</option>
                                <?php
                                // Reset the pointer for gyms result
                                $gyms_result->data_seek(0);
                                while ($gym = $gyms_result->fetch_assoc()): ?>
                                    <option value="<?php echo $gym['gym_id']; ?>">
                                        <?php echo htmlspecialchars($gym['gym_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <button id="reset-member-filters" class="reset-filters-btn">
                            Reset Filters
                        </button>
                    </div>
                </div>
            </div>

            <div class="member-stats-summary">
                <div class="stat-box active">
                    <div class="stat-icon"><i class="fas fa-user-check"></i></div>
                    <div class="stat-content">
                        <h3>Active Members</h3>
                        <p class="stat-number"><?php echo number_format($overall_stats['active_members']); ?></p>
                    </div>
                </div>
                <div class="stat-box inactive">
                    <div class="stat-icon"><i class="fas fa-user-times"></i></div>
                    <div class="stat-content">
                        <h3>Inactive Members</h3>
                        <p class="stat-number"><?php echo number_format($overall_stats['inactive_members']); ?></p>
                    </div>
                </div>
                <div class="stat-box total">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-content">
                        <h3>Total Members</h3>
                        <p class="stat-number"><?php echo number_format($overall_stats['total_members']); ?></p>
                    </div>
                </div>
            </div>

            <div class="all-members-table-container">
                <table id="allMembersTable" class="all-members-table">
                    <thead>
                        <tr>
                            <th>Gym</th>
                            <th>Member Name</th>
                            <th>Membership Plan</th>
                            <th>Amount</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($all_members_result && $all_members_result->num_rows > 0): ?>
                            <?php while($member = $all_members_result->fetch_assoc()): ?>
                                <tr class="member-row <?php echo strtolower($member['status']); ?>" 
                                    data-gym-id="<?php echo $member['gym_id']; ?>"
                                    data-start-date="<?php echo $member['start_date']; ?>"
                                    data-end-date="<?php echo $member['end_date']; ?>"
                                    data-status="<?php echo strtolower($member['status']); ?>">
                                    <td><?php echo htmlspecialchars($member['gym_name']); ?></td>
                                    <td><?php echo htmlspecialchars($member['member_name']); ?></td>
                                    <td><?php echo htmlspecialchars($member['plan_name']); ?></td>
                                    <td>₱<?php echo number_format($member['price'], 2); ?></td>
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
                                <td colspan="7" class="no-data">No member data available.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <style>
        /* All Members Section Styles */
        .all-members-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-top: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .all-members-section h2 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .all-members-section h2 i {
            color: #4CAF50;
        }

        .member-filter-btn {
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

        .member-filter-btn:hover {
            background-color: #3d8b40;
        }

        .member-filter-menu {
            display: none;
            position: absolute;
            right: 0;
            background-color: white;
            width: 300px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            padding: 15px;
            z-index: 10;
            border-radius: 4px;
        }

        .filter-dropdown:hover .member-filter-menu {
            display: block;
        }

        .filter-group {
            margin-bottom: 15px;
        }

        .filter-group > label {
            display: block;
            font-weight: 500;
            margin-bottom: 8px;
            color: #333;
        }

        .checkbox-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .date-inputs {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .date-field {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .date-field input {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        #gym-filter {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .reset-filters-btn {
            width: 100%;
            padding: 8px 0;
            background-color: #f0f0f0;
            border: 1px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
            margin-top: 10px;
        }

        .reset-filters-btn:hover {
            background-color: #e0e0e0;
        }

        .member-stats-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }

        .stat-box {
            display: flex;
            align-items: center;
            padding: 20px;
            border-radius: 8px;
            background-color: #f8f9fa;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .stat-box.active {
            border-left: 4px solid #4CAF50;
        }

        .stat-box.inactive {
            border-left: 4px solid #F44336;
        }

        .stat-box.total {
            border-left: 4px solid #2196F3;
        }

        .stat-icon {
            font-size: 2rem;
            margin-right: 20px;
        }

        .stat-box.active .stat-icon {
            color: #4CAF50;
        }

        .stat-box.inactive .stat-icon {
            color: #F44336;
        }

        .stat-box.total .stat-icon {
            color: #2196F3;
        }

        .stat-content h3 {
            margin: 0 0 5px 0;
            font-size: 16px;
            color: #666;
        }

        .stat-number {
            font-size: 24px;
            font-weight: bold;
            margin: 0;
            color: #333;
        }

        .all-members-table-container {
            overflow-x: auto;
            margin-top: 20px;
            border: 1px solid #eee;
            border-radius: 8px;
        }

        .all-members-table {
            width: 100%;
            border-collapse: collapse;
        }

        .all-members-table th,
        .all-members-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .all-members-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #555;
        }

        .all-members-table tr:hover {
            background-color: #f8f9fa;
        }

        .status-badge {
            display: inline-block;
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
        .all-members-section[data-hidden="true"] {
            display: none;
        }

        @media (max-width: 768px) {
            .member-stats-summary {
                grid-template-columns: 1fr;
            }
        }
        </style>
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
                                // Clean up revenue value for PDF
                                value = cleanTextForPDF(value, 'revenue');
                                summaryStats.totalRevenue = value;
                            } else if (label.includes('Rating')) {
                                // Clean up rating value for PDF
                                value = cleanTextForPDF(value, 'rating');
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
                                        value = cleanTextForPDF(value, 'revenue');
                                    } else if (stat.label.includes('Rating')) {
                                        value = cleanTextForPDF(value, 'rating');
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

                    // Then add a member breakdown section to the PDF
                    if (includeMembers.checked) {
                        // Add a members section title
                        doc.addPage();
                        doc.setFont(pdfFonts.bold);
                        doc.setFontSize(16);
                        doc.text('Platform Members Overview', 20, 20);
                        currentY = 30;
                        
                        doc.setFont(pdfFonts.normal);
                        doc.setFontSize(12);
                        
                        // Add member statistics
                        const activeCount = window.filteredMemberCounts ? window.filteredMemberCounts.active : parseInt(overall_stats['active_members']);
                        const inactiveCount = window.filteredMemberCounts ? window.filteredMemberCounts.inactive : parseInt(overall_stats['inactive_members']);
                        const totalCount = activeCount + inactiveCount;
                        
                        doc.text(`Active Members: ${activeCount.toLocaleString()}`, 20, currentY);
                        currentY += 8;
                        doc.text(`Inactive Members: ${inactiveCount.toLocaleString()}`, 20, currentY);
                        currentY += 8;
                        doc.text(`Total Members: ${totalCount.toLocaleString()}`, 20, currentY);
                        currentY += 15;
                        
                        // Add gym-by-gym member breakdown if detailed info is available
                        if (window.filteredMemberCounts && window.filteredMemberCounts.byGym) {
                            doc.setFont(pdfFonts.bold);
                            doc.setFontSize(14);
                            doc.text('Membership Distribution by Gym', 20, currentY);
                            currentY += 10;
                            
                            // Create a table header
                            const columns = ["Gym Name", "Active", "Inactive", "Total"];
                            const columnWidths = [100, 25, 25, 25];
                            
                            // Draw table header
                            let xPos = 20;
                            doc.setFillColor(240, 240, 240);
                            doc.rect(xPos, currentY - 8, 175, 12, 'F');
                            
                            columns.forEach((column, index) => {
                                doc.text(column, xPos, currentY);
                                xPos += columnWidths[index];
                            });
                            
                            currentY += 12;
                            doc.setFont(pdfFonts.normal);
                            
                            // Draw each gym's member stats
                            Object.keys(window.filteredMemberCounts.byGym).forEach((gymId, index) => {
                                const gymData = window.filteredMemberCounts.byGym[gymId];
                                
                                // Check if we need a new page
                                if (currentY > 270) {
                                    doc.addPage();
                                    currentY = 20;
                                    
                                    // Redraw header
                                    doc.setFont(pdfFonts.bold);
                                    doc.text('Membership Distribution by Gym (Continued)', 20, currentY);
                                    currentY += 10;
                                    
                                    // Redraw table header
                                    xPos = 20;
                                    doc.setFillColor(240, 240, 240);
                                    doc.rect(xPos, currentY - 8, 175, 12, 'F');
                                    
                                    columns.forEach((column, index) => {
                                        doc.text(column, xPos, currentY);
                                        xPos += columnWidths[index];
                                    });
                                    
                                    currentY += 12;
                                    doc.setFont(pdfFonts.normal);
                                }
                                
                                // Add alternating row background
                                if (index % 2 === 1) {
                                    doc.setFillColor(247, 247, 247);
                                    doc.rect(20, currentY - 8, 175, 12, 'F');
                                }
                                
                                // Draw gym data
                                xPos = 20;
                                
                                // Truncate long gym names
                                const gymName = gymData.name.length > 45 ? gymData.name.substring(0, 42) + '...' : gymData.name;
                                
                                doc.text(gymName, xPos, currentY);
                                xPos += columnWidths[0];
                                
                                doc.text(gymData.active.toString(), xPos, currentY);
                                xPos += columnWidths[1];
                                
                                doc.text(gymData.inactive.toString(), xPos, currentY);
                                xPos += columnWidths[2];
                                
                                doc.text(gymData.total.toString(), xPos, currentY);
                                
                                currentY += 12;
                            });
                        }
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
                        // Include filtered members if available
                        if (window.filteredMemberCounts) {
                            summary += `There are ${window.filteredMemberCounts.active} active members and ${window.filteredMemberCounts.inactive} inactive members, for a total of ${window.filteredMemberCounts.total} members across all gyms. `;
                        } else {
                            summary += `There are ${summaryStats.totalMembers} active members across all gyms. `;
                        }
                    }
                    
                    if (summaryStats.avgRating) {
                        // Clean up any potential formatting issues with the rating
                        const cleanRating = cleanTextForPDF(summaryStats.avgRating.toString(), 'rating');
                        summary += `The average customer satisfaction rating is ${cleanRating} out of 5 stars, `;
                        const ratingValue = parseFloat(cleanRating);
                        if (ratingValue >= 4) {
                            summary += "indicating excellent overall customer satisfaction. ";
                        } else if (ratingValue >= 3) {
                            summary += "showing good overall customer satisfaction. ";
                        } else {
                            summary += "suggesting there are areas for improvement in customer satisfaction. ";
                        }
                    }
                    
                    if (summaryStats.totalRevenue) {
                        // Clean up any potential formatting issues with the revenue
                        const cleanRevenue = cleanTextForPDF(summaryStats.totalRevenue.toString(), 'revenue');
                        summary += `The platform has generated a total revenue of ${cleanRevenue}. `;
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

            // Member filtering logic for all gyms
            const activeFilter = document.getElementById('filter-active-members');
            const inactiveFilter = document.getElementById('filter-inactive-members');
            const startDateFilter = document.getElementById('member-start-date');
            const endDateFilter = document.getElementById('member-end-date');
            const gymFilter = document.getElementById('gym-filter');
            const resetFilterBtn = document.getElementById('reset-member-filters');
            const membersTable = document.getElementById('allMembersTable');
            
            if (membersTable) {
                // Set default dates (last 12 months to today)
                const today = new Date();
                const twelveMonthsAgo = new Date();
                twelveMonthsAgo.setMonth(today.getMonth() - 12);
                
                // Format dates for input fields
                startDateFilter.value = formatDateForInput(twelveMonthsAgo);
                endDateFilter.value = formatDateForInput(today);
                
                // Apply initial filtering
                applyMemberFilters();
                
                // Add event listeners
                activeFilter.addEventListener('change', applyMemberFilters);
                inactiveFilter.addEventListener('change', applyMemberFilters);
                startDateFilter.addEventListener('change', applyMemberFilters);
                endDateFilter.addEventListener('change', applyMemberFilters);
                gymFilter.addEventListener('change', applyMemberFilters);
                resetFilterBtn.addEventListener('click', resetMemberFilters);
                
                // Include member section in main filters
                const includeMembers = document.getElementById('include-members');
                if (includeMembers) {
                    includeMembers.addEventListener('change', function() {
                        const memberSection = document.querySelector('.all-members-section');
                        if (memberSection) {
                            memberSection.setAttribute('data-hidden', !this.checked);
                        }
                    });
                }
                
                // Update PDF generation to include filtered members
                const downloadPdfBtn = document.getElementById('downloadPdfBtn');
                if (downloadPdfBtn) {
                    const originalOnclick = downloadPdfBtn.onclick;
                    downloadPdfBtn.onclick = function() {
                        // Update filtered members data for PDF
                        updateVisibleMembersForPDF();
                        
                        // Then call original function
                        if (typeof originalOnclick === 'function') {
                            originalOnclick.call(this);
                        } else if (typeof generatePDF === 'function') {
                            generatePDF();
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
            
            function resetMemberFilters() {
                activeFilter.checked = true;
                inactiveFilter.checked = true;
                gymFilter.value = 'all';
                
                const today = new Date();
                const twelveMonthsAgo = new Date();
                twelveMonthsAgo.setMonth(today.getMonth() - 12);
                
                startDateFilter.value = formatDateForInput(twelveMonthsAgo);
                endDateFilter.value = formatDateForInput(today);
                
                applyMemberFilters();
            }
            
            function applyMemberFilters() {
                const showActive = activeFilter.checked;
                const showInactive = inactiveFilter.checked;
                const gymId = gymFilter.value;
                const startDate = startDateFilter.value ? new Date(startDateFilter.value) : null;
                const endDate = endDateFilter.value ? new Date(endDateFilter.value) : null;
                
                const rows = membersTable.querySelectorAll('tbody tr.member-row');
                let visibleCount = 0;
                let activeCount = 0;
                let inactiveCount = 0;
                
                rows.forEach(row => {
                    const rowStatus = row.getAttribute('data-status');
                    const rowGymId = row.getAttribute('data-gym-id');
                    const rowStartDate = row.getAttribute('data-start-date') ? new Date(row.getAttribute('data-start-date')) : null;
                    
                    let visible = true;
                    
                    // Apply status filters
                    if ((rowStatus === 'active' && !showActive) || 
                        (rowStatus === 'inactive' && !showInactive)) {
                        visible = false;
                    }
                    
                    // Apply gym filter
                    if (visible && gymId !== 'all' && rowGymId !== gymId) {
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
                        if (rowStatus === 'active') {
                            activeCount++;
                        } else if (rowStatus === 'inactive') {
                            inactiveCount++;
                        }
                    }
                });
                
                // Update the visible count displays
                updateMemberCountDisplay(activeCount, inactiveCount, visibleCount);
                
                // Show "No data" message if no visible rows
                const noDataRow = membersTable.querySelector('.no-data-row');
                if (visibleCount === 0 && !noDataRow) {
                    const tbody = membersTable.querySelector('tbody');
                    const tr = document.createElement('tr');
                    tr.className = 'no-data-row';
                    const td = document.createElement('td');
                    td.className = 'no-data';
                    td.setAttribute('colspan', '7');
                    td.textContent = 'No members match the selected filters.';
                    tr.appendChild(td);
                    tbody.appendChild(tr);
                } else if (visibleCount > 0 && noDataRow) {
                    noDataRow.remove();
                }
            }
            
            function updateMemberCountDisplay(activeCount, inactiveCount, totalCount) {
                // Update the stats boxes with filtered counts
                const activeCountElem = document.querySelector('.stat-box.active .stat-number');
                const inactiveCountElem = document.querySelector('.stat-box.inactive .stat-number');
                const totalCountElem = document.querySelector('.stat-box.total .stat-number');
                
                if (activeCountElem) {
                    activeCountElem.textContent = activeCount.toLocaleString();
                }
                
                if (inactiveCountElem) {
                    inactiveCountElem.textContent = inactiveCount.toLocaleString();
                }
                
                if (totalCountElem) {
                    totalCountElem.textContent = totalCount.toLocaleString();
                }
            }
            
            function updateVisibleMembersForPDF() {
                // Mark visible members for PDF inclusion
                const rows = membersTable.querySelectorAll('tbody tr.member-row');
                
                // Store the filtered counts for PDF generation
                window.filteredMemberCounts = {
                    active: 0,
                    inactive: 0,
                    total: 0,
                    byGym: {}
                };
                
                rows.forEach(row => {
                    const isVisible = row.style.display !== 'none';
                    row.setAttribute('data-visible-for-pdf', isVisible ? 'true' : 'false');
                    
                    if (isVisible) {
                        const status = row.getAttribute('data-status');
                        const gymId = row.getAttribute('data-gym-id');
                        const gymName = row.cells[0].textContent;
                        
                        window.filteredMemberCounts.total++;
                        
                        if (status === 'active') {
                            window.filteredMemberCounts.active++;
                        } else if (status === 'inactive') {
                            window.filteredMemberCounts.inactive++;
                        }
                        
                        // Track by gym
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
        });
        </script>
    </body>
</html>