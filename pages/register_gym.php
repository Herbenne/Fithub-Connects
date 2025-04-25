<?php
session_start();
include '../config/database.php';
include '../includes/auth.php';
require_once '../includes/AWSFileManager.php';

// Add role check
if (!isset($_SESSION['role']) || $_SESSION['role'] === 'member') {
    die("Error: You don't have permission to register a gym.");
}

// Check if user has a pending application
$user_id = $_SESSION['user_id'];
$check_query = "SELECT * FROM gyms WHERE owner_id = ? AND status = 'pending'";
$stmt = $db_connection->prepare($check_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$pending_application = $stmt->get_result()->fetch_assoc();

// Check if user already owns an approved gym
$approved_query = "SELECT * FROM gyms WHERE owner_id = ? AND status = 'approved'";
$stmt = $db_connection->prepare($approved_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$approved_gym = $stmt->get_result()->fetch_assoc();

// If user has an approved gym, redirect to dashboard
if ($approved_gym) {
    header("Location: dashboard.php?error=already_owner");
    exit();
}

// Initialize AWS file manager
$awsManager = new AWSFileManager();

// Process gym registration
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_SESSION['user_id'])) {
        die("Error: You must be logged in to apply for a gym.");
    }

    $gym_name = trim($_POST['gym_name']);
    $gym_location = trim($_POST['gym_location']);
    $gym_phone_number = trim($_POST['gym_phone_number']);
    $gym_description = trim($_POST['gym_description']);
    $gym_amenities = trim($_POST['gym_amenities']);
    $user_id = $_SESSION['user_id'];

    if (empty($gym_name) || empty($gym_location) || empty($gym_phone_number) || 
        empty($gym_description) || empty($gym_amenities)) {
        $error = "All fields are required.";
    } else {
        // Start transaction
        $db_connection->begin_transaction();

        try {
            // Create placeholder gym record to get gym_id
            $query = "INSERT INTO gyms (owner_id, gym_name, gym_location, gym_phone_number, 
                      gym_description, gym_amenities, status) 
                      VALUES (?, ?, ?, ?, ?, ?, 'pending')";
            $stmt = $db_connection->prepare($query);

            if ($stmt === false) {
                throw new Exception("Error preparing statement: " . $db_connection->error);
            }

            $stmt->bind_param("isssss", $user_id, $gym_name, $gym_location, $gym_phone_number, 
                              $gym_description, $gym_amenities);

            if (!$stmt->execute()) {
                throw new Exception("Error creating gym record: " . $stmt->error);
            }

            $gym_id = $db_connection->insert_id;

            // Handle file uploads
            $legal_documents = [];
            $required_docs = [
                'business_permit' => 'Business Permit',
                'valid_id' => 'Valid ID',
                'barangay_clearance' => 'Barangay Clearance'
            ];
            
            // Optional document
            $optional_docs = [
                'sanitary_clearance' => 'Sanitary Clearance'
            ];

            // Process required documents
            foreach ($required_docs as $doc_field => $doc_name) {
                if (!isset($_FILES[$doc_field]) || $_FILES[$doc_field]['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception("$doc_name is required");
                }
                
                $tmp_path = $_FILES[$doc_field]['tmp_name'];
                $filename = $_FILES[$doc_field]['name'];
                
                // These are encrypted and private
                $result = $awsManager->uploadLegalDocument($tmp_path, $gym_id, $filename, $doc_field);
                
                if (!$result) {
                    throw new Exception("Failed to upload $doc_name");
                }
                
                $legal_documents[$doc_field] = $result;
            }

            // Process optional document
            foreach ($optional_docs as $doc_field => $doc_name) {
                if (isset($_FILES[$doc_field]) && $_FILES[$doc_field]['error'] === UPLOAD_ERR_OK) {
                    $tmp_path = $_FILES[$doc_field]['tmp_name'];
                    $filename = $_FILES[$doc_field]['name'];
                    
                    $result = $awsManager->uploadLegalDocument($tmp_path, $gym_id, $filename, $doc_field);
                    
                    if ($result) {
                        $legal_documents[$doc_field] = $result;
                    }
                }
            }

            // Handle gym thumbnail upload
            $gym_thumbnail = null;
            if (isset($_FILES['gym_thumbnail']) && $_FILES['gym_thumbnail']['error'] === UPLOAD_ERR_OK) {
                $tmp_path = $_FILES['gym_thumbnail']['tmp_name'];
                $filename = $_FILES['gym_thumbnail']['name'];
                
                // Create gym folder structure if not already created
                $awsManager->createGymFolder($gym_id, $gym_name);
                
                // Upload thumbnail
                $gym_thumbnail = $awsManager->uploadGymThumbnail($tmp_path, $gym_id, $filename);
                
                if (!$gym_thumbnail) {
                    throw new Exception("Failed to upload gym thumbnail");
                }
                
                // Update the record with the thumbnail path
                $update_query = "UPDATE gyms SET gym_thumbnail = ? WHERE gym_id = ?";
                $stmt = $db_connection->prepare($update_query);
                $stmt->bind_param("si", $gym_thumbnail, $gym_id);
                
                if (!$stmt->execute()) {
                    throw new Exception("Failed to update gym with thumbnail");
                }
            }

            // Handle equipment images
            $equipment_images = [];
            if (isset($_FILES['equipment_images'])) {
                foreach ($_FILES['equipment_images']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['equipment_images']['error'][$key] === UPLOAD_ERR_OK) {
                        $filename = $_FILES['equipment_images']['name'][$key];
                        $result = $awsManager->uploadEquipmentImages($tmp_name, $gym_id, $filename);
                        
                        if ($result) {
                            $equipment_images[] = $result;
                        }
                    }
                }
            }

            // Update gym with equipment images
            if (!empty($equipment_images)) {
                $equipment_json = json_encode($equipment_images);
                $update_query = "UPDATE gyms SET equipment_images = ? WHERE gym_id = ?";
                $stmt = $db_connection->prepare($update_query);
                $stmt->bind_param("si", $equipment_json, $gym_id);
                
                if (!$stmt->execute()) {
                    throw new Exception("Failed to update gym with equipment images");
                }
            }

            // Store legal documents information
            if (!empty($legal_documents)) {
                $legal_json = json_encode($legal_documents);
                $legal_query = "UPDATE gyms SET legal_documents = ? WHERE gym_id = ?";
                $stmt = $db_connection->prepare($legal_query);
                $stmt->bind_param("si", $legal_json, $gym_id);
                
                if (!$stmt->execute()) {
                    throw new Exception("Failed to store legal documents information");
                }
            }

            $db_connection->commit();
            $success = "Gym registration request sent! Wait for approval.";
            
            // Redirect to dashboard on success
            header("Location: dashboard.php?success=application_submitted");
            exit();
        } catch (Exception $e) {
            $db_connection->rollback();
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register Gym - FitHub</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/mains.css">
    <link rel="stylesheet" href="../assets/css/dashboards.css">
    <link rel="stylesheet" href="../assets/css/unified-theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .application-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background: #f7f7f7;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .form-section {
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }
        
        .form-section:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }
        
        .form-section h3 {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #333;
            margin-bottom: 1rem;
        }
        
        .step-indicator {
            display: flex;
            margin-bottom: 2rem;
        }
        
        .step {
            flex: 1;
            text-align: center;
            padding: 1rem;
            background: #f0f0f0;
            border-right: 1px solid #ddd;
            position: relative;
        }
        
        .step:last-child {
            border-right: none;
        }
        
        .step.active {
            background: #ffb22c;
            color: #000000;
            font-weight: bold;
        }
        
        .step-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            background: #333;
            color: white;
            border-radius: 50%;
            margin-right: 0.5rem;
        }
        
        .step.active .step-number {
            background: #000000;
        }
        
        .file-input-container {
            margin-bottom: 1.5rem;
        }
        
        .file-input-container label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .file-drop-area {
            border: 2px dashed #ddd;
            border-radius: 6px;
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
        }
        
        .file-drop-area:hover {
            border-color: #ffb22c;
            background: rgba(255, 178, 44, 0.05);
        }
        
        .file-drop-area.highlight {
            border-color: #ffb22c;
            background: rgba(255, 178, 44, 0.1);
        }
        
        .file-input {
            display: none;
        }
        
        .file-message {
            margin-top: 1rem;
            color: #666;
        }
        
        .file-preview {
            margin-top: 1rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .file-preview-item {
            position: relative;
            width: 100px;
            height: 100px;
            background: #f0f0f0;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .file-preview-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .remove-file {
            position: absolute;
            top: 5px;
            right: 5px;
            width: 20px;
            height: 20px;
            background: rgba(0, 0, 0, 0.5);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 10px;
        }
        
        .file-type-icon {
            font-size: 36px;
            color: #666;
        }
        
        .file-name {
            font-size: 10px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            width: 90px;
            text-align: center;
            margin-top: 5px;
        }
        
        .required-label::after {
            content: "*";
            color: #f44336;
            margin-left: 4px;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
        }
        
        .alert-info {
            background: rgba(255, 178, 44, 0.1);
            border-left: 4px solid #ffb22c;
            color: #000000;
        }
        
        .next-btn, .prev-btn, .submit-btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .next-btn, .submit-btn {
            background: #ffb22c;
            color: #000000;
        }
        
        .prev-btn {
            background: #f0f0f0;
            color: #333;
        }
        
        .navigation-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 2rem;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-brand">FitHub Connect</div>
        <div class="nav-links">
            <a href="dashboard.php">Dashboard</a>
            <a href="profile.php">My Profile</a>
            <a href="../actions/logout.php">Logout</a>
        </div>
    </nav>

    <div class="application-container">
        <h2>Gym Registration Application</h2>
        
        <?php if ($pending_application): ?>
            <div class="alert alert-info">
                <p><i class="fas fa-info-circle"></i> You already have a pending gym application for 
                   <strong><?php echo htmlspecialchars($pending_application['gym_name']); ?></strong>. 
                   Please wait for approval before submitting another application.</p>
                <a href="dashboard.php" class="back-btn" style="margin-top: 1rem;">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        <?php else: ?>
            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    <p><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></p>
                </div>
            <?php endif; ?>

            <?php if (isset($success)): ?>
                <div class="alert alert-success">
                    <p><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></p>
                </div>
            <?php endif; ?>

            <div class="step-indicator">
                <div class="step active" data-step="basic">
                    <span class="step-number">1</span>
                    Basic Information
                </div>
                <div class="step" data-step="media">
                    <span class="step-number">2</span>
                    Media & Gallery
                </div>
                <div class="step" data-step="legal">
                    <span class="step-number">3</span>
                    Legal Documents
                </div>
                <div class="step" data-step="review">
                    <span class="step-number">4</span>
                    Review & Submit
                </div>
            </div>
            
            <form id="gymApplicationForm" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" enctype="multipart/form-data">
                <div class="form-section" id="step-basic">
                    <h3><i class="fas fa-info-circle"></i> Basic Information</h3>
                    
                    <div class="form-group">
                        <label for="gym_name" class="required-label">Gym Name</label>
                        <input type="text" id="gym_name" name="gym_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="gym_location" class="required-label">Location</label>
                        <input type="text" id="gym_location" name="gym_location" required 
                               placeholder="Full address including city, state/province, and postal code">
                    </div>
                    
                    <div class="form-group">
                        <label for="gym_phone_number" class="required-label">Phone Number</label>
                        <input type="tel" id="gym_phone_number" name="gym_phone_number" required 
                               placeholder="e.g., +123456789">
                    </div>
                    
                    <div class="form-group">
                        <label for="gym_description" class="required-label">Gym Description</label>
                        <textarea id="gym_description" name="gym_description" rows="4" required 
                                 placeholder="Describe your gym, its focus, and what makes it unique..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="gym_amenities" class="required-label">Amenities</label>
                        <textarea id="gym_amenities" name="gym_amenities" rows="4" required 
                                 placeholder="List your gym's amenities, equipment, classes, trainers, etc..."></textarea>
                    </div>
                    
                    <div class="navigation-buttons">
                        <div></div> <!-- Empty div to maintain space-between alignment -->
                        <button type="button" class="next-btn" data-next="media">
                            Next: Media & Gallery <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-section" id="step-media" style="display: none;">
                    <h3><i class="fas fa-camera"></i> Media & Gallery</h3>
                    
                    <div class="file-input-container">
                        <label for="gym_thumbnail" class="required-label">Gym Thumbnail (Main Image)</label>
                        <div class="file-drop-area" id="thumbnailDropArea">
                            <input type="file" id="gym_thumbnail" name="gym_thumbnail" class="file-input" 
                                  accept="image/*" required>
                            <div class="file-message">
                                <i class="fas fa-cloud-upload-alt fa-2x"></i>
                                <p>Drag & drop or click to browse</p>
                                <p class="file-note">Recommended size: 1200×800 pixels, Max: 5MB</p>
                            </div>
                            <div class="file-preview" id="thumbnailPreview"></div>
                        </div>
                    </div>
                    
                    <div class="file-input-container">
                        <label for="equipment_images">Equipment Images (Optional)</label>
                        <div class="file-drop-area" id="equipmentDropArea">
                            <input type="file" id="equipment_images" name="equipment_images[]" class="file-input" 
                                  accept="image/*" multiple>
                            <div class="file-message">
                                <i class="fas fa-cloud-upload-alt fa-2x"></i>
                                <p>Drag & drop or click to browse (multiple files allowed)</p>
                                <p class="file-note">Max: 5 images, 5MB each</p>
                            </div>
                            <div class="file-preview" id="equipmentPreview"></div>
                        </div>
                    </div>
                    
                    <div class="navigation-buttons">
                        <button type="button" class="prev-btn" data-prev="basic">
                            <i class="fas fa-arrow-left"></i> Previous
                        </button>
                        <button type="button" class="next-btn" data-next="legal">
                            Next: Legal Documents <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-section" id="step-legal" style="display: none;">
                    <h3><i class="fas fa-file-contract"></i> Legal Documents</h3>
                    
                    <div class="alert alert-info">
                        <p><i class="fas fa-info-circle"></i> The following documents are required for verification purposes. 
                        All documents are encrypted and securely stored. Only authorized personnel can view them during the approval process.</p>
                    </div>
                    
                    <div class="file-input-container">
                        <label for="business_permit" class="required-label">Business Permit</label>
                        <div class="file-drop-area" id="permitDropArea">
                            <input type="file" id="business_permit" name="business_permit" class="file-input" 
                                  accept=".pdf,.jpg,.jpeg,.png" required>
                            <div class="file-message">
                                <i class="fas fa-file-alt fa-2x"></i>
                                <p>Upload your business permit (PDF, JPG, PNG)</p>
                                <p class="file-note">Max size: 10MB</p>
                            </div>
                            <div class="file-preview" id="permitPreview"></div>
                        </div>
                    </div>
                    
                    <div class="file-input-container">
                        <label for="valid_id" class="required-label">Owner's Valid ID</label>
                        <div class="file-drop-area" id="idDropArea">
                            <input type="file" id="valid_id" name="valid_id" class="file-input" 
                                  accept=".pdf,.jpg,.jpeg,.png" required>
                            <div class="file-message">
                                <i class="fas fa-id-card fa-2x"></i>
                                <p>Upload a valid government-issued ID (PDF, JPG, PNG)</p>
                                <p class="file-note">Max size: 5MB</p>
                            </div>
                            <div class="file-preview" id="idPreview"></div>
                        </div>
                    </div>
                    
                    <div class="file-input-container">
                        <label for="barangay_clearance" class="required-label">Barangay Clearance</label>
                        <div class="file-drop-area" id="barangayDropArea">
                            <input type="file" id="barangay_clearance" name="barangay_clearance" class="file-input" 
                                  accept=".pdf,.jpg,.jpeg,.png" required>
                            <div class="file-message">
                                <i class="fas fa-file-invoice fa-2x"></i>
                                <p>Upload your barangay clearance certificate (PDF, JPG, PNG)</p>
                                <p class="file-note">Max size: 5MB</p>
                            </div>
                            <div class="file-preview" id="barangayPreview"></div>
                        </div>
                    </div>
                    
                    <div class="file-input-container">
                        <label for="sanitary_clearance">Sanitary Clearance (Optional)</label>
                        <div class="file-drop-area" id="sanitaryDropArea">
                            <input type="file" id="sanitary_clearance" name="sanitary_clearance" class="file-input" 
                                  accept=".pdf,.jpg,.jpeg,.png">
                            <div class="file-message">
                                <i class="fas fa-file-medical-alt fa-2x"></i>
                                <p>Upload your sanitary clearance if available (PDF, JPG, PNG)</p>
                                <p class="file-note">Max size: 5MB</p>
                            </div>
                            <div class="file-preview" id="sanitaryPreview"></div>
                        </div>
                    </div>
                    
                    <div class="navigation-buttons">
                        <button type="button" class="prev-btn" data-prev="media">
                            <i class="fas fa-arrow-left"></i> Previous
                        </button>
                        <button type="button" class="next-btn" data-next="review">
                            Next: Review & Submit <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-section" id="step-review" style="display: none;">
                    <h3><i class="fas fa-check-circle"></i> Review & Submit</h3>
                    
                    <p>Please review your information before submitting. Once submitted, your application will be reviewed by our team.</p>
                    
                    <div id="summary-basic" class="summary-section">
                        <h4>Basic Information</h4>
                        <div class="summary-content"></div>
                    </div>
                    
                    <div id="summary-media" class="summary-section">
                        <h4>Media & Gallery</h4>
                        <div class="summary-content"></div>
                    </div>
                    
                    <div id="summary-legal" class="summary-section">
                        <h4>Legal Documents</h4>
                        <div class="summary-content"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="terms_agreement" class="required-label">Terms and Conditions</label>
                        <div class="checkbox-group">
                            <input type="checkbox" id="terms_agreement" name="terms_agreement" required>
                            <label for="terms_agreement">
                                I hereby declare that all the information provided is true and correct. I understand that 
                                any false information may result in the rejection of my application or termination of my gym listing.
                            </label>
                        </div>
                    </div>
                    
                    <div class="navigation-buttons">
                        <button type="button" class="prev-btn" data-prev="legal">
                            <i class="fas fa-arrow-left"></i> Previous
                        </button>
                        <button type="submit" class="submit-btn">
                            <i class="fas fa-paper-plane"></i> Submit Application
                        </button>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>
    <script src="../assets/js/register_gym.js"></script>
</body>
</html>