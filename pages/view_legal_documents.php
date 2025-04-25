<?php
session_start();
include '../config/database.php';

// Ensure user is superadmin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: login.php");
    exit();
}

// Get user ID and document type from URL
$user_id = $_GET['user_id'] ?? null;
$doc_type = $_GET['type'] ?? null;
$gym_id = $_GET['gym_id'] ?? null;

if (!$user_id || !$doc_type || !$gym_id) {
    header("Location: manage_gym_applications.php?error=invalid_params");
    exit();
}

// Get document path from database
$query = "SELECT legal_documents FROM gyms WHERE gym_id = ? AND owner_id = ?";
$stmt = $db_connection->prepare($query);
$stmt->bind_param("ii", $gym_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$gym = $result->fetch_assoc();

if (!$gym || empty($gym['legal_documents'])) {
    header("Location: manage_gym_applications.php?error=no_documents");
    exit();
}

$legal_docs = json_decode($gym['legal_documents'], true);

if (!isset($legal_docs[$doc_type])) {
    header("Location: manage_gym_applications.php?error=document_not_found");
    exit();
}

$doc_path = $legal_docs[$doc_type];

// Get file info
$file_info = pathinfo($doc_path);
$file_extension = strtolower($file_info['extension'] ?? '');

// Check if it's a viewable file type
$is_viewable = in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif', 'pdf']);

// Set appropriate headers based on file type
if ($is_viewable) {
    if ($file_extension === 'pdf') {
        header('Content-Type: application/pdf');
    } else {
        header('Content-Type: image/' . ($file_extension === 'jpg' ? 'jpeg' : $file_extension));
    }
    
    // For local file system
    $real_path = str_replace('../', '', $doc_path);
    if (file_exists('../' . $real_path)) {
        readfile('../' . $real_path);
        exit();
    } else {
        // If file doesn't exist, show a message
        echo "Error: Document file not found.";
    }
} else {
    // Not a viewable file
    header("Location: manage_gym_applications.php?error=unsupported_file_type");
    exit();
}
?>

<!-- Create a proper view_application.php page that shows all gym details and legal documents -->
<?php
// pages/view_application.php

session_start();
include '../config/database.php';

// Ensure user is superadmin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: login.php");
    exit();
}

// Get gym ID from URL
$gym_id = $_GET['gym_id'] ?? null;

if (!$gym_id) {
    header("Location: manage_gym_applications.php?error=invalid_gym");
    exit();
}

// Fetch application details
$query = "SELECT g.*, u.username, u.first_name, u.last_name, u.email, u.contact_number
          FROM gyms g 
          JOIN users u ON g.owner_id = u.id 
          WHERE g.gym_id = ? AND g.status = 'pending'";
$stmt = $db_connection->prepare($query);
$stmt->bind_param("i", $gym_id);
$stmt->execute();
$result = $stmt->get_result();
$application = $result->fetch_assoc();

if (!$application) {
    header("Location: manage_gym_applications.php?error=not_found");
    exit();
}

// Get legal documents
$legal_documents = [];
if (!empty($application['legal_documents'])) {
    $legal_documents = json_decode($application['legal_documents'], true);
}

// Get equipment images
$equipment_images = [];
if (!empty($application['equipment_images'])) {
    $equipment_images = json_decode($application['equipment_images'], true);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>View Gym Application - FitHub</title>
    <link rel="stylesheet" href="../assets/css/mains.css">
    <link rel="stylesheet" href="../assets/css/unified-theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .application-container {
            max-width: 900px;
            margin: 30px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .back-btn {
            display: inline-block;
            margin-bottom: 20px;
            color: #555;
            text-decoration: none;
        }
        
        .back-btn i {
            margin-right: 5px;
        }
        
        .section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .section:last-child {
            border-bottom: none;
        }
        
        .section h3 {
            margin-top: 0;
            color: #333;
            border-left: 4px solid #4CAF50;
            padding-left: 10px;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 10px;
        }
        
        .info-label {
            font-weight: bold;
            width: 150px;
            color: #555;
        }
        
        .info-value {
            flex: 1;
        }
        
        .thumbnail-preview {
            max-width: 300px;
            max-height: 200px;
            border-radius: 5px;
            margin-top: 10px;
            border: 1px solid #ddd;
        }
        
        .equipment-images {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
        }
        
        .equipment-image {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        .documents-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 15px;
        }
        
        .document-card {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            background-color: #f9f9f9;
        }
        
        .document-name {
            margin-top: 0;
            font-size: 16px;
            color: #333;
        }
        
        .document-preview {
            width: 100%;
            height: 180px;
            margin: 10px 0;
            object-fit: contain;
            border: 1px solid #ddd;
            background-color: #fff;
            border-radius: 3px;
        }
        
        .document-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        
        .view-btn, .download-btn {
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            color: white;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
        }
        
        .view-btn {
            background-color: #2196F3;
        }
        
        .download-btn {
            background-color: #4CAF50;
        }
        
        .view-btn i, .download-btn i {
            margin-right: 5px;
        }
        
        .approval-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .approve-btn, .reject-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            color: white;
            font-weight: bold;
            cursor: pointer;
            display: flex;
            align-items: center;
        }
        
        .approve-btn {
            background-color: #4CAF50;
        }
        
        .reject-btn {
            background-color: #f44336;
        }
        
        .approve-btn i, .reject-btn i {
            margin-right: 5px;
        }
        
        .gym-info {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .gym-details {
            flex: 1;
            min-width: 300px;
        }
        
        .owner-details {
            flex: 1;
            min-width: 300px;
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
        }
        
        /* Loading overlay */
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            color: white;
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
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Loading overlay -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="spinner"></div>
        <h3>Processing Request...</h3>
        <p>Please wait while we process your request.</p>
    </div>

    <div class="application-container">
        <a href="manage_gym_applications.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Applications
        </a>
        
        <h1>Gym Application: <?php echo htmlspecialchars($application['gym_name']); ?></h1>
        
        <div class="section">
            <div class="gym-info">
                <div class="gym-details">
                    <h3>Gym Information</h3>
                    
                    <div class="info-row">
                        <div class="info-label">Gym Name:</div>
                        <div class="info-value"><?php echo htmlspecialchars($application['gym_name']); ?></div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">Location:</div>
                        <div class="info-value"><?php echo htmlspecialchars($application['gym_location']); ?></div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">Phone Number:</div>
                        <div class="info-value"><?php echo htmlspecialchars($application['gym_phone_number']); ?></div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">Description:</div>
                        <div class="info-value"><?php echo nl2br(htmlspecialchars($application['gym_description'])); ?></div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">Amenities:</div>
                        <div class="info-value"><?php echo nl2br(htmlspecialchars($application['gym_amenities'])); ?></div>
                    </div>
                </div>
                
                <div class="owner-details">
                    <h3>Owner Information</h3>
                    
                    <div class="info-row">
                        <div class="info-label">Name:</div>
                        <div class="info-value"><?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?></div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">Username:</div>
                        <div class="info-value"><?php echo htmlspecialchars($application['username']); ?></div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">Email:</div>
                        <div class="info-value"><?php echo htmlspecialchars($application['email']); ?></div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">Contact Number:</div>
                        <div class="info-value"><?php echo htmlspecialchars($application['contact_number']); ?></div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">Submission Date:</div>
                        <div class="info-value"><?php echo date('F d, Y', strtotime($application['created_at'])); ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if (!empty($application['gym_thumbnail'])): ?>
        <div class="section">
            <h3>Gym Thumbnail</h3>
            <img src="<?php echo htmlspecialchars($application['gym_thumbnail']); ?>" 
                 alt="Gym Thumbnail" class="thumbnail-preview">
        </div>
        <?php endif; ?>
        
        <?php if (!empty($equipment_images)): ?>
        <div class="section">
            <h3>Equipment Images</h3>
            <div class="equipment-images">
                <?php foreach ($equipment_images as $image): ?>
                    <img src="<?php echo htmlspecialchars($image); ?>" 
                         alt="Equipment" class="equipment-image">
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="section">
            <h3>Legal Documents</h3>
            
            <?php if (empty($legal_documents)): ?>
                <p>No legal documents found for this application.</p>
            <?php else: ?>
                <div class="documents-grid">
                    <?php foreach ($legal_documents as $doc_type => $doc_path): 
                        $doc_name = ucwords(str_replace('_', ' ', $doc_type));
                        $file_ext = strtolower(pathinfo($doc_path, PATHINFO_EXTENSION));
                        $is_image = in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif']);
                        $is_pdf = $file_ext === 'pdf';
                    ?>
                        <div class="document-card">
                            <h4 class="document-name"><?php echo htmlspecialchars($doc_name); ?></h4>
                            
                            <?php if ($is_image): ?>
                                <img src="<?php echo htmlspecialchars($doc_path); ?>" 
                                     alt="<?php echo htmlspecialchars($doc_name); ?>" 
                                     class="document-preview">
                            <?php elseif ($is_pdf): ?>
                                <div class="document-preview" style="text-align: center; padding-top: 60px;">
                                    <i class="fas fa-file-pdf" style="font-size: 48px; color: #f44336;"></i>
                                    <p>PDF Document</p>
                                </div>
                            <?php else: ?>
                                <div class="document-preview" style="text-align: center; padding-top: 60px;">
                                    <i class="fas fa-file" style="font-size: 48px; color: #607d8b;"></i>
                                    <p><?php echo strtoupper($file_ext); ?> Document</p>
                                </div>
                            <?php endif; ?>
                            
                            <div class="document-actions">
                                <a href="view_legal_documents.php?gym_id=<?php echo $application['gym_id']; ?>&user_id=<?php echo $application['owner_id']; ?>&type=<?php echo $doc_type; ?>" 
                                   target="_blank" class="view-btn">
                                   <i class="fas fa-eye"></i> View
                                </a>
                                
                                <?php if (file_exists(str_replace('../', '', $doc_path))): ?>
                                <a href="<?php echo htmlspecialchars($doc_path); ?>" 
                                   download="<?php echo htmlspecialchars($doc_name . '.' . $file_ext); ?>"
                                   class="download-btn">
                                   <i class="fas fa-download"></i> Download
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="approval-actions">
            <form action="../actions/approve_gym.php" method="POST" onsubmit="showLoading()">
                <input type="hidden" name="gym_id" value="<?php echo $application['gym_id']; ?>">
                <button type="submit" class="approve-btn"
                        onclick="return confirm('Are you sure you want to approve this gym?')">
                    <i class="fas fa-check"></i> Approve Application
                </button>
            </form>
            
            <form action="../actions/reject_gym.php" method="POST" onsubmit="showLoading()">
                <input type="hidden" name="gym_id" value="<?php echo $application['gym_id']; ?>">
                <button type="submit" class="reject-btn"
                        onclick="return confirm('Are you sure you want to reject this application? This action cannot be undone.')">
                    <i class="fas fa-times"></i> Reject Application
                </button>
            </form>
        </div>
    </div>
    
    <script>
    function showLoading() {
        document.getElementById('loadingOverlay').style.display = 'flex';
        return true;
    }
    </script>
</body>
</html>