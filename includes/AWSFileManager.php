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
     * Uploads legal documents - these will be encrypted and private
     */
    public function uploadLegalDocument($tempFilePath, $gymId, $fileName, $documentType) {
        $ext = pathinfo($fileName, PATHINFO_EXTENSION);
        $folderPath = "uploads/legal_documents/gym_{$gymId}/";
        
        // Create legal documents folder for gym if doesn't exist
        $this->createFolderIfNotExists($folderPath);
        
        $newFileName = "{$documentType}_" . time() . "." . $ext;
        $targetPath = $folderPath . $newFileName;
        
        return $this->uploadFile($tempFilePath, $targetPath, true, true); // Private AND encrypted
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