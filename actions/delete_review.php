<?php
session_start();
include '../config/database.php';

if (!isset($_SESSION['user_id']) || !isset($_POST['review_id']) || !isset($_POST['gym_id'])) {
    header("Location: ../pages/explore_gyms.php");
    exit();
}

$review_id = $_POST['review_id'];
$gym_id = $_POST['gym_id'];

// Helper function to determine redirect page
function getRedirectPage() {
    if (isset($_SESSION['role'])) {
        if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'superadmin') {
            return 'view_gym.php';
        }
    }
    return 'user_view_gym.php';
}

// Check if user has permission to delete
$query = "SELECT r.*, g.owner_id FROM gym_reviews r 
          JOIN gyms g ON r.gym_id = g.gym_id 
          WHERE r.review_id = ?";
$stmt = $db_connection->prepare($query);
$stmt->bind_param("i", $review_id);
$stmt->execute();
$review = $stmt->get_result()->fetch_assoc();

$redirect_page = getRedirectPage();

if ($review && 
    ($_SESSION['user_id'] == $review['user_id'] || // Own review
     $_SESSION['role'] == 'superadmin' || // Superadmin
     ($_SESSION['role'] == 'admin' && $_SESSION['user_id'] == $review['owner_id'])) // Gym owner
) {
    $delete_query = "DELETE FROM gym_reviews WHERE review_id = ?";
    $stmt = $db_connection->prepare($delete_query);
    $stmt->bind_param("i", $review_id);
    
    if ($stmt->execute()) {
        header("Location: ../pages/$redirect_page?gym_id=$gym_id&success=review_deleted");
    } else {
        header("Location: ../pages/$redirect_page?gym_id=$gym_id&error=delete_failed");
    }
} else {
    header("Location: ../pages/$redirect_page?gym_id=$gym_id&error=permission_denied");
}
exit();