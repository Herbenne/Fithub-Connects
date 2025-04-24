<?php
session_start();
include '../config/database.php';
require_once '../includes/AWSFileManager.php';

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

// If we're using AWS, get URLs for legal documents
$documentUrls = [];
if (USE_AWS) {
    $awsManager = new AWSFileManager();
    $documentUrls = $awsManager->getPendingLegalDocumentUrls($application['owner_user_id']);
}

// Get document paths from gym record if not using AWS
if (!USE_AWS && !empty($application['legal_documents'])) {
    $legal_docs = json_decode($application['legal_documents'], true);
    foreach ($legal_docs as $type => $path) {
        $docType = ucwords(str_replace('_', ' ', $type));
        $documentUrls[$docType] = [
            'url' => '../' . $path,
            'filename' => basename($path),
            'path' => $path
        ];
    }
}

// Extract equipment images
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
    <link rel="stylesheet" href="../assets/css/view_application.css">
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
            
            <?php if (!empty($application['gym_thumbnail'])):
                $thumbnail_url = "view_file.php?path=" . urlencode($application['gym_thumbnail']) . "&direct=1";
            ?>
                <div class="section">
                    <h3>Gym Thumbnail</h3>
                    <img src="<?php echo htmlspecialchars($thumbnail_url); ?>" 
                        alt="Gym Thumbnail" 
                        class="thumbnail-preview"
                        onerror="this.src='../assets/images/image-error.png';">
                </div>
            <?php endif; ?>
            
            <?php if (!empty($equipment_images)): ?>
                <h3>Equipment Images</h3>
                <div class="equipment-images">
                    <?php foreach ($equipment_images as $image): 
                        $image_url = "view_file.php?path=" . urlencode($image) . "&direct=1";
                    ?>
                        <img src="<?php echo htmlspecialchars($image_url); ?>" 
                            alt="Equipment" 
                            class="equipment-image"
                            onerror="this.src='../assets/images/image-error.png';">
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
            
            <?php if (empty($documentUrls)): ?>
                <p>No legal documents found for this application.</p>
            <?php else: ?>
                <div class="documents-grid">
                    <?php foreach ($legal_documents as $doc_type => $doc_path): 
                        $doc_name = ucwords(str_replace('_', ' ', $doc_type));
                        $file_ext = strtolower(pathinfo($doc_path, PATHINFO_EXTENSION));
                        $is_image = in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif']);
                        $is_pdf = $file_ext === 'pdf';
                        
                        // Generate proper URLs for viewing
                        $view_url = "view_file.php?path=" . urlencode($doc_path) . "&type=" . urlencode($doc_type);
                        $direct_url = $view_url . "&direct=1";
                    ?>
                        <div class="document-card">
                            <h4 class="document-name"><?php echo htmlspecialchars($doc_name); ?></h4>
                            
                            <?php if ($is_image): ?>
                                <img src="fetch_document.php?gym_id=<?php echo $gym_id; ?>&doc_type=<?php echo $doc_type; ?>" 
                                alt="<?php echo htmlspecialchars($doc_name); ?>" 
                                class="document-preview"
                                onerror="this.src='../assets/images/image-error.png';">
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
                                <a href="<?php echo htmlspecialchars($direct_url); ?>" 
                                target="_blank" class="view-btn">
                                <i class="fas fa-eye"></i> View
                                </a>
                                
                                <a href="<?php echo htmlspecialchars($direct_url); ?>"
                                download="<?php echo htmlspecialchars($doc_name . '.' . $file_ext); ?>"
                                class="download-btn">
                                <i class="fas fa-download"></i> Download
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="approval-actions">
            <form action="../actions/approve_gym.php" method="POST" 
                  onsubmit="return confirm('Are you sure you want to approve this gym?');">
                <input type="hidden" name="gym_id" value="<?php echo $application['gym_id']; ?>">
                <button type="submit" class="approve-btn">
                    <i class="fas fa-check"></i> Approve Application
                </button>
            </form>
            
            <form action="../actions/reject_gym.php" method="POST"
                  onsubmit="return confirm('Are you sure you want to reject this gym application?');">
                <input type="hidden" name="gym_id" value="<?php echo $application['gym_id']; ?>">
                <button type="submit" class="reject-btn">
                    <i class="fas fa-times"></i> Reject Application
                </button>
            </form>
        </div>
    </div>
</body>
</html>