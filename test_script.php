// First, verify the credentials are working by running this test script
<?php
require_once __DIR__ . '/vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

try {
    // Create an S3Client
    $s3Client = new S3Client([
        'version' => 'latest',
        'region' => 'ap-southeast-1',
        'credentials' => [
            'key' => 'AKIA3IGE32DHK4JDED4H',
            'secret' => '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'
        ]
    ]);
    
    // Try a basic operation - list buckets
    $result = $s3Client->listBuckets();
    echo "Connected successfully!\n";
    echo "Available buckets:\n";
    foreach ($result['Buckets'] as $bucket) {
        echo $bucket['Name'] . "\n";
    }
    
} catch (AwsException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "AWS Error Code: " . $e->getAwsErrorCode() . "\n";
    echo "AWS Error Type: " . $e->getAwsErrorType() . "\n";
}