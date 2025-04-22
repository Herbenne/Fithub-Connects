<?php
// Purpose: Set up AWS S3 bucket folder structure and test connection

// Try to load the database config, but don't fail if it can't connect
try {
    require_once __DIR__ . '/../config/database.php';
} catch (Exception $e) {
    echo "⚠️ Database connection warning: " . $e->getMessage() . "\n";
    echo "Continuing with AWS setup...\n\n";
    // Define AWS constants if not already defined
    if (!defined('USE_AWS')) define('USE_AWS', true);
}

echo "================================================================\n";
echo "  FitHub AWS Lightsail Bucket Setup and Test Script\n";
echo "================================================================\n\n";

// Check if AWS SDK is available
if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
    echo "❌ Vendor directory not found. Please run 'composer install' first.\n";
    exit(1);
}

require_once __DIR__ . '/../vendor/autoload.php';

// Check for AWS classes
if (!class_exists('Aws\S3\S3Client')) {
    echo "❌ AWS SDK not found. Please run 'composer install' first.\n";
    exit(1);
}

// Look for AWS credentials in the private config file
$aws_config_path = __DIR__ . '/../private/config/aws-config.php';
if (file_exists($aws_config_path)) {
    $aws_config = require($aws_config_path);
    echo "✅ Found AWS configuration file\n";
    
    // Check credentials in config file
    if (empty($aws_config['credentials']['key']) || empty($aws_config['credentials']['secret'])) {
        echo "❌ AWS credentials are empty in the config file. Please update your credentials.\n";
        exit(1);
    }
    
    echo "🔍 Checking AWS configuration...\n";
    echo "  - AWS Region: " . $aws_config['region'] . "\n";
    echo "  - AWS Bucket: " . $aws_config['bucket'] . "\n\n";
} else {
    // Fall back to environment variables
    if (empty(getenv('AWS_ACCESS_KEY_ID')) || empty(getenv('AWS_SECRET_ACCESS_KEY'))) {
        echo "❌ AWS credentials not found. Please:\n";
        echo "  1. Create a file at: $aws_config_path\n";
        echo "  2. Add your AWS credentials to this file\n";
        echo "  3. Run this script again\n";
        exit(1);
    }
    
    echo "🔍 Checking AWS configuration from environment...\n";
    echo "  - AWS Region: " . getenv('AWS_REGION') . "\n";
    echo "  - AWS Bucket: " . getenv('AWS_BUCKET_NAME') . "\n\n";
}

try {
    require_once __DIR__ . '/../includes/AWSFileManager.php';
} catch (Exception $e) {
    echo "❌ Failed to load AWSFileManager class: " . $e->getMessage() . "\n";
    echo "Make sure you've created the includes/AWSFileManager.php file.\n";
    exit(1);
}

// Initialize AWS File Manager
try {
    echo "🔄 Initializing AWS File Manager...\n";
    $awsManager = new AWSFileManager();
    echo "✅ Connection to AWS successful!\n\n";
} catch (Exception $e) {
    echo "❌ Failed to connect to AWS: " . $e->getMessage() . "\n";
    exit(1);
}

// Create base folder structure
echo "🔄 Creating base folder structure...\n";
$baseFolders = [
    'uploads/',
    'uploads/profile_pictures/',
    'uploads/legal_documents/',
    'uploads/gyms/'
];

foreach ($baseFolders as $folder) {
    try {
        if ($awsManager->createFolderIfNotExists($folder)) {
            echo "  ✅ Created folder: $folder\n";
        } else {
            echo "  ℹ️ Folder already exists: $folder\n";
        }
    } catch (Exception $e) {
        echo "  ❌ Failed to create folder $folder: " . $e->getMessage() . "\n";
    }
}
echo "\n";

// Create a test gym folder
echo "🔄 Creating a test gym folder...\n";
$testGymId = 999;
$testGymName = "Test Gym";

try {
    $folderPath = $awsManager->createGymFolder($testGymId, $testGymName);
    if ($folderPath) {
        echo "  ✅ Created test gym folder: $folderPath\n";
        
        // List subfolders
        echo "  ✅ Created subfolders:\n";
        echo "    - {$folderPath}amenities/\n";
        echo "    - {$folderPath}equipment/\n";
        echo "    - {$folderPath}thumbnail/\n";
    } else {
        echo "  ❌ Failed to create test gym folder\n";
    }
} catch (Exception $e) {
    echo "  ❌ Error creating test gym folder: " . $e->getMessage() . "\n";
}
echo "\n";

// Test uploading a simple text file
echo "🔄 Testing file upload...\n";
$testFilePath = __DIR__ . '/test_file.txt';
$testFileContent = "This is a test file created by FitHub Connects setup script\nTimestamp: " . date('Y-m-d H:i:s');

// Create test file
file_put_contents($testFilePath, $testFileContent);

// Upload test file
try {
    $uploadPath = "uploads/test_file_" . time() . ".txt";
    $result = $awsManager->uploadFile($testFilePath, $uploadPath, false);
    
    if ($result) {
        echo "  ✅ Successfully uploaded test file to: $uploadPath\n";
        
        // Test get a presigned URL
        $presignedUrl = $awsManager->getPresignedUrl($uploadPath);
        if ($presignedUrl) {
            echo "  ✅ Generated presigned URL successfully\n";
            echo "  📎 URL: $presignedUrl\n";
            echo "  ℹ️ This URL will expire in 30 minutes\n";
        } else {
            echo "  ❌ Failed to generate presigned URL\n";
        }
    } else {
        echo "  ❌ Failed to upload test file\n";
    }
} catch (Exception $e) {
    echo "  ❌ Error uploading test file: " . $e->getMessage() . "\n";
}

// Clean up
if (file_exists($testFilePath)) {
    unlink($testFilePath);
}
echo "\n";

echo "================================================================\n";
echo "  Setup Complete! \n";
echo "================================================================\n";
echo "\nYour AWS Lightsail bucket is now configured for use with FitHub.\n";
echo "You can now upload files to your bucket through the application.\n\n";
echo "For more information, see the README.md file.\n";