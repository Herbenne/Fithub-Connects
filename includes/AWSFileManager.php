<?php

require_once __DIR__ . '/../vendor/autoload.php'; // Requires AWS SDK for PHP

use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use Aws\S3\Exception\S3Exception;

class AWSFileManager {
    private $s3Client;
    private $bucketName;
    private $region;
    private $baseUrl;
    private $currentFolders = [];
    
    /**
     * Initialize AWS S3 client for Lightsail
     */
    public function __construct() {
        // Load AWS configuration
        $aws_config_path = __DIR__ . '/../private/config/aws-config.php';
        $aws_config = file_exists($aws_config_path) ? require($aws_config_path) : [
            'credentials' => [
                'key' => getenv('AWS_ACCESS_KEY_ID') ?: '',
                'secret' => getenv('AWS_SECRET_ACCESS_KEY') ?: ''
            ],
            'region' => getenv('AWS_REGION') ?: 'ap-southeast-1',
            'bucket' => getenv('AWS_BUCKET_NAME') ?: 'fithubconnect-bucket'
        ];
        
        $this->bucketName = $aws_config['bucket'];
        $this->region = $aws_config['region'];
        $this->baseUrl = "https://{$this->bucketName}.s3.{$this->region}.amazonaws.com/";
        
        try {
            $this->s3Client = new S3Client([
                'version' => 'latest',
                'region'  => $this->region,
                'credentials' => [
                    'key'    => $aws_config['credentials']['key'],
                    'secret' => $aws_config['credentials']['secret'],
                ]
            ]);
            
            // Check connection by listing bucket contents
            $this->s3Client->listObjectsV2([
                'Bucket' => $this->bucketName,
                'MaxKeys' => 1
            ]);
            
            // Initialize base folder structure
            $this->initializeBaseStructure();
            
        } catch (Exception $e) {
            error_log("AWS Connection Error: " . $e->getMessage());
            throw new Exception("Failed to connect to AWS storage: " . $e->getMessage());
        }
    }
    
    /**
     * Creates the base folder structure if it doesn't exist
     */
    private function initializeBaseStructure() {
        $baseFolders = [
            'uploads/',
            'uploads/profile_pictures/',
            'uploads/legal_documents/',
            'uploads/gyms/',
            'uploads/legal_documents/pending/' // Added separate folder for pending documents
        ];
        
        foreach ($baseFolders as $folder) {
            $this->createFolderIfNotExists($folder);
        }
    }
    
    /**
     * Check if folder exists, create if it doesn't
     */
    public function createFolderIfNotExists($folderPath) {
        try {
            // Ensure the path ends with a slash
            if (substr($folderPath, -1) !== '/') {
                $folderPath .= '/';
            }
            
            // First check if we've already verified this folder in this session
            if (in_array($folderPath, $this->currentFolders)) {
                return true;
            }
            
            // Check if folder exists
            $result = $this->s3Client->listObjectsV2([
                'Bucket' => $this->bucketName,
                'Prefix' => $folderPath,
                'MaxKeys' => 1
            ]);
            
            // If the folder doesn't exist or is empty, create it with an empty object
            if (!isset($result['Contents']) || count($result['Contents']) === 0) {
                $this->s3Client->putObject([
                    'Bucket' => $this->bucketName,
                    'Key'    => $folderPath,
                    'Body'   => '',
                    'ACL'    => 'private'
                ]);
                
                error_log("Created folder: {$folderPath}");
                $this->currentFolders[] = $folderPath; // Add to tracked folders
                return true;
            }
            
            // Folder exists
            $this->currentFolders[] = $folderPath; // Add to tracked folders
            return false;
        } catch (S3Exception $e) {
            error_log("Error creating folder {$folderPath}: " . $e->getMessage());
            return false;
        }
    }
    
    public function uploadTestThumbnail($tempFilePath, $filename) {
        $targetPath = "uploads/test/thumbnail_" . time() . ".txt";
        return $this->uploadFile($tempFilePath, $targetPath, false);
    }

    /**
     * Checks if a gym already has a folder structure in AWS
     */
    public function ensureGymFolderExists($gymId, $gymName) {
        $sanitizedName = $this->sanitizeFileName($gymName);
        $folderPath = "uploads/gyms/gym{$gymId}_{$sanitizedName}/";
        
        // Check if the folder exists
        try {
            $result = $this->s3Client->listObjectsV2([
                'Bucket' => $this->bucketName,
                'Prefix' => $folderPath,
                'MaxKeys' => 1
            ]);
            
            if (!isset($result['Contents']) || count($result['Contents']) === 0) {
                // Folder doesn't exist, create it
                return $this->createGymFolder($gymId, $gymName);
            } else {
                // Folder exists
                return $folderPath;
            }
        } catch (Exception $e) {
            error_log("Error checking gym folder: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create a new gym folder when a gym is approved
     */
    public function createGymFolder($gymId, $gymName) {
        $sanitizedName = $this->sanitizeFileName($gymName);
        $folderPath = "uploads/gyms/gym{$gymId}_{$sanitizedName}/";
        
        if ($this->createFolderIfNotExists($folderPath)) {
            // Create subfolders for gym
            $this->createFolderIfNotExists($folderPath . 'amenities/');
            $this->createFolderIfNotExists($folderPath . 'equipment/');
            $this->createFolderIfNotExists($folderPath . 'thumbnail/');
            $this->createFolderIfNotExists($folderPath . 'legal_documents/'); // Add legal_documents subfolder
            
            return $folderPath;
        }
        
        return false;
    }
    
    /**
     * Uploads a file to the specified path with proper permissions
     * 
     * @param string $tempFilePath Path to the temporary file
     * @param string $targetPath AWS path where file should be stored
     * @param boolean $isPrivate Whether the file should be private (default) or public
     * @param boolean $encrypt Whether to encrypt the file (for legal documents)
     * @return string|false The URL or path of the uploaded file or false on failure
     */
    public function uploadFile($tempFilePath, $targetPath, $isPrivate = true, $encrypt = false) {
        if (!file_exists($tempFilePath)) {
            error_log("Upload error: Temporary file not found at {$tempFilePath}");
            return false;
        }
        
        try {
            // Create the folder path if it doesn't exist
            $folderPath = dirname($targetPath) . '/';
            if (substr($folderPath, -1) !== '/') {
                $folderPath .= '/';
            }
            
            // Ensure folder exists
            $this->createFolderIfNotExists($folderPath);
            
            // Set up upload options
            $options = [
                'Bucket' => $this->bucketName,
                'Key'    => $targetPath,
                'Body'   => fopen($tempFilePath, 'rb'),
                'ACL'    => $isPrivate ? 'private' : 'public-read',
            ];
            
            // Add encryption for legal documents
            if ($encrypt) {
                $options['ServerSideEncryption'] = 'AES256';
            }
            
            // Upload file
            $result = $this->s3Client->putObject($options);
            error_log("Successfully uploaded file to S3: {$targetPath}");
            
            // Return the appropriate path or URL
            if ($isPrivate) {
                return $targetPath; // Return path for private files
            } else {
                return $result['ObjectURL']; // Return URL for public files
            }
            
        } catch (S3Exception $e) {
            error_log("AWS Upload Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Uploads a profile picture and organizes by user ID
     */
    public function uploadProfilePicture($tempFilePath, $userId, $fileName) {
        $ext = pathinfo($fileName, PATHINFO_EXTENSION);
        
        // Create user-specific folder structure
        $folderPath = "uploads/profile_pictures/user_{$userId}/";
        
        // Ensure the folder exists
        $this->createFolderIfNotExists($folderPath);
        
        $newFileName = "profile_" . time() . "." . $ext;
        $targetPath = $folderPath . $newFileName;
        
        // Upload as public file so it can be viewed directly
        $result = $this->uploadFile($tempFilePath, $targetPath, false); // not private, not encrypted
        
        return $result;
    }
    
    /**
     * Get public URL for a profile picture
     * 
     * @param string $path The stored path of the image
     * @return string The full URL to the image
     */
    public function getProfilePictureUrl($path) {
        // If it's already a full URL (starts with http), return it as is
        if (strpos($path, 'http') === 0) {
            return $path;
        }
        
        // If it's a relative path to an S3 object, create a presigned URL
        if (USE_AWS) {
            try {
                // For public files, we can construct the direct URL
                if (strpos($path, 'uploads/profile_pictures/') === 0) {
                    // Public objects can be accessed directly
                    return "https://{$this->bucketName}.s3.{$this->region}.amazonaws.com/{$path}";
                } else {
                    // For private objects, generate a presigned URL
                    return $this->getPresignedUrl($path, '+1 day');
                }
            } catch (Exception $e) {
                error_log("Error generating profile picture URL: " . $e->getMessage());
                return '../assets/images/default-profile.png'; // Fallback to default
            }
        }
        
        // For local storage, make sure path starts correctly
        if (strpos($path, '../') !== 0 && strpos($path, '/') !== 0) {
            return '../' . $path;
        }
        
        return $path;
    }


    /**
     * Uploads a gym thumbnail
     */
    public function uploadGymThumbnail($tempFilePath, $gymId, $fileName) {
        // Get gym data to find gym folder
        global $db_connection;
        $stmt = $db_connection->prepare("SELECT gym_name FROM gyms WHERE gym_id = ?");
        $stmt->bind_param("i", $gymId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $sanitizedName = $this->sanitizeFileName($row['gym_name']);
            $folderPath = "uploads/gyms/gym{$gymId}_{$sanitizedName}/thumbnail/";
            
            // Create folder if doesn't exist
            $this->createFolderIfNotExists($folderPath);
            
            $ext = pathinfo($fileName, PATHINFO_EXTENSION);
            $newFileName = "thumbnail_" . time() . "." . $ext;
            $targetPath = $folderPath . $newFileName;
            
            $result = $this->uploadFile($tempFilePath, $targetPath, false); // Public, not encrypted
            error_log("Gym thumbnail uploaded to: {$targetPath}, result: " . ($result ? $result : 'false'));
            return $result;
        }
        
        error_log("Failed to find gym data for uploading thumbnail. Gym ID: {$gymId}");
        return false;
    }
    
    /**
     * Uploads gym equipment images
     */
    public function uploadEquipmentImages($tempFilePath, $gymId, $fileName) {
        // Get gym data to find gym folder
        global $db_connection;
        $stmt = $db_connection->prepare("SELECT gym_name FROM gyms WHERE gym_id = ?");
        $stmt->bind_param("i", $gymId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $sanitizedName = $this->sanitizeFileName($row['gym_name']);
            $folderPath = "uploads/gyms/gym{$gymId}_{$sanitizedName}/equipment/";
            
            // Create folder if doesn't exist
            $this->createFolderIfNotExists($folderPath);
            
            $ext = pathinfo($fileName, PATHINFO_EXTENSION);
            $newFileName = "equipment_" . time() . "_" . uniqid() . "." . $ext;
            $targetPath = $folderPath . $newFileName;
            
            $result = $this->uploadFile($tempFilePath, $targetPath, false); // Public, not encrypted
            error_log("Equipment image uploaded to: {$targetPath}, result: " . ($result ? $result : 'false'));
            return $result;
        }
        
        error_log("Failed to find gym data for uploading equipment. Gym ID: {$gymId}");
        return false;
    }
    
    /**
     * Uploads amenities images
     */
    public function uploadAmenityImages($tempFilePath, $gymId, $fileName) {
        // Get gym data to find gym folder
        global $db_connection;
        $stmt = $db_connection->prepare("SELECT gym_name FROM gyms WHERE gym_id = ?");
        $stmt->bind_param("i", $gymId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $sanitizedName = $this->sanitizeFileName($row['gym_name']);
            $folderPath = "uploads/gyms/gym{$gymId}_{$sanitizedName}/amenities/";
            
            // Create folder if doesn't exist
            $this->createFolderIfNotExists($folderPath);
            
            $ext = pathinfo($fileName, PATHINFO_EXTENSION);
            $newFileName = "amenity_" . time() . "_" . uniqid() . "." . $ext;
            $targetPath = $folderPath . $newFileName;
            
            $result = $this->uploadFile($tempFilePath, $targetPath, false); // Public, not encrypted
            error_log("Amenity image uploaded to: {$targetPath}, result: " . ($result ? $result : 'false'));
            return $result;
        }
        
        error_log("Failed to find gym data for uploading amenity. Gym ID: {$gymId}");
        return false;
    }
    
    /**
     * Uploads pending legal documents - these will be encrypted and private
     */
    public function uploadPendingLegalDocument($tempFilePath, $userId, $fileName, $documentType) {
        $ext = pathinfo($fileName, PATHINFO_EXTENSION);
        $folderPath = "uploads/legal_documents/pending/user_{$userId}/";
        
        // Create pending documents folder for user if doesn't exist
        $this->createFolderIfNotExists($folderPath);
        
        $newFileName = "{$documentType}_" . time() . "." . $ext;
        $targetPath = $folderPath . $newFileName;
        
        $result = $this->uploadFile($tempFilePath, $targetPath, true, true); // Private AND encrypted
        error_log("Pending legal document uploaded to: {$targetPath}, result: " . ($result ? $result : 'false'));
        return $result;
    }
    
    /**
     * Uploads approved legal documents - these will be encrypted and private
     */
    public function uploadLegalDocument($tempFilePath, $gymId, $fileName, $documentType) {
        $ext = pathinfo($fileName, PATHINFO_EXTENSION);
        
        // Get gym folder path
        global $db_connection;
        $stmt = $db_connection->prepare("SELECT gym_name FROM gyms WHERE gym_id = ?");
        $stmt->bind_param("i", $gymId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $sanitizedName = $this->sanitizeFileName($row['gym_name']);
            $folderPath = "uploads/gyms/gym{$gymId}_{$sanitizedName}/legal_documents/";
            
            // Create legal documents folder if doesn't exist
            $this->createFolderIfNotExists($folderPath);
            
            $newFileName = "{$documentType}_" . time() . "." . $ext;
            $targetPath = $folderPath . $newFileName;
            
            $result = $this->uploadFile($tempFilePath, $targetPath, true, true); // Private AND encrypted
            error_log("Approved legal document uploaded to: {$targetPath}, result: " . ($result ? $result : 'false'));
            return $result;
        }
        
        error_log("Failed to find gym data for uploading legal document. Gym ID: {$gymId}");
        return false;
    }
    
    /**
     * Moves documents from pending to approved status
     * 
     * @param int $userId User ID of the gym applicant
     * @param int $gymId Gym ID that was approved
     * @return bool Success or failure
     */
    public function moveDocumentsToApprovedGym($userId, $gymId) {
        try {
            // Get gym data
            global $db_connection;
            $stmt = $db_connection->prepare("SELECT gym_name FROM gyms WHERE gym_id = ?");
            $stmt->bind_param("i", $gymId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if (!$result->num_rows) {
                error_log("Failed to find gym data for moving documents. Gym ID: {$gymId}");
                return false;
            }
            
            $gym = $result->fetch_assoc();
            $sanitizedName = $this->sanitizeFileName($gym['gym_name']);
            
            // Source and destination paths
            $sourcePath = "uploads/legal_documents/pending/user_{$userId}/";
            $destPath = "uploads/gyms/gym{$gymId}_{$sanitizedName}/legal_documents/";
            
            // Ensure destination folder exists
            $this->createFolderIfNotExists($destPath);
            
            // List all files in source directory
            $objects = $this->s3Client->listObjectsV2([
                'Bucket' => $this->bucketName,
                'Prefix' => $sourcePath
            ]);
            
            if (!isset($objects['Contents'])) {
                error_log("No files found in pending documents folder: {$sourcePath}");
                return false;
            }
            
            // Copy each file to the new location
            foreach ($objects['Contents'] as $object) {
                // Skip the directory object itself
                if (substr($object['Key'], -1) === '/') {
                    continue;
                }
                
                $fileName = basename($object['Key']);
                $destinationKey = $destPath . $fileName;
                
                // Copy object
                $this->s3Client->copyObject([
                    'Bucket' => $this->bucketName,
                    'CopySource' => $this->bucketName . '/' . $object['Key'],
                    'Key' => $destinationKey,
                    'ACL' => 'private',
                    'ServerSideEncryption' => 'AES256'
                ]);
                
                error_log("Moved document from {$object['Key']} to {$destinationKey}");
                
                // Delete original
                $this->s3Client->deleteObject([
                    'Bucket' => $this->bucketName,
                    'Key' => $object['Key']
                ]);
            }
            
            // Finally, delete the empty source directory
            $this->s3Client->deleteObject([
                'Bucket' => $this->bucketName,
                'Key' => $sourcePath
            ]);
            
            return true;
        } catch (Exception $e) {
            error_log("Error moving documents: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Gets a presigned URL that allows temporary access to private files
     * Used for superadmin to view legal documents
     */
    public function getPresignedUrl($filePath, $expiryTime = '+30 minutes') {
        try {
            $cmd = $this->s3Client->getCommand('GetObject', [
                'Bucket' => $this->bucketName,
                'Key'    => $filePath
            ]);
            
            $request = $this->s3Client->createPresignedRequest($cmd, $expiryTime);
            return (string)$request->getUri();
            
        } catch (S3Exception $e) {
            error_log("Error generating presigned URL: " . $e->getMessage());
            return false;
        }
    }
    
    /**
 * Gets a presigned URL for viewing legal documents with a shorter expiry
 *
 * @param string $filePath The path to the file in S3
 * @param string $expiryTime Time string for URL expiration (default: 15 minutes)
 * @return string|false The presigned URL or false on failure
 */
public function getLegalDocumentUrl($filePath, $expiryTime = '+15 minutes') {
    return $this->getPresignedUrl($filePath, $expiryTime);
}

/**
 * Retrieves URLs for all legal documents associated with a user's gym application
 *
 * @param int $userId The user ID of the applicant
 * @return array An array of document types and their URLs
 */
public function getPendingLegalDocumentUrls($userId) {
    $basePath = "uploads/legal_documents/pending/user_{$userId}/";
    $documentUrls = [];
    
    try {
        // List all objects in the user's pending folder
        $result = $this->s3Client->listObjectsV2([
            'Bucket' => $this->bucketName,
            'Prefix' => $basePath
        ]);
        
        if (isset($result['Contents'])) {
            foreach ($result['Contents'] as $object) {
                // Skip the directory marker
                if (substr($object['Key'], -1) === '/') {
                    continue;
                }
                
                // Extract document type from filename
                $filename = basename($object['Key']);
                $docType = 'unknown';
                
                // Try to identify document type
                if (strpos($filename, 'business_permit') === 0) {
                    $docType = 'Business Permit';
                } elseif (strpos($filename, 'valid_id') === 0) {
                    $docType = 'Valid ID';
                } elseif (strpos($filename, 'tax_certificate') === 0) {
                    $docType = 'Tax Certificate';
                }
                
                // Get presigned URL
                $url = $this->getPresignedUrl($object['Key']);
                if ($url) {
                    $documentUrls[$docType] = [
                        'url' => $url,
                        'filename' => $filename,
                        'path' => $object['Key']
                    ];
                }
            }
        }
        
        return $documentUrls;
    } catch (Exception $e) {
        error_log("Error getting legal document URLs: " . $e->getMessage());
        return [];
    }
}

    /**
     * Deletes a file from S3
     */
    public function deleteFile($filePath) {
        try {
            $this->s3Client->deleteObject([
                'Bucket' => $this->bucketName,
                'Key'    => $filePath
            ]);
            return true;
        } catch (S3Exception $e) {
            error_log("Error deleting file {$filePath}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Sanitizes a filename to be safe for storage
     */
    private function sanitizeFileName($fileName) {
        // Replace special characters with underscore
        $sanitized = preg_replace('/[^a-zA-Z0-9_-]/', '_', $fileName);
        // Convert to lowercase
        $sanitized = strtolower($sanitized);
        // Limit length
        $sanitized = substr($sanitized, 0, 50);
        
        return $sanitized;
    }

}



