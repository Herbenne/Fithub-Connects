<?php
// Include database connection
require 'db_connection.php';

if (isset($_GET['plan_id']) && isset($_GET['gym_id'])) {
    $plan_id = intval($_GET['plan_id']);
    $gym_id = intval($_GET['gym_id']);

    // Delete the plan from the database
    $delete_sql = "DELETE FROM membership_plans WHERE id = ? AND gym_id = ?";
    $stmt = $db_connection->prepare($delete_sql);
    $stmt->bind_param("ii", $plan_id, $gym_id);

    if ($stmt->execute()) {
        echo "Membership plan deleted successfully!";
        header("Location: gym_details.php?gym_id=" . $gym_id);
    } else {
        echo "Error deleting plan: " . $stmt->error;
    }

    $stmt->close();
    $db_connection->close();
} else {
    echo "Invalid plan ID or gym ID.";
}
