<?php
$host = 'localhost';
$user = 'root';
$pass = ''; // If you have a password, update it here
$db = 'gymdb1';
$port = 3307;
$paymongo_public_key = getenv('PAYMONGO_PUBLIC_KEY');
$paymongo_secret_key = getenv('PAYMONGO_SECRET_KEY');

$db_connection = new mysqli($host, $user, $pass, $db, $port);

if ($db_connection->connect_error) {
    die("Connection failed: " . $db_connection->connect_error);
}
