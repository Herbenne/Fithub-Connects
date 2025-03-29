<?php
session_start();
include '../config/database.php';

// Ensure user is superadmin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: ../pages/login.php");
    exit();
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$table = $_GET['table'] ?? $_POST['table'] ?? '';

if (empty($table)) {
    die("Table name is required");
}

switch ($action) {
    case 'delete':
        $id = $_GET['id'] ?? '';
        if ($id) {
            // Get primary key column
            $pk_query = "SHOW KEYS FROM `$table` WHERE Key_name = 'PRIMARY'";
            $pk_result = $db_connection->query($pk_query);
            $pk_data = $pk_result->fetch_assoc();
            $pk_column = $pk_data['Column_name'];

            // Prepare and execute delete query
            $query = "DELETE FROM `$table` WHERE `$pk_column` = ?";
            $stmt = $db_connection->prepare($query);
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                header("Location: ../pages/manage_table.php?table=$table&success=deleted");
            } else {
                header("Location: ../pages/manage_table.php?table=$table&error=delete_failed");
            }
        }
        break;

    case 'bulk_delete':
        $ids = $_GET['ids'] ?? '';
        if ($ids) {
            $id_array = explode(',', $ids);
            $pk_query = "SHOW KEYS FROM `$table` WHERE Key_name = 'PRIMARY'";
            $pk_result = $db_connection->query($pk_query);
            $pk_data = $pk_result->fetch_assoc();
            $pk_column = $pk_data['Column_name'];

            // Create placeholders for IN clause
            $placeholders = str_repeat('?,', count($id_array) - 1) . '?';
            $query = "DELETE FROM `$table` WHERE `$pk_column` IN ($placeholders)";
            
            $stmt = $db_connection->prepare($query);
            $types = str_repeat('i', count($id_array));
            $stmt->bind_param($types, ...$id_array);
            
            if ($stmt->execute()) {
                header("Location: ../pages/manage_table.php?table=$table&success=bulk_deleted");
            } else {
                header("Location: ../pages/manage_table.php?table=$table&error=bulk_delete_failed");
            }
        }
        break;

    case 'update':
        // Handle record update
        $id = $_POST['id'] ?? '';
        if ($id) {
            $pk_query = "SHOW KEYS FROM `$table` WHERE Key_name = 'PRIMARY'";
            $pk_result = $db_connection->query($pk_query);
            $pk_data = $pk_result->fetch_assoc();
            $pk_column = $pk_data['Column_name'];

            $updates = [];
            $params = [];
            $types = '';

            foreach ($_POST as $key => $value) {
                if ($key !== 'action' && $key !== 'table' && $key !== 'id') {
                    $updates[] = "`$key` = ?";
                    $params[] = $value;
                    $types .= 's'; // Assuming string type for all fields
                }
            }

            $params[] = $id;
            $types .= 'i';

            $query = "UPDATE `$table` SET " . implode(', ', $updates) . " WHERE `$pk_column` = ?";
            $stmt = $db_connection->prepare($query);
            $stmt->bind_param($types, ...$params);
            
            if ($stmt->execute()) {
                header("Location: ../pages/manage_table.php?table=$table&success=updated");
            } else {
                header("Location: ../pages/manage_table.php?table=$table&error=update_failed");
            }
        }
        break;
}

exit();