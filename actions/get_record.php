<?php
session_start();
include '../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    http_response_code(403);
    exit('Unauthorized');
}

$table = $_GET['table'] ?? '';
$id = $_GET['id'] ?? '';

if (empty($table) || empty($id)) {
    http_response_code(400);
    exit('Missing parameters');
}

// Get primary key column
$pk_query = "SHOW KEYS FROM `$table` WHERE Key_name = 'PRIMARY'";
$pk_result = $db_connection->query($pk_query);
$pk_data = $pk_result->fetch_assoc();
$pk_column = $pk_data['Column_name'];

// Fetch record
$query = "SELECT * FROM `$table` WHERE `$pk_column` = ?";
$stmt = $db_connection->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$record = $result->fetch_assoc();

header('Content-Type: application/json');
echo json_encode($record);