<?php
include 'db_connection.php'; // Ensure the path is correct

// Fetch Total Counts with Error Handling
$totalUsersQuery = "SELECT COUNT(*) as count FROM users";
$totalUsersResult = mysqli_query($db_connection, $totalUsersQuery) or die("Error in query: $totalUsersQuery - " . mysqli_error($db_connection));
$totalUsers = mysqli_fetch_assoc($totalUsersResult)['count'];

$totalGymsQuery = "SELECT COUNT(*) as count FROM gyms";
$totalGymsResult = mysqli_query($db_connection, $totalGymsQuery) or die("Error in query: $totalGymsQuery - " . mysqli_error($db_connection));
$totalGyms = mysqli_fetch_assoc($totalGymsResult)['count'];

$totalMembershipsQuery = "SELECT COUNT(*) as count FROM membership_plans";
$totalMembershipsResult = mysqli_query($db_connection, $totalMembershipsQuery) or die("Error in query: $totalMembershipsQuery - " . mysqli_error($db_connection));
$totalMemberships = mysqli_fetch_assoc($totalMembershipsResult)['count'];

$totalAttendanceQuery = "SELECT COUNT(*) as count FROM attendance";
$totalAttendanceResult = mysqli_query($db_connection, $totalAttendanceQuery) or die("Error in query: $totalAttendanceQuery - " . mysqli_error($db_connection));
$totalAttendance = mysqli_fetch_assoc($totalAttendanceResult)['count'];

// Fetch Role Distribution
$roleDistributionQuery = "SELECT role, COUNT(*) as count FROM admins GROUP BY role";
$roleDistributionResult = mysqli_query($db_connection, $roleDistributionQuery) or die("Error in query: $roleDistributionQuery - " . mysqli_error($db_connection));
$roles = [];
$roleCounts = [];
while ($row = mysqli_fetch_assoc($roleDistributionResult)) {
    $roles[] = $row['role'];
    $roleCounts[] = $row['count'];
}

// Fetch Gyms per Admin
$gymsPerAdminQuery = "SELECT admins.username, COUNT(gyms.gym_id) as gym_count FROM gyms JOIN admins ON gyms.gym_id = admins.gym_id GROUP BY admins.username";
$gymsPerAdminResult = mysqli_query($db_connection, $gymsPerAdminQuery) or die("Error in query: $gymsPerAdminQuery - " . mysqli_error($db_connection));
$admins = [];
$gymCounts = [];
while ($row = mysqli_fetch_assoc($gymsPerAdminResult)) {
    $admins[] = $row['username'];
    $gymCounts[] = $row['gym_count'];
}

// Fetch Membership Plans per Gym
$membershipPerGymQuery = "SELECT gym_name AS gym_name, COUNT(membership_plans.id) AS plan_count FROM membership_plans JOIN gyms ON membership_plans.gym_id = gyms.gym_id GROUP BY gyms.gym_name";
$membershipPerGymResult = mysqli_query($db_connection, $membershipPerGymQuery) or die("Error in query: $membershipPerGymQuery - " . mysqli_error($db_connection));
$gymNames = [];
$planCounts = [];
while ($row = mysqli_fetch_assoc($membershipPerGymResult)) {
    $gymNames[] = $row['gym_name'];
    $planCounts[] = $row['plan_count'];
}

// Monthly User Registrations
$monthlyRegistrationsQuery = "SELECT DATE_FORMAT(reg_date, '%Y-%m') AS month, COUNT(*) AS count FROM users GROUP BY DATE_FORMAT(reg_date, '%Y-%m')";
$monthlyRegistrationsResult = mysqli_query($db_connection, $monthlyRegistrationsQuery) or die("Error in query: $monthlyRegistrationsQuery - " . mysqli_error($db_connection));
$months = [];
$userCounts = [];
while ($row = mysqli_fetch_assoc($monthlyRegistrationsResult)) {
    $months[] = $row['month'];
    $userCounts[] = $row['count'];
}

// Fetch Applications by Status
$applicationsByStatusQuery = "SELECT status, COUNT(*) as count FROM gyms_applications GROUP BY status";
$applicationsByStatusResult = mysqli_query($db_connection, $applicationsByStatusQuery) or die("Error in query: $applicationsByStatusQuery - " . mysqli_error($db_connection));
$statuses = [];
$statusCounts = [];
while ($row = mysqli_fetch_assoc($applicationsByStatusResult)) {
    $statuses[] = $row['status'];
    $statusCounts[] = $row['count'];
}

$db_connection->close();
