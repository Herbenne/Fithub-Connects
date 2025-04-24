<?php
// pages/view_file.php - A dedicated script to reliably serve files from any storage location
session_start();
include '../config/database.php';

// Basic security check - must be logged in
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.0 403 Forbidden');
    echo "Access denied";
    exit();
}

// Get parameters
$file_path = isset($_GET['path']) ? $_GET['path'] : null;
$file_type = isset($_GET['type']) ? $_GET['type'] : null;
$direct = isset($_GET['direct']) ? true : false;

// Validate file path
if (empty($file_path)) {
    header('HTTP/1.0 400 Bad Request');
    echo "Missing file path";
    exit();
}

// Convert URL-encoded path
$file_path = urldecode($file_path);

// Security check - prevent directory traversal
$file_path = str_replace('../', '', $file_path);
$file_path = str_replace('..\\', '', $file_path);

// Add error logging to help debug
error_log("Attempting to serve file: " . $file_path);

// Check if it's a local path already starting with assets or uploads
$local_path_prefixes = ['assets/', 'uploads/'];
$is_local_path = false;

foreach ($local_path_prefixes as $prefix) {
    if (strpos($file_path, $prefix) === 0) {
        $is_local_path = true;
        break;
    }
}

// If it's a relative path without prefix, handle differently
if (!$is_local_path && strpos($file_path, 'http') !== 0) {
    error_log("Path does not have standard prefix: " . $file_path);
    
    // Try to infer correct path based on context
    if (strpos($file_path, 'profile_') !== false) {
        // Likely a profile picture
        $file_path = 'assets/images/profile_pictures/' . basename($file_path);
        error_log("Inferred path as profile picture: " . $file_path);
    } else if (strpos($file_path, 'gym_') !== false) {
        // Likely a gym thumbnail
        $file_path = 'uploads/gym_thumbnails/' . basename($file_path);
        error_log("Inferred path as gym thumbnail: " . $file_path);
    } else if (strpos($file_path, 'equipment_') !== false) {
        // Likely an equipment image
        $file_path = 'uploads/equipment_images/' . basename($file_path);
        error_log("Inferred path as equipment image: " . $file_path);
    } else if (strpos($file_path, 'permit') !== false || 
              strpos($file_path, 'id') !== false || 
              strpos($file_path, 'certificate') !== false) {
        // Likely a legal document
        if (preg_match('/user_(\d+)/', $file_path, $matches)) {
            $user_id = $matches[1];
            $file_path = 'uploads/legal_documents/pending/user_' . $user_id . '/' . basename($file_path);
            error_log("Inferred path as legal document: " . $file_path);
        }
    }
}

// Add special handling for profile pictures
if (strpos($file_path, 'profile_') !== false) {
    // Check if it's a user-specific path
    if (strpos($file_path, 'user_') === false && isset($_SESSION['user_id'])) {
        // Try to construct the correct user-specific path
        $user_id = $_SESSION['user_id'];
        $filename = basename($file_path);
        $file_path = "uploads/profile_pictures/user_{$user_id}/" . $filename;
        error_log("Reconstructed profile path: " . $file_path);
    }
}

// Handle AWS vs local storage
if (USE_AWS) {
    try {
        require_once '../includes/AWSFileManager.php';
        $awsManager = new AWSFileManager();
        
        // If it's already a full URL, just redirect to it
        if (strpos($file_path, 'http') === 0) {
            error_log("Redirecting to existing URL: " . $file_path);
            header("Location: $file_path");
            exit();
        }
        
        // Generate a presigned URL with a short expiry
        $url = $awsManager->getPresignedUrl($file_path, '+15 minutes');
        
        if ($url) {
            error_log("Generated presigned URL: " . $url);
            
            // If direct viewing is requested, redirect to the URL
            if ($direct) {
                header("Location: $url");
                exit();
            }
            
            // Otherwise, return the URL as JSON
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'url' => $url]);
            exit();
        } else {
            error_log("Failed to generate URL for: " . $file_path);
            header('HTTP/1.0 404 Not Found');
            echo "File not found";
            exit();
        }
    } catch (Exception $e) {
        error_log("AWS Error: " . $e->getMessage());
        header('HTTP/1.0 500 Internal Server Error');
        echo "Error accessing file: " . $e->getMessage();
        exit();
    }
} else {
    // For local storage, we'll directly serve the file
    
    // Build the full path
    $full_path = '../' . $file_path;
    error_log("Attempting to serve local file: " . $full_path);
    
    // Check if file exists
    if (!file_exists($full_path)) {
        error_log("Local file not found: " . $full_path);
        
        // Try alternative path formats
        $alt_paths = [
            '../' . ltrim($file_path, '/'),  // Remove leading slash
            $file_path,                      // As is
            str_replace('../', '', $file_path) // Remove any existing ../ prefix
        ];
        
        $found = false;
        foreach ($alt_paths as $path) {
            error_log("Trying alternative path: " . $path);
            if (file_exists($path)) {
                $full_path = $path;
                $found = true;
                error_log("Found file at: " . $full_path);
                break;
            }
        }
        
        if (!$found) {
            header('HTTP/1.0 404 Not Found');
            echo "File not found. Checked paths:<br>";
            echo "- Original: " . $full_path . "<br>";
            foreach ($alt_paths as $path) {
                echo "- Alternative: " . $path . "<br>";
            }
            exit();
        }
    }
    
    // Get file info
    $file_info = pathinfo($full_path);
    $file_extension = strtolower($file_info['extension'] ?? '');
    
    // Set appropriate content type based on extension
    $content_type = 'application/octet-stream'; // Default
    
    switch ($file_extension) {
        case 'jpg':
        case 'jpeg':
            $content_type = 'image/jpeg';
            break;
        case 'png':
            $content_type = 'image/png';
            break;
        case 'gif':
            $content_type = 'image/gif';
            break;
        case 'pdf':
            $content_type = 'application/pdf';
            break;
        case 'txt':
            $content_type = 'text/plain';
            break;
    }
    
    // Serve the file
    header('Content-Type: ' . $content_type);
    header('Content-Disposition: inline; filename="' . basename($full_path) . '"');
    
    // Make sure the file exists again (just to be safe)
    if (file_exists($full_path) && is_readable($full_path)) {
        header('Content-Length: ' . filesize($full_path));
        readfile($full_path);
    } else {
        echo "Error: File exists but is not readable.";
        error_log("File exists but is not readable: " . $full_path);
    }
    exit();
}