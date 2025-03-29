<?php
session_start();
include '../config/database.php';

if (!isset($_SESSION['user_id']) || !isset($_POST['comment_id']) || !isset($_POST['gym_id'])) {
    header("Location: ../pages/explore_gyms.php");
    exit();
}

$comment_id = $_POST['comment_id'];
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
$query = "SELECT c.*, g.owner_id 
          FROM review_comments c 
          JOIN gym_reviews r ON c.review_id = r.review_id 
          JOIN gyms g ON r.gym_id = g.gym_id 
          WHERE c.comment_id = ?";
$stmt = $db_connection->prepare($query);
$stmt->bind_param("i", $comment_id);
$stmt->execute();
$comment = $stmt->get_result()->fetch_assoc();

$redirect_page = getRedirectPage();

if ($comment && 
    ($_SESSION['user_id'] == $comment['user_id'] || // Own comment
     $_SESSION['role'] == 'superadmin' || // Superadmin
     ($_SESSION['role'] == 'admin' && $_SESSION['user_id'] == $comment['owner_id'])) // Gym owner
) {
    $delete_query = "DELETE FROM review_comments WHERE comment_id = ?";
    $stmt = $db_connection->prepare($delete_query);
    $stmt->bind_param("i", $comment_id);
    
    if ($stmt->execute()) {
        header("Location: ../pages/$redirect_page?gym_id=$gym_id&success=comment_deleted");
    } else {
        header("Location: ../pages/$redirect_page?gym_id=$gym_id&error=deletion_failed");
    }
} else {
    header("Location: ../pages/$redirect_page?gym_id=$gym_id&error=permission_denied");
}
exit();