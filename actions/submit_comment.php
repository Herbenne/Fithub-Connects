<?php
session_start();
include '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../pages/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../pages/explore_gyms.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$review_id = $_POST['review_id'];
$gym_id = $_POST['gym_id'];
$comment = trim($_POST['comment']);

// Validate comment
if (empty($comment)) {
    $redirect_page = getRedirectPage();
    header("Location: ../pages/{$redirect_page}?gym_id=$gym_id&error=empty_comment");
    exit();
}

// Insert comment
$query = "INSERT INTO review_comments (review_id, user_id, comment) VALUES (?, ?, ?)";
$stmt = $db_connection->prepare($query);
$stmt->bind_param("iis", $review_id, $user_id, $comment);

if ($stmt->execute()) {
    $redirect_page = getRedirectPage();
    header("Location: ../pages/{$redirect_page}?gym_id=$gym_id&success=comment_submitted");
} else {
    $redirect_page = getRedirectPage();
    header("Location: ../pages/{$redirect_page}?gym_id=$gym_id&error=submission_failed");
}
exit();

// Helper function to determine redirect page based on user role
function getRedirectPage() {
    if (isset($_SESSION['role'])) {
        if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'superadmin') {
            return 'view_gym.php';
        }
    }
    return 'user_view_gym.php';
}