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
    
    /**
     * Initialize AWS S3 client for Lightsail
     */
    public function __construct() {
        // Load AWS configuration - FIX THIS PATH
        $aws_config_path = __DIR__ . '/../private/config/aws-config.php';
        $aws_config = file_exists($aws_config_path) ? require($aws_config_path) : [
            'credentials' => ['key' => '', 'secret' => ''],
            'region' => 'ap-southeast-1',
            'bucket' => 'fithubconnect-bucket'
        ];
        
        $this->bucketName = $aws_config['bucket'];
        $this->region = $aws_config['region'];
        $this->baseUrl = "https://s3.{$this->region}.amazonaws.com/{$this->bucketName}/";
        
        try {
            $this->s3Client = new S3Client([
                'version' => 'latest',
                'region'  => $this->region,
                'credentials' => [
                    'key'    => $aws_config['credentials']['key'],
                    'secret' => $aws_config['credentials']['secret'],
                ]
            ]);
            
            // Check if the uploads folder exists, create if it doesn't
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
            'uploads/gyms/'
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
            
            // Check if folder exists
            $result = $this->s3Client->listObjects([
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
            }
            
            return true;
        } catch (S3Exception $e) {
            error_log("Error creating folder {$folderPath}: " . $e->getMessage());
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
     * @return string|false The URL of the uploaded file or false on failure
     */
    public function uploadFile($tempFilePath, $targetPath, $isPrivate = true, $encrypt = false) {
        if (!file_exists($tempFilePath)) {
            error_log("Upload error: Temporary file not found at {$tempFilePath}");
            return false;
        }
        
        try {
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
            
            // Return the URL of the uploaded object
            return $isPrivate ? $targetPath : $result['ObjectURL'];
            
        } catch (S3Exception $e) {
            error_log("AWS Upload Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Uploads a profile picture
     */
    public function uploadProfilePicture($tempFilePath, $userId, $fileName) {
        $ext = pathinfo($fileName, PATHINFO_EXTENSION);
        $newFileName = "user_{$userId}_" . time() . "." . $ext;
        $targetPath = "uploads/profile_pictures/{$newFileName}";
        
        return $this->uploadFile($tempFilePath, $targetPath, false); // Public, not encrypted
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
            
            return $this->uploadFile($tempFilePath, $targetPath, false); // Public, not encrypted
        }
        
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
            
            return $this->uploadFile($tempFilePath, $targetPath, false); // Public, not encrypted
        }
        
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
            
            return $this->uploadFile($tempFilePath, $targetPath, false); // Public, not encrypted
        }
        
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
     * 
     * @param string $filePath Path to the file in S3
     * @param string $expiryTime Expiry time string (e.g. '+30 minutes')
     * @return string|false The presigned URL or false on failure
     */
    public function getPresignedUrl($filePath, $expiryTime = '+30 minutes') {
        try {
            // Log path for debugging
            error_log("Generating presigned URL for path: " . $filePath);
            
            // If it's already a full URL, just return it
            if (strpos($filePath, 'http') === 0) {
                error_log("Path is already a URL, returning as is");
                return $filePath;
            }
            
            // Clean up the path - remove any leading slashes or "../"
            $filePath = ltrim($filePath, '/');
            $filePath = str_replace('../', '', $filePath);
            
            // If the path doesn't exist in S3, try to find alternative paths
            $exists = false;
            
            try {
                // Check if the file exists first
                $this->s3Client->headObject([
                    'Bucket' => $this->bucketName,
                    'Key'    => $filePath
                ]);
                $exists = true;
            } catch (S3Exception $e) {
                error_log("File not found in S3 at path: " . $filePath);
                
                // Try alternative paths
                $alternative_paths = [
                    "uploads/" . basename($filePath),
                    "uploads/legal_documents/" . basename($filePath),
                    "uploads/legal_documents/pending/user_" . basename($filePath)
                ];
                
                foreach ($alternative_paths as $alt_path) {
                    error_log("Trying alternative path: " . $alt_path);
                    try {
                        $this->s3Client->headObject([
                            'Bucket' => $this->bucketName,
                            'Key'    => $alt_path
                        ]);
                        $filePath = $alt_path;
                        $exists = true;
                        error_log("Found file at alternative path: " . $alt_path);
                        break;
                    } catch (S3Exception $e) {
                        // Continue to next path
                    }
                }
            }
            
            if (!$exists) {
                error_log("File not found in S3, all alternatives exhausted");
                return false;
            }
            
            // Create a presigned URL with expiration
            $cmd = $this->s3Client->getCommand('GetObject', [
                'Bucket' => $this->bucketName,
                'Key'    => $filePath
            ]);
            
            $request = $this->s3Client->createPresignedRequest($cmd, $expiryTime);
            $presignedUrl = (string)$request->getUri();
            
            error_log("Generated presigned URL: " . $presignedUrl);
            return $presignedUrl;
        } catch (Exception $e) {
            error_log("Error generating presigned URL: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Gets public URL for a file (for non-private files)
     * 
     * @param string $filePath Path to the file in S3
     * @return string|false The public URL or false on failure
     */
    public function getPublicUrl($filePath) {
        try {
            // Clean up the path
            $filePath = ltrim($filePath, '/');
            $filePath = str_replace('../', '', $filePath);
            
            // Generate the standard S3 URL
            $url = "https://{$this->bucketName}.s3.{$this->region}.amazonaws.com/{$filePath}";
            return $url;
        } catch (Exception $e) {
            error_log("Error generating public URL: " . $e->getMessage());
            return false;
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