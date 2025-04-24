<?php
ini_set('display_errors', 1);
require_once 'config/database.php';
require_once 'includes/AWSFileManager.php';

// Create test content
$temp_file = tempnam(sys_get_temp_dir(), 'test');
file_put_contents($temp_file, 'Test content');

// Try to upload
$awsManager = new AWSFileManager();
$key = $awsManager->uploadFileWithDebug($temp_file, 'test/test_file.txt');

echo "Uploaded test file with key: $key\n";
echo "URL would be: " . $awsManager->getPublicUrl($key) . "\n";
?>