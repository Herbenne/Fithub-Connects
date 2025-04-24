<?php
session_start();
include '../config/database.php';

// Add debugging to help troubleshoot
ini_set('display_errors', 1);
error_reporting(E_ALL);
error_log("Starting view_application.php execution");

// Ensure user is superadmin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: login.php");
    exit();
}

// Get gym ID from URL
$gym_id = $_GET['gym_id'] ?? null;

if (!$gym_id) {
    header("Location: manage_gym_applications.php?error=invalid_request");
    exit();
}

// Fetch application details
$query = "SELECT g.*, 
          u.id as owner_user_id, 
          u.username, 
          u.first_name, 
          u.last_name, 
          u.email,
          u.contact_number
          FROM gyms g 
          JOIN users u ON g.owner_id = u.id 
          WHERE g.gym_id = ? AND g.status = 'pending'";
$stmt = $db_connection->prepare($query);
$stmt->bind_param("i", $gym_id);
$stmt->execute();
$application = $stmt->get_result()->fetch_assoc();

if (!$application) {
    header("Location: manage_gym_applications.php?error=application_not_found");
    exit();
}

// Extract equipment images
$equipment_images = [];
if (!empty($application['equipment_images'])) {
    $equipment_images = json_decode($application['equipment_images'], true);
}

// Extract legal documents
$legal_documents = [];
if (!empty($application['legal_documents'])) {
    $legal_documents = json_decode($application['legal_documents'], true);
    
    // Log the documents for debugging
    error_log("Legal documents: " . print_r($legal_documents, true));
}

// Bucket base URL for direct access (since your bucket is public)
$s3_base_url = "https://fithubconnect-bucket.s3.ap-southeast-1.amazonaws.com/";
?>

<!DOCTYPE html>
<html>
<head>
    <title>View Gym Application - FitHub</title>
    <link rel="stylesheet" href="../assets/css/mains.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
    .application-container {
        max-width: 1000px;
        margin: 30px auto;
        padding: 20px;
        background-color: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .back-btn {
        margin-bottom: 20px;
        display: inline-block;
        padding: 8px 15px;
        background-color: #f5f5f5;
        color: #333;
        border-radius: 4px;
        text-decoration: none;
    }
    
    .back-btn:hover {
        background-color: #e0e0e0;
    }
    
    .section {
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 1px solid #eee;
    }
    
    .documents-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
        margin-top: 15px;
    }
    
    .document-card {
        border: 1px solid #ddd;
        border-radius: 5px;
        padding: 15px;
        background-color: #f9f9f9;
    }
    
    .document-card h4 {
        margin-top: 0;
        color: #444;
    }
    
    .document-actions {
        margin-top: 15px;
    }
    
    .document-actions a {
        display: inline-block;
        padding: 6px 12px;
        background-color: #4CAF50;
        color: white;
        text-decoration: none;
        border-radius: 4px;
        margin-right: 5px;
    }
    
    .document-actions a:hover {
        background-color: #45a049;
    }
    
    .document-preview {
        width: 100%;
        height: 200px;
        object-fit: contain;
        margin-top: 10px;
        border: 1px solid #ddd;
        background-color: #fff;
    }
    
    .approval-actions {
        display: flex;
        gap: 10px;
        margin-top: 30px;
    }
    
    .approval-actions form {
        display: inline;
    }
    
    .approval-actions button {
        padding: 10px 20px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-weight: bold;
    }
    
    .approve-btn {
        background-color: #4CAF50;
        color: white;
    }
    
    .reject-btn {
        background-color: #f44336;
        color: white;
    }
    
    .approval-actions button:hover {
        opacity: 0.9;
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
        border-radius: 4px;
    }
    </style>
</head>
<body>
    <div class="application-container">
        <a href="manage_gym_applications.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Applications
        </a>
        
        <h1>Gym Application Review</h1>
        
        <div class="section">
            <h2>Gym Details</h2>
            <p><strong>Gym Name:</strong> <?php echo htmlspecialchars($application['gym_name']); ?></p>
            <p><strong>Location:</strong> <?php echo htmlspecialchars($application['gym_location']); ?></p>
            <p><strong>Phone Number:</strong> <?php echo htmlspecialchars($application['gym_phone_number']); ?></p>
            <p><strong>Description:</strong> <?php echo nl2br(htmlspecialchars($application['gym_description'])); ?></p>
            <p><strong>Amenities:</strong> <?php echo nl2br(htmlspecialchars($application['gym_amenities'])); ?></p>
            
            <?php if (!empty($application['gym_thumbnail'])): ?>
                <h3>Gym Thumbnail</h3>
                <img src="<?php echo htmlspecialchars($application['gym_thumbnail']); ?>" 
                     alt="Gym Thumbnail" 
                     style="max-width: 300px; border-radius: 5px;"
                     onerror="this.onerror=null; this.src='../assets/images/placeholder.png';">
            <?php endif; ?>
            
            <?php if (!empty($equipment_images)): ?>
                <h3>Equipment Images</h3>
                <div class="equipment-images">
                    <?php foreach ($equipment_images as $image): ?>
                        <img src="<?php echo htmlspecialchars($image); ?>" 
                             alt="Equipment" class="equipment-image"
                             onerror="this.onerror=null; this.src='../assets/images/placeholder.png';">
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="section">
            <h2>Owner Information</h2>
            <p><strong>Name:</strong> <?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?></p>
            <p><strong>Username:</strong> <?php echo htmlspecialchars($application['username']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($application['email']); ?></p>
            <p><strong>Contact Number:</strong> <?php echo htmlspecialchars($application['contact_number']); ?></p>
        </div>
        
        <div class="section">
            <h2>Legal Documents</h2>
            
            <?php if (empty($legal_documents)): ?>
                <p>No legal documents found for this application.</p>
            <?php else: ?>
                <div class="documents-grid">
                    <?php foreach ($legal_documents as $doc_type => $doc_path): 
                        $doc_name = ucwords(str_replace('_', ' ', $doc_type));
                        
                        // Get direct S3 URL for the document
                        $doc_url = $doc_path;
                        
                        // If it's not a full URL already, make it one
                        if (strpos($doc_path, 'http') !== 0) {
                            // Remove any leading slashes
                            $doc_path = ltrim($doc_path, '/');
                            $doc_url = $s3_base_url . $doc_path;
                        }
                    ?>
                        <div class="document-card">
                            <h4><?php echo htmlspecialchars($doc_name); ?></h4>
                            <p><?php echo htmlspecialchars(basename($doc_path)); ?></p>
                            
                            <?php 
                            // Get the file extension
                            $file_extension = strtolower(pathinfo($doc_path, PATHINFO_EXTENSION));
                            $is_image = in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif']);
                            
                            if ($is_image): 
                            ?>
                                <img src="<?php echo htmlspecialchars($doc_url); ?>" 
                                     alt="<?php echo htmlspecialchars($doc_name); ?>"
                                     class="document-preview"
                                     onerror="this.onerror=null; this.src='../assets/images/image-error.png'">
                            <?php else: ?>
                                <div class="document-preview" style="text-align: center; padding-top: 60px;">
                                    <i class="fas fa-file-<?php echo $file_extension === 'pdf' ? 'pdf' : 'alt'; ?>" 
                                       style="font-size: 48px; color: <?php echo $file_extension === 'pdf' ? '#f44336' : '#607d8b'; ?>;"></i>
                                    <p><?php echo strtoupper($file_extension); ?> Document</p>
                                </div>
                            <?php endif; ?>
                            
                            <div class="document-actions">
                                <a href="<?php echo htmlspecialchars($doc_url); ?>" 
                                   target="_blank">View Document</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="approval-actions">
            <form action="../actions/approve_gym.php" method="POST">
                <input type="hidden" name="gym_id" value="<?php echo $application['gym_id']; ?>">
                <button type="submit" class="approve-btn" onclick="return confirm('Are you sure you want to approve this gym?');">
                    <i class="fas fa-check"></i> Approve Application
                </button>
            </form>
            
            <form action="../actions/reject_gym.php" method="POST">
                <input type="hidden" name="gym_id" value="<?php echo $application['gym_id']; ?>">
                <button type="submit" class="reject-btn" onclick="return confirm('Are you sure you want to reject this gym?');">
                    <i class="fas fa-times"></i> Reject Application
                </button>
            </form>
        </div>
    </div>
</body>
</html>