<?php
session_start();
require_once __DIR__ . "/../config/database.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Access denied.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $member_id = $_POST['member_id'] ?? null;
    $plan_id = $_POST['plan_id'] ?? null;
    $end_date = $_POST['end_date'] ?? null;
    $status = $_POST['status'] ?? 'active';

    if (!$member_id || !$plan_id || !$end_date) {
        die("Missing required fields.");
    }

    $query = "UPDATE gym_members 
              SET plan_id = ?, 
                  end_date = ?,
                  status = ?
              WHERE id = ?";

    $stmt = $db_connection->prepare($query);
    $stmt->bind_param("issi", $plan_id, $end_date, $status, $member_id);

    if ($stmt->execute()) {
        header("Location: ../pages/manage_members.php?success=1");
        exit();
    } else {
        header("Location: ../pages/manage_members.php?error=1");
        exit();
    }
}