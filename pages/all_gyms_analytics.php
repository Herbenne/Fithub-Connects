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

// And add a comprehensive query to get all members across gyms:
$all_members_query = "SELECT 
    g.gym_id,
    g.gym_name,
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
    JOIN gyms g ON m.gym_id = g.gym_id
    WHERE g.status = 'approved'
    ORDER BY g.gym_name, status, m.start_date DESC";
$all_members_result = $db_connection->query($all_members_query);
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            <h2><i class="fas fa-users"></i> Platform Members</h2>
            
            <div class="members-stats-cards">
                <div class="member-stat-card active">
                    <div class="stat-icon"><i class="fas fa-user-check"></i></div>
                    <div class="stat-info">
                        <span class="stat-label">Active Members</span>
                        <span class="stat-value"><?php echo number_format($overall_stats['active_members']); ?></span>
                    </div>
                </div>
                
                <div class="member-stat-card inactive">
                    <div class="stat-icon"><i class="fas fa-user-clock"></i></div>
                    <div class="stat-info">
                        <span class="stat-label">Inactive Members</span>
                        <span class="stat-value"><?php echo number_format($overall_stats['inactive_members']); ?></span>
                    </div>
                </div>
                
                <div class="member-stat-card total">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-info">
                        <span class="stat-label">Total Members</span>
                        <span class="stat-value"><?php echo number_format($overall_stats['total_members']); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="filter-card">
                <div class="filter-header">
                    <h3>Filter Members</h3>
                </div>
                <div class="filter-body">
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
                            <label for="member-start-date">Start Date:</label>
                            <input type="date" id="member-start-date" class="date-input">
                        </div>
                        
                        <div class="filter-group">
                            <label for="member-end-date">End Date:</label>
                            <input type="date" id="member-end-date" class="date-input">
                        </div>
                        
                        <div class="filter-group">
                            <label for="gym-filter">Gym:</label>
                            <select id="gym-filter" class="status-filter">
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
                        
                        <button id="reset-member-filters" class="reset-filter-btn">
                            <i class="fas fa-sync-alt"></i> Reset Filters
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="table-container">
                <table id="allMembersTable" class="data-table">
                    <thead>
                        <tr>
                            <th>Gym</th>
                            <th>Full Name</th>
                            <th>Registration Date</th>
                            <th>Membership Plan</th>
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
                                    <td><?php echo date('M d, Y', strtotime($member['start_date'])); ?></td>
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
                                <td colspan="7" class="no-data">No member data available.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <style>
        /* Platform Members Section Styles */
        .platform-members-section {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-top: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 15px;
        }

        .section-header h2 {
            margin: 0;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-header h2 i {
            color: #ffb22c;
        }

        .toggle-btn {
            padding: 8px 16px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            transition: background 0.3s;
        }

        .toggle-btn:hover {
            background: #3d8b40;
        }

        .members-stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 25px;
            margin-bottom: 25px;
        }

        .member-stat-card {
            display: flex;
            align-items: center;
            padding: 20px;
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

        .filter-card {
            background: #f8f9fa;
            border-radius: 10px;
            margin-bottom: 25px;
            border: 1px solid #eee;
            overflow: hidden;
        }

        .filter-header {
            padding: 15px 20px;
            background-color: #f1f3f5;
            border-bottom: 1px solid #eee;
        }

        .filter-header h3 {
            margin: 0;
            font-size: 16px;
            color: #555;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-header h3 i {
            color: #4CAF50;
        }

        .filter-body {
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
            gap: 8px;
        }

        .filter-group label {
            font-size: 14px;
            color: #666;
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

        .date-input, .select-input {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            width: 100%;
        }

        .date-input:focus, .select-input:focus {
            outline: none;
            border-color: #4CAF50;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
        }

        .reset-filter-btn {
            padding: 10px 15px;
            background: #f1f1f1;
            border: 1px solid #ddd;
            border-radius: 5px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-weight: 500;
            transition: all 0.3s;
            width: 100%;
            margin-top: 10px;
        }

        .reset-filter-btn:hover {
            background: #e9e9e9;
        }

        .members-table-container {
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
            background-color: #f8f9fa;
            font-weight: 600;
            color: #555;
            text-align: left;
            padding: 15px;
            border-bottom: 2px solid #eee;
            position: sticky;
            top: 0;
        }

        .data-table td {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }

        .data-table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .data-table tr:last-child td {
            border-bottom: none;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
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
            color: #666;
            padding: 30px;
            font-style: italic;
        }

        /* Ensure this section is hidden when filters are applied */
        .platform-members-section[data-hidden="true"] {
            display: none;
        }

        @media (max-width: 992px) {
            .filter-row {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 768px) {
            .members-stats-cards {
                grid-template-columns: 1fr;
            }
            
            .filter-row {
                grid-template-columns: 1fr;
            }
        }
        </style>

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
            color: #ffb22c;
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
        // Data preparation - keep your existing PHP variables
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize filter functionality
            const includeGyms = document.getElementById('include-gyms');
            const includeMembers = document.getElementById('include-members');
            const includeRatings = document.getElementById('include-ratings');
            const includeRevenue = document.getElementById('include-revenue');
            
            // Initialize filters
            function initializeFilters() {
                [includeGyms, includeMembers, includeRatings, includeRevenue].forEach(checkbox => {
                    if (checkbox) checkbox.checked = true;
                });
                applyFilters();
            }
            
            // Apply filters to elements
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
                
                // Filter gym cards
                const gymCards = document.querySelectorAll('.gym-card');
                gymCards.forEach(card => {
                    card.setAttribute('data-hidden', !includeGyms.checked);
                });
                
                // Filter member section
                const memberSection = document.querySelector('.all-members-section');
                if (memberSection) {
                    memberSection.setAttribute('data-hidden', !includeMembers.checked);
                }
            }
            
            // Add filter change listeners
            [includeGyms, includeMembers, includeRatings, includeRevenue].forEach(checkbox => {
                if (checkbox) checkbox.addEventListener('change', applyFilters);
            });
            
            // Add PDF export functionality
            const downloadPdfBtn = document.getElementById('downloadPdfBtn');
            if (downloadPdfBtn) {
                downloadPdfBtn.addEventListener('click', generatePDF);
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
                        
                        // Set up PDF
                        doc.setFont('Helvetica-Bold');
                        doc.setFontSize(22);
                        doc.text('FitHub Platform Analytics Report', 105, 20, { align: 'center' });
                        
                        doc.setFont('Helvetica');
                        doc.setFontSize(12);
                        doc.text(`Generated on: ${new Date().toLocaleDateString()}`, 105, 30, { align: 'center' });
                        
                        // Add filter information
                        doc.setFontSize(10);
                        doc.text('Filters applied:', 20, 40);
                        const filterText = [];
                        if (includeGyms && includeGyms.checked) filterText.push('Gyms');
                        if (includeMembers && includeMembers.checked) filterText.push('Members');
                        if (includeRatings && includeRatings.checked) filterText.push('Ratings');
                        if (includeRevenue && includeRevenue.checked) filterText.push('Revenue');
                        doc.text(`Included: ${filterText.join(', ')}`, 20, 45);
                        
                        // Add platform overview
                        let currentY = 55;
                        if (filterText.length > 0) {
                            doc.setFont('Helvetica-Bold');
                            doc.setFontSize(16);
                            doc.text('Platform Overview', 20, currentY);
                            currentY += 10;
                            
                            doc.setFont('Helvetica');
                            doc.setFontSize(12);
                            
                            const statCards = document.querySelectorAll('.stats-overview .stat-card[data-hidden="false"]');
                            statCards.forEach((card) => {
                                const label = card.querySelector('h3').innerText;
                                let value = card.querySelector('.stat-number').innerText;
                                
                                // Clean up values for PDF
                                if (label.includes('Rating')) {
                                    value = value.replace(/[⭐+P±]/g, '').trim() + " rating";
                                } else if (label.includes('Revenue')) {
                                    value = "PHP " + value.replace(/[₱]/g, '').trim();
                                }
                                
                                doc.text(`${label}: ${value}`, 20, currentY);
                                currentY += 10;
                            });
                            
                            currentY += 10; // Add more space after overview
                        }
                        
                        // Add gym listings (if included)
                        if (includeGyms && includeGyms.checked) {
                            doc.setFont('Helvetica-Bold');
                            doc.setFontSize(16);
                            doc.text('Registered Gyms', 20, currentY);
                            currentY += 10;
                            
                            doc.setFont('Helvetica');
                            doc.setFontSize(12);
                            
                            const gyms = document.querySelectorAll('.gym-card[data-hidden="false"]');
                            gyms.forEach((gym, index) => {
                                // Check if we need a new page
                                if (currentY > 260) {
                                    doc.addPage();
                                    currentY = 20;
                                }
                                
                                // Get gym data
                                const gymName = gym.querySelector('h3').innerText;
                                const location = gym.querySelector('.location').innerText;
                                
                                // Add gym name
                                doc.setFont('Helvetica-Bold');
                                doc.setFontSize(14);
                                doc.text(`${index + 1}. ${gymName}`, 20, currentY);
                                currentY += 7;
                                
                                // Add location
                                doc.setFont('Helvetica');
                                doc.setFontSize(10);
                                doc.text(`Location: ${location}`, 25, currentY);
                                currentY += 7;
                                
                                // Add stats
                                const stats = [];
                                if (includeMembers && includeMembers.checked) {
                                    const memberStat = gym.querySelector('.stat:nth-child(1)');
                                    if (memberStat) {
                                        stats.push({
                                            label: memberStat.querySelector('label').innerText,
                                            value: memberStat.querySelector('span').innerText
                                        });
                                    }
                                }
                                if (includeRatings && includeRatings.checked) {
                                    const ratingStat = gym.querySelector('.stat:nth-child(2)');
                                    if (ratingStat) {
                                        stats.push({
                                            label: ratingStat.querySelector('label').innerText,
                                            value: ratingStat.querySelector('span').innerText
                                        });
                                    }
                                }
                                if (includeRevenue && includeRevenue.checked) {
                                    const revenueStat = gym.querySelector('.stat:nth-child(3)');
                                    if (revenueStat) {
                                        stats.push({
                                            label: revenueStat.querySelector('label').innerText,
                                            value: revenueStat.querySelector('span').innerText
                                        });
                                    }
                                }
                                
                                if (stats.length > 0) {
                                    doc.text('Statistics:', 25, currentY);
                                    currentY += 5;
                                    
                                    stats.forEach((stat) => {
                                        let value = stat.value;
                                        
                                        // Fix currency and rating symbols
                                        if (stat.label.includes('Rating')) {
                                            value = value.replace(/[⭐+P±]/g, '').trim() + " rating";
                                        } else if (stat.label.includes('Revenue')) {
                                            value = "PHP " + value.replace(/[₱]/g, '').trim();
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
                        
                        // Add members section if included
                        if (includeMembers && includeMembers.checked) {
                            // Start a new page for members
                            doc.addPage();
                            
                            doc.setFont('Helvetica-Bold');
                            doc.setFontSize(16);
                            doc.text('Platform Members', 105, 20, { align: 'center' });
                            
                            // Get member counts
                            const memberCounts = {
                                active: document.querySelector('.member-stat-card.active .stat-value')?.textContent || 
                                        document.querySelector('.stat-box.active .stat-number')?.textContent || '0',
                                inactive: document.querySelector('.member-stat-card.inactive .stat-value')?.textContent || 
                                        document.querySelector('.stat-box.inactive .stat-number')?.textContent || '0',
                                total: document.querySelector('.member-stat-card.total .stat-value')?.textContent || 
                                    document.querySelector('.stat-box.total .stat-number')?.textContent || '0'
                            };
                            
                            // Add member stats
                            let memberY = 40;
                            doc.setFont('Helvetica');
                            doc.setFontSize(12);
                            
                            doc.text(`Active Members: ${memberCounts.active}`, 20, memberY);
                            memberY += 10;
                            doc.text(`Inactive Members: ${memberCounts.inactive}`, 20, memberY);
                            memberY += 10;
                            doc.text(`Total Members: ${memberCounts.total}`, 20, memberY);
                            memberY += 20;
                            
                            // Add member table if it exists
                            const membersTable = document.getElementById('allMembersTable');
                            if (membersTable) {
                                // Add table header
                                doc.setFont('Helvetica-Bold');
                                doc.text('Member List', 105, memberY, { align: 'center' });
                                memberY += 15;
                                
                                // Get visible rows
                                const visibleRows = Array.from(membersTable.querySelectorAll('tbody tr'))
                                    .filter(row => row.style.display !== 'none' && !row.classList.contains('no-data-row') && row.classList.contains('member-row'));
                                
                                if (visibleRows.length > 0) {
                                    // Get column headers
                                    const headers = Array.from(membersTable.querySelectorAll('thead th')).map(th => th.textContent);
                                    
                                    // Draw table header
                                    const startY = memberY;
                                    const rowHeight = 10;
                                    let colWidths = [];
                                    
                                    // Determine column widths based on headers
                                    if (headers.length === 7) { // Full table with Gym column
                                        colWidths = [30, 35, 25, 30, 25, 25, 20]; // Gym, Name, Reg Date, Plan, Start, End, Status
                                    } else {
                                        colWidths = [40, 30, 35, 30, 30, 25]; // Name, Reg Date, Plan, Start, End, Status
                                    }
                                    
                                    // Calculate total width
                                    const tableWidth = colWidths.reduce((sum, width) => sum + width, 0);
                                    const leftMargin = (210 - tableWidth) / 2;
                                    
                                    // Draw header background
                                    doc.setFillColor(240, 240, 240);
                                    doc.rect(leftMargin, startY - 5, tableWidth, rowHeight, 'F');
                                    
                                    // Draw header text
                                    let currentX = leftMargin;
                                    headers.forEach((header, index) => {
                                        if (index < colWidths.length) {
                                            doc.text(header, currentX + 2, startY);
                                            currentX += colWidths[index];
                                        }
                                    });
                                    
                                    // Draw rows (max 20)
                                    let currentY = startY + rowHeight;
                                    const maxRows = Math.min(visibleRows.length, 20);
                                    
                                    doc.setFont('Helvetica');
                                    for (let i = 0; i < maxRows; i++) {
                                        const row = visibleRows[i];
                                        
                                        // Add alternating row background
                                        if (i % 2 === 1) {
                                            doc.setFillColor(248, 248, 248);
                                            doc.rect(leftMargin, currentY - 5, tableWidth, rowHeight, 'F');
                                        }
                                        
                                        // Draw cell data
                                        currentX = leftMargin;
                                        for (let j = 0; j < Math.min(row.cells.length, colWidths.length); j++) {
                                            let text = row.cells[j].textContent.trim();
                                            
                                            // Truncate text if too long
                                            const maxLength = Math.floor(colWidths[j] / 2);
                                            if (text.length > maxLength) {
                                                text = text.substring(0, maxLength - 3) + '...';
                                            }
                                            
                                            // Set color for status column (usually the last column)
                                            if (j === row.cells.length - 1 && text.toLowerCase().includes('active')) {
                                                doc.setTextColor(76, 175, 80); // Green
                                            } else if (j === row.cells.length - 1 && text.toLowerCase().includes('inactive')) {
                                                doc.setTextColor(244, 67, 54); // Red
                                            }
                                            
                                            doc.text(text, currentX + 2, currentY);
                                            doc.setTextColor(0); // Reset text color
                                            
                                            currentX += colWidths[j];
                                        }
                                        
                                        currentY += rowHeight;
                                    }
                                    
                                    // Add note if more members than shown
                                    if (visibleRows.length > maxRows) {
                                        currentY += 5;
                                        doc.text(`Note: Showing ${maxRows} of ${visibleRows.length} members.`, leftMargin, currentY);
                                    }
                                } else {
                                    doc.text('No members match the current filter criteria.', 105, memberY, { align: 'center' });
                                }
                            }
                        }
                        
                        // Add footer with page numbers
                        const pageCount = doc.getNumberOfPages();
                        for (let i = 1; i <= pageCount; i++) {
                            doc.setPage(i);
                            doc.setFontSize(10);
                            doc.setTextColor(150);
                            doc.text(`FitHub Analytics Report - Page ${i} of ${pageCount}`, 105, 285, { align: 'center' });
                        }
                        
                        // Remove loading overlay and save the PDF
                        document.body.removeChild(loadingOverlay);
                        
                        // Generate filename with date and filters
                        const filterSuffix = filterText.join('_');
                        const filename = `FitHub_Analytics_${filterSuffix}_${new Date().toLocaleDateString().replace(/\//g, '-')}.pdf`;
                        doc.save(filename);
                    } catch (error) {
                        console.error('Error generating PDF:', error);
                        document.body.removeChild(loadingOverlay);
                        alert('Error generating PDF. Please try again.');
                    }
                }, 500);
            }
            
            // Member filtering functionality
            initializeMemberFiltering();
            
            // Call initialize on page load
            initializeFilters();
        });

        function initializeMemberFiltering() {
            // For all_gyms_analytics.php
            const activeFilter = document.getElementById('filter-active-members');
            const inactiveFilter = document.getElementById('filter-inactive-members');
            const startDateFilter = document.getElementById('member-start-date');
            const endDateFilter = document.getElementById('member-end-date');
            const gymFilter = document.getElementById('gym-filter');
            const resetFilterBtn = document.getElementById('reset-member-filters');
            const membersTable = document.getElementById('allMembersTable');
            
            if (!membersTable) return;
            
            // Set default dates (last 12 months to today)
            const today = new Date();
            const twelveMonthsAgo = new Date();
            twelveMonthsAgo.setMonth(today.getMonth() - 12);
            
            // Format dates for input fields
            if (startDateFilter) startDateFilter.value = formatDateForInput(twelveMonthsAgo);
            if (endDateFilter) endDateFilter.value = formatDateForInput(today);
            
            // Apply initial filtering
            applyMemberFilters();
            
            // Add event listeners
            if (activeFilter) activeFilter.addEventListener('change', applyMemberFilters);
            if (inactiveFilter) inactiveFilter.addEventListener('change', applyMemberFilters);
            if (startDateFilter) startDateFilter.addEventListener('change', applyMemberFilters);
            if (endDateFilter) endDateFilter.addEventListener('change', applyMemberFilters);
            if (gymFilter) gymFilter.addEventListener('change', applyMemberFilters);
            if (resetFilterBtn) resetFilterBtn.addEventListener('click', resetMemberFilters);
            
            function formatDateForInput(date) {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            }
            
            function resetMemberFilters() {
                if (activeFilter) activeFilter.checked = true;
                if (inactiveFilter) inactiveFilter.checked = true;
                if (gymFilter) gymFilter.value = 'all';
                
                const today = new Date();
                const twelveMonthsAgo = new Date();
                twelveMonthsAgo.setMonth(today.getMonth() - 12);
                
                if (startDateFilter) startDateFilter.value = formatDateForInput(twelveMonthsAgo);
                if (endDateFilter) endDateFilter.value = formatDateForInput(today);
                
                applyMemberFilters();
            }
            
            function applyMemberFilters() {
                const showActive = activeFilter ? activeFilter.checked : true;
                const showInactive = inactiveFilter ? inactiveFilter.checked : true;
                const gymId = gymFilter ? gymFilter.value : 'all';
                const startDate = startDateFilter && startDateFilter.value ? new Date(startDateFilter.value) : null;
                const endDate = endDateFilter && endDateFilter.value ? new Date(endDateFilter.value) : null;
                
                const rows = membersTable.querySelectorAll('tbody tr.member-row');
                let activeCount = 0;
                let inactiveCount = 0;
                let totalCount = 0;
                
                rows.forEach(row => {
                    const rowStatus = row.getAttribute('data-status');
                    const rowGymId = row.getAttribute('data-gym-id');
                    const startDateValue = row.getAttribute('data-start-date') ? new Date(row.getAttribute('data-start-date')) : null;
                    
                    let visible = true;
                    
                    // Apply status filters
                    if ((rowStatus === 'active' && !showActive) || (rowStatus === 'inactive' && !showInactive)) {
                        visible = false;
                    }
                    
                    // Apply gym filter
                    if (visible && gymId !== 'all' && rowGymId !== gymId) {
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
                updateCount('.member-stat-card.active .stat-value, #active-members-count', activeCount);
                updateCount('.member-stat-card.inactive .stat-value, #inactive-members-count', inactiveCount);
                updateCount('.member-stat-card.total .stat-value, #total-members-count', totalCount);
                
                // Show "No data" message if no visible rows
                const noDataRow = membersTable.querySelector('.no-data-row');
                if (totalCount === 0 && !noDataRow) {
                    const tbody = membersTable.querySelector('tbody');
                    const tr = document.createElement('tr');
                    tr.className = 'no-data-row';
                    const td = document.createElement('td');
                    td.className = 'no-data';
                    td.setAttribute('colspan', '7');
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