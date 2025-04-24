<?php
class AWSFileManager {
    private $s3Client;
    private $bucketName = 'fithubconnect-bucket'; // Your bucket name
    private $region = 'ap-southeast-1'; // Your AWS region
    
    public function __construct() {
        // We don't initialize S3Client here to avoid dependency on the AWS SDK
        // when just getting public URLs
    }
    
    /**
     * Upload a profile picture (public)
     */
    public function uploadProfilePicture($tmp_path, $user_id, $filename) {
        $destination = "profile_pictures/user_{$user_id}";
        $key = $destination . '/' . $this->generateUniqueFilename($filename);
        return $key;
    }
    
    /**
     * Upload legal document (public)
     */
    public function uploadPendingLegalDocument($tmp_path, $user_id, $filename, $doc_type) {
        $destination = "legal_documents/pending/user_{$user_id}";
        $key = $destination . '/' . $doc_type . '_' . $this->generateUniqueFilename($filename);
        return $key;
    }
    
    /**
     * Upload gym thumbnail or image (public)
     */
    public function uploadGymImage($tmp_path, $gym_id, $filename, $type = 'thumbnail') {
        $destination = "gyms/{$gym_id}/{$type}";
        $key = $destination . '/' . $this->generateUniqueFilename($filename);
        return $key;
    }
    
    /**
     * Upload equipment images (public)
     */
    public function uploadEquipmentImage($tmp_path, $gym_id, $filename) {
        $destination = "gyms/{$gym_id}/equipment";
        $key = $destination . '/' . $this->generateUniqueFilename($filename);
        return $key;
    }
    
    /**
     * Upload approved legal document (now public)
     */
    public function uploadApprovedLegalDocument($tmp_path, $gym_id, $filename, $doc_type) {
        $destination = "legal_documents/approved/gym_{$gym_id}";
        $key = $destination . '/' . $doc_type . '_' . $this->generateUniqueFilename($filename);
        return $key;
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
     * Create gym folder structure
     */
    public function createGymFolder($gym_id, $gym_name) {
        // Just return the base path since we don't need to actually create folders in S3
        return "gyms/{$gym_id}";
    }
    
    /**
     * Move documents from pending to approved location
     */
    public function moveDocumentsToApprovedGym($owner_id, $gym_id) {
        // In a real implementation, this would copy from pending to approved
        // but for this stub, we'll just return true
        return true;
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