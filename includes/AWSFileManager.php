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
     * Create a new gym folder when a gym is approved
     * 
     * @param int $gymId The gym ID
     * @param string $gymName The gym name
     * @return string|boolean The folder path or false on failure
     */
    public function createGymFolder($gymId, $gymName) {
        try {
            $sanitizedName = $this->sanitizeFileName($gymName);
            $folderPath = "uploads/gyms/gym{$gymId}_{$sanitizedName}/";
            
            error_log("Creating gym folder structure at: " . $folderPath);
            
            // Create main gym folder
            if (!$this->createFolderIfNotExists($folderPath)) {
                error_log("Failed to create main gym folder: " . $folderPath);
                return false;
            }
            
            // Create subfolders 
            $subfolders = [
                'amenities/',
                'equipment/',
                'thumbnail/',
                'legal_documents/'
            ];
            
            foreach ($subfolders as $subfolder) {
                $subfolderPath = $folderPath . $subfolder;
                error_log("Creating subfolder: " . $subfolderPath);
                
                if (!$this->createFolderIfNotExists($subfolderPath)) {
                    error_log("Failed to create subfolder: " . $subfolderPath);
                    // Continue anyway to create other folders
                }
            }
            
            error_log("Successfully created gym folder structure for gym ID: " . $gymId);
            return $folderPath;
        } catch (Exception $e) {
            error_log("Error creating gym folder: " . $e->getMessage());
            return false;
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
            
            error_log("Checking if folder exists: " . $folderPath);
            
            // Check if folder exists
            try {
                $result = $this->s3Client->listObjects([
                    'Bucket' => $this->bucketName,
                    'Prefix' => $folderPath,
                    'MaxKeys' => 1
                ]);
                
                // If the folder exists, we'll receive something in the Contents
                if (isset($result['Contents']) && count($result['Contents']) > 0) {
                    error_log("Folder already exists: " . $folderPath);
                    return true;
                }
            } catch (S3Exception $e) {
                error_log("Error checking if folder exists: " . $e->getMessage());
            }
            
            // If we get here, the folder doesn't exist, so create it
            try {
                $this->s3Client->putObject([
                    'Bucket' => $this->bucketName,
                    'Key'    => $folderPath,
                    'Body'   => '',
                    'ACL'    => 'public-read'
                ]);
                
                error_log("Created folder: " . $folderPath);
                return true;
            } catch (S3Exception $e) {
                error_log("Error creating folder: " . $e->getMessage());
                return false;
            }
        } catch (Exception $e) {
            error_log("Error in createFolderIfNotExists: " . $e->getMessage());
            return false;
        }
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
     * 
     * @param string $filePath Path to the file in S3
     * @param string $expiryTime Expiry time string (e.g. '+30 minutes')
     * @return string|false The presigned URL or false on failure
     */
    public function getPresignedUrl($filePath, $expiryTime = '+30 minutes') {
        try {
            error_log("Getting presigned URL for: " . $filePath);
            
            // If it's already a full URL, just return it
            if (strpos($filePath, 'http') === 0) {
                return $filePath;
            }
            
            // Clean up the path - remove any leading slashes or "../"
            $filePath = ltrim($filePath, '/');
            $filePath = str_replace('../', '', $filePath);
            
            // Try to create a presigned URL
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
            
            // If the request fails, try a public URL as fallback
            try {
                // Try direct S3 URL
                $directUrl = "https://{$this->bucketName}.s3.{$this->region}.amazonaws.com/{$filePath}";
                error_log("Falling back to direct S3 URL: " . $directUrl);
                return $directUrl;
            } catch (Exception $innerException) {
                error_log("Fallback also failed: " . $innerException->getMessage());
                return false;
            }
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

    /**
    * Copies a file from one location to another in S3
    * 
    * @param string $sourcePath Source path of the file
    * @param string $destinationPath Destination path for the file
    * @param boolean $isPrivate Whether the destination file should be private
    * @param boolean $encrypt Whether to encrypt the destination file
    * @return boolean Success or failure
    */
    public function copyObject($sourcePath, $destinationPath, $isPrivate = true, $encrypt = true) {
        try {
            // Log paths for debugging
            error_log("Copying file from: {$sourcePath} to: {$destinationPath}");
            
            // Clean up the paths
            $sourcePath = ltrim($sourcePath, '/');
            $sourcePath = str_replace('../', '', $sourcePath);
            $destinationPath = ltrim($destinationPath, '/');
            $destinationPath = str_replace('../', '', $destinationPath);
            
            // Make sure source folder exists - it will be checked internally by S3
            
            // Ensure destination folder exists
            $destFolder = dirname($destinationPath) . '/';
            $this->createFolderIfNotExists($destFolder);
            
            // Prepare copy parameters
            $params = [
                'Bucket' => $this->bucketName,
                'CopySource' => $this->bucketName . '/' . $sourcePath,
                'Key' => $destinationPath,
                'ACL' => $isPrivate ? 'private' : 'public-read'
            ];
            
            // Add encryption if requested
            if ($encrypt) {
                $params['ServerSideEncryption'] = 'AES256';
            }
            
            // Perform the copy operation
            $this->s3Client->copyObject($params);
            
            error_log("Successfully copied file to: {$destinationPath}");
            return true;
        } catch (S3Exception $e) {
            error_log("Error copying file: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Deletes a folder and all its contents from S3
     * 
     * @param string $folderPath Folder path to delete
     * @return boolean Success or failure
     */
    public function deleteFolder($folderPath) {
        try {
            // Ensure folder path ends with a slash
            if (substr($folderPath, -1) !== '/') {
                $folderPath .= '/';
            }
            
            // Clean up the path
            $folderPath = ltrim($folderPath, '/');
            $folderPath = str_replace('../', '', $folderPath);
            
            error_log("Attempting to delete folder: {$folderPath}");
            
            // First list all objects in the folder
            $objects = $this->s3Client->listObjectsV2([
                'Bucket' => $this->bucketName,
                'Prefix' => $folderPath
            ]);
            
            if (!isset($objects['Contents']) || count($objects['Contents']) === 0) {
                error_log("No objects found in folder: {$folderPath}");
                return true; // Folder doesn't exist or is already empty
            }
            
            // Create a delete request for all objects
            $objectsToDelete = [];
            foreach ($objects['Contents'] as $object) {
                $objectsToDelete[] = ['Key' => $object['Key']];
            }
            
            // Delete all objects in the folder
            $this->s3Client->deleteObjects([
                'Bucket' => $this->bucketName,
                'Delete' => [
                    'Objects' => $objectsToDelete,
                    'Quiet' => false
                ]
            ]);
            
            error_log("Successfully deleted folder and its contents: {$folderPath}");
            return true;
        } catch (S3Exception $e) {
            error_log("Error deleting folder: " . $e->getMessage());
            return false;
        }
    }

    /**
    * Lists all files in a directory in S3
    * 
    * @param string $prefix Directory prefix to list
    * @return array List of file paths
    */
    public function listFiles($prefix) {
        try {
            error_log("Listing files with prefix: " . $prefix);
            
            $result = $this->s3Client->listObjects([
                'Bucket' => $this->bucketName,
                'Prefix' => $prefix
            ]);
            
            $files = [];
            if (isset($result['Contents'])) {
                foreach ($result['Contents'] as $object) {
                    $files[] = $object['Key'];
                    error_log("Found file: " . $object['Key']);
                }
            }
            
            return $files;
        } catch (S3Exception $e) {
            error_log("Error listing files: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Returns the bucket name
     * 
     * @return string The bucket name
     */
    public function getBucketName() {
        return $this->bucketName;
    }

    }
