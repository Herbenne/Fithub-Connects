<?php
// Load environment variables from .env file
$env_path = __DIR__ . '/../.env';
if (file_exists($env_path)) {
    $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        
        // Set environment variable
        putenv("$name=$value");
        $_ENV[$name] = $value;
    }
}

// Get database config from environment variables
$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: ''; // If you have a password, it will come from .env
$db = getenv('DB_NAME') ?: 'gymdb1';
$port = getenv('DB_PORT') ?: 3306;

// Define constants for AWS integration
define('USE_AWS', getenv('USE_AWS') === 'true');
define('UPLOAD_DIR', '../uploads/');  // Local upload directory as fallback

// Load AWS configuration from private file if needed
if (USE_AWS) {
    $aws_config_path = __DIR__ . '/../private/config/aws-config.php';
    if (file_exists($aws_config_path)) {
        $aws_config = require($aws_config_path);
    }
}

// PayMongo API keys
$paymongo_public_key = getenv('PAYMONGO_PUBLIC_KEY');
$paymongo_secret_key = getenv('PAYMONGO_SECRET_KEY');

$db_connection = new mysqli($host, $user, $pass, $db, $port);

if ($db_connection->connect_error) {
    die("Connection failed: " . $db_connection->connect_error);
}