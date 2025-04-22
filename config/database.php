<?php
// File: config/database.php

$host = 'localhost';
$user = 'root';
$pass = ''; // If you have a password, update it here
$db = 'gymdb1';
$port = 3306;

// Load AWS configuration from private file
$aws_config_path = __DIR__ . '/../private/config/aws-config.php';
$aws_config = file_exists($aws_config_path) ? require($aws_config_path) : [
    'credentials' => ['key' => '', 'secret' => ''],
    'region' => 'ap-southeast-1',
    'bucket' => 'fithubconnect-bucket', 
    'use_aws' => false
];

// Define constants for AWS integration
define('USE_AWS', $aws_config['use_aws'] ?? false);
define('UPLOAD_DIR', '../uploads/');  // Local upload directory as fallback

// PayMongo API keys
$paymongo_public_key = getenv('PAYMONGO_PUBLIC_KEY');
$paymongo_secret_key = getenv('PAYMONGO_SECRET_KEY');

$db_connection = new mysqli($host, $user, $pass, $db, $port);

if ($db_connection->connect_error) {
    die("Connection failed: " . $db_connection->connect_error);
}
