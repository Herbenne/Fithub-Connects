<?php
require_once 'db_connection.php'; // Adjust the path to your actual db_connection.php file

if (isset($_GET['id'])) {
    $plan_id = intval($_GET['id']);

    // Prepare and execute the deletion query
    $stmt = $db_connection->prepare("DELETE FROM membership_plans WHERE id = ?");
    $stmt->bind_param("i", $plan_id);
    
    if ($stmt->execute()) {
        // Successfully deleted, redirect back to the admin dashboard
        header("Location: admin_dashboard.php?msg=Plan+deleted+successfully");
    } else {
        // Failed to delete, redirect back with an error message
        header("Location: admin_dashboard.php?msg=Failed+to+delete+plan");
    }

    $stmt->close();
} else {
    // No ID provided, redirect back with an error message
    header("Location: admin_dashboard.php?msg=Invalid+plan+ID");
}

$db_connection->close();
exit();
?>
