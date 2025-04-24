<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

class AWSFileManager {
    private $s3Client;
    private $bucketName = 'fithubconnect-bucket';
    private $region = 'ap-southeast-1';
    
    public function __construct() {
        // Initialize AWS S3 client with credentials from config
        $this->s3Client = new S3Client([
            'version' => 'latest',
            'region' => $this->region,
            'credentials' => [
                'key' => AWS_ACCESS_KEY_ID,
                'secret' => AWS_SECRET_ACCESS_KEY,
            ]
        ]);
    }
    
    /**
     * Upload a profile picture (public)
     */
    public function uploadProfilePicture($tmp_path, $user_id, $filename) {
        $destination = "profile_pictures/user_{$user_id}";
        
        try {
            $key = $destination . '/' . $this->generateUniqueFilename($filename);
            
            // Actually upload the file to S3
            $result = $this->s3Client->putObject([
                'Bucket' => $this->bucketName,
                'Key' => $key,
                'SourceFile' => $tmp_path,
                'ACL' => 'public-read',
                'ContentType' => $this->getContentType($filename)
            ]);
            
            error_log("Successfully uploaded to S3: " . $key);
            return $key;
            
        } catch (AwsException $e) {
            error_log("AWS upload error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Upload legal document (private)
     */
    public function uploadPendingLegalDocument($tmp_path, $user_id, $filename, $doc_type) {
        $destination = "legal_documents/pending/user_{$user_id}";
        
        try {
            $key = $destination . '/' . $doc_type . '_' . $this->generateUniqueFilename($filename);
            
            // Actually upload the file to S3
            $result = $this->s3Client->putObject([
                'Bucket' => $this->bucketName,
                'Key' => $key,
                'SourceFile' => $tmp_path,
                'ACL' => 'public-read', // Now using public-read since bucket is public
                'ContentType' => $this->getContentType($filename)
            ]);
            
            error_log("Successfully uploaded legal document to S3: " . $key);
            return $key;
            
        } catch (AwsException $e) {
            error_log("AWS upload error for legal document: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Upload gym thumbnail or image (public)
     */
    public function uploadGymImage($tmp_path, $gym_id, $filename, $type = 'thumbnail') {
        $destination = "gyms/{$gym_id}/{$type}";
        
        try {
            $key = $destination . '/' . $this->generateUniqueFilename($filename);
            
            // Actually upload the file to S3
            $result = $this->s3Client->putObject([
                'Bucket' => $this->bucketName,
                'Key' => $key,
                'SourceFile' => $tmp_path,
                'ACL' => 'public-read',
                'ContentType' => $this->getContentType($filename)
            ]);
            
            error_log("Successfully uploaded gym image to S3: " . $key);
            return $key;
            
        } catch (AwsException $e) {
            error_log("AWS upload error for gym image: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Upload equipment images (public)
     */
    public function uploadEquipmentImage($tmp_path, $gym_id, $filename) {
        $destination = "gyms/{$gym_id}/equipment";
        
        try {
            $key = $destination . '/' . $this->generateUniqueFilename($filename);
            
            // Actually upload the file to S3
            $result = $this->s3Client->putObject([
                'Bucket' => $this->bucketName,
                'Key' => $key,
                'SourceFile' => $tmp_path,
                'ACL' => 'public-read',
                'ContentType' => $this->getContentType($filename)
            ]);
            
            error_log("Successfully uploaded equipment image to S3: " . $key);
            return $key;
            
        } catch (AwsException $e) {
            error_log("AWS upload error for equipment image: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Upload approved legal document (now public)
     */
    public function uploadApprovedLegalDocument($tmp_path, $gym_id, $filename, $doc_type) {
        $destination = "legal_documents/approved/gym_{$gym_id}";
        
        try {
            $key = $destination . '/' . $doc_type . '_' . $this->generateUniqueFilename($filename);
            
            // Actually upload the file to S3
            $result = $this->s3Client->putObject([
                'Bucket' => $this->bucketName,
                'Key' => $key,
                'SourceFile' => $tmp_path,
                'ACL' => 'public-read', // Now using public-read since bucket is public
                'ContentType' => $this->getContentType($filename)
            ]);
            
            error_log("Successfully uploaded approved legal document to S3: " . $key);
            return $key;
            
        } catch (AwsException $e) {
            error_log("AWS upload error for approved legal document: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get a public URL for files
     */
    public function getPublicUrl($key) {
        if (empty($key)) return false;
        
        // If it's already a full URL, return it
        if (strpos($key, 'http') === 0) {
            return $key;
        }
        
        // Otherwise, construct the correct URL with region
        return "https://{$this->bucketName}.s3.{$this->region}.amazonaws.com/" . ltrim($key, '/');
    }
    
    /**
     * Get a secure URL (same as public now since bucket is public)
     */
    public function getSecureUrl($key) {
        return $this->getPublicUrl($key);
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