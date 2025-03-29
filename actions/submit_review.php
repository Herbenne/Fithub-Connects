<?php
session_start();
include '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../pages/login.php");
    exit();
}

// Validate POST data
if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: ../pages/explore_gyms.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$gym_id = $_POST['gym_id'] ?? null;
$rating = $_POST['rating'] ?? null;
$comment = $_POST['comment'] ?? null;

// Determine the redirect page based on user role
function getRedirectPage() {
    if (isset($_SESSION['role'])) {
        if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'superadmin') {
            return 'view_gym.php';
        }
    }
    return 'user_view_gym.php';
}

$redirect_page = getRedirectPage();

// Validate inputs
if (!$gym_id || !$rating || !$comment) {
    header("Location: ../pages/$redirect_page?gym_id=$gym_id&error=missing_fields");
    exit();
}

// Check if user has already reviewed this gym
$check_query = "SELECT review_id FROM gym_reviews WHERE user_id = ? AND gym_id = ?";
$check_stmt = $db_connection->prepare($check_query);

if (!$check_stmt) {
    error_log("Error preparing check statement: " . $db_connection->error);
    header("Location: ../pages/$redirect_page?gym_id=$gym_id&error=db_error");
    exit();
}

$check_stmt->bind_param("ii", $user_id, $gym_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    header("Location: ../pages/$redirect_page?gym_id=$gym_id&error=already_reviewed");
    exit();
}

// Insert the review
$insert_query = "INSERT INTO gym_reviews (user_id, gym_id, rating, comment) VALUES (?, ?, ?, ?)";
$insert_stmt = $db_connection->prepare($insert_query);

if (!$insert_stmt) {
    error_log("Error preparing insert statement: " . $db_connection->error);
    header("Location: ../pages/$redirect_page?gym_id=$gym_id&error=db_error");
    exit();
}

$insert_stmt->bind_param("iiis", $user_id, $gym_id, $rating, $comment);

if ($insert_stmt->execute()) {
    header("Location: ../pages/$redirect_page?gym_id=$gym_id&success=review_submitted");
} else {
    error_log("Error executing insert: " . $insert_stmt->error);
    header("Location: ../pages/$redirect_page?gym_id=$gym_id&error=submit_failed");
}

$insert_stmt->close();
$check_stmt->close();
$db_connection->close();