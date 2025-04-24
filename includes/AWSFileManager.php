<?php
// AWSFileManager.php
require 'vendor/autoload.php'; // Make sure AWS SDK is properly included

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

class AWSFileManager {
    private $s3Client;
    private $bucketName = 'fithubconnect-bucket'; // Your actual bucket name
    
    public function __construct() {
        // Initialize AWS S3 client
        $this->s3Client = new S3Client([
            'version' => 'latest',
            'region' => 'ap-southeast-1', // Update with your region
            'credentials' => [
                'key' => AWS_ACCESS_KEY_ID,
                'secret' => AWS_SECRET_ACCESS_KEY,
            ]
        ]);
    }
    
    /**
     * Upload a file to S3 with public access
     * Used for: profile pictures, gym thumbnails, amenity images, equipment images
     */
    public function uploadPublicFile($tmp_path, $destination_path, $filename) {
        $key = $destination_path . '/' . $this->generateUniqueFilename($filename);
        
        try {
            $result = $this->s3Client->putObject([
                'Bucket' => $this->bucketName,
                'Key' => $key,
                'SourceFile' => $tmp_path,
                'ACL' => 'public-read', // Make it publicly accessible
                'ContentType' => $this->getContentType($filename)
            ]);
            
            return $key; // Return the path to store in database
        } catch (AwsException $e) {
            error_log("AWS upload error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Upload a file to S3 with private access
     * Used for: legal documents
     */
    public function uploadPrivateFile($tmp_path, $destination_path, $filename) {
        $key = $destination_path . '/' . $this->generateUniqueFilename($filename);
        
        try {
            $result = $this->s3Client->putObject([
                'Bucket' => $this->bucketName,
                'Key' => $key,
                'SourceFile' => $tmp_path,
                'ACL' => 'private', // Keep it private
                'ContentType' => $this->getContentType($filename)
            ]);
            
            return $key; // Return the path to store in database
        } catch (AwsException $e) {
            error_log("AWS upload error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Upload a profile picture (public)
     */
    public function uploadProfilePicture($tmp_path, $user_id, $filename) {
        $destination = "profile_pictures/user_{$user_id}";
        return $this->uploadPublicFile($tmp_path, $destination, $filename);
    }
    
    /**
     * Upload gym thumbnail or image (public)
     */
    public function uploadGymImage($tmp_path, $gym_id, $filename, $type = 'thumbnail') {
        $destination = "gyms/{$gym_id}/{$type}";
        return $this->uploadPublicFile($tmp_path, $destination, $filename);
    }
    
    /**
     * Upload equipment images (public)
     */
    public function uploadEquipmentImage($tmp_path, $gym_id, $filename) {
        $destination = "gyms/{$gym_id}/equipment";
        return $this->uploadPublicFile($tmp_path, $destination, $filename);
    }
    
    /**
     * Upload legal document (private)
     */
    public function uploadPendingLegalDocument($tmp_path, $user_id, $filename, $doc_type) {
        $destination = "legal_documents/pending/user_{$user_id}";
        return $this->uploadPrivateFile($tmp_path, $destination, "{$doc_type}_" . $filename);
    }
    
    /**
     * Upload approved legal document (private)
     */
    public function uploadApprovedLegalDocument($tmp_path, $gym_id, $filename, $doc_type) {
        $destination = "legal_documents/approved/gym_{$gym_id}";
        return $this->uploadPrivateFile($tmp_path, $destination, "{$doc_type}_" . $filename);
    }
    
    /**
     * Get a public URL for public files
     */
    public function getPublicUrl($key) {
        if (empty($key)) return false;
        
        // If it's already a full URL, return it
        if (strpos($key, 'http') === 0) {
            return $key;
        }
        
        // Otherwise, construct the URL
        return "https://{$this->bucketName}.s3.amazonaws.com/" . ltrim($key, '/');
    }
    
    /**
     * Generate a temporary signed URL for private files
     */
    public function getSecureUrl($key, $expires = '+30 minutes') {
        if (empty($key)) return false;
        
        try {
            $cmd = $this->s3Client->getCommand('GetObject', [
                'Bucket' => $this->bucketName,
                'Key' => ltrim($key, '/')
            ]);
            
            $request = $this->s3Client->createPresignedRequest($cmd, $expires);
            return (string) $request->getUri();
        } catch (Exception $e) {
            error_log("Failed to generate pre-signed URL: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Helper: Generate a unique filename to prevent overwriting
     */
    private function generateUniqueFilename($filename) {
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        return time() . '_' . uniqid() . '.' . $ext;
    }
    
    /**
     * Helper: Get the appropriate content type for a file
     */
    private function getContentType($filename) {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $types = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];
        return $types[$ext] ?? 'application/octet-stream';
    }
}