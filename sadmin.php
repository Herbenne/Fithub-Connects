<?php
include 'config/database.php';

$password = "admin123"; // You can change this password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

$query = "INSERT INTO users (
    unique_id,
    username,
    email,
    password,
    full_name,
    role
) VALUES (
    'SADM001',
    'superadmin',
    'superadmin@admin.com',
    ?,
    'Super Admin',
    'superadmin'
)";

$stmt = $db_connection->prepare($query);
$stmt->bind_param("s", $hashed_password);

if ($stmt->execute()) {
    echo "Superadmin created successfully!\n";
    echo "Username: superadmin\n";
    echo "Password: " . $password . "\n";
} else {
    echo "Error creating superadmin: " . $stmt->error;
}

$stmt->close();
$db_connection->close();
