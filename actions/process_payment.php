<?php
session_start();
include '../config/database.php';

$paymongo_secret_key = "sk_test_BxdiEZrx4W8TLQPQJJX7haTt";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION['user_id'] ?? null;
    $user_email = $_SESSION['user_email'] ?? "test@example.com"; // Default email
    $gym_id = $_POST['gym_id'] ?? null;
    $plan_id = $_POST['membership_plans'] ?? null;

    if (!$user_id || !$gym_id || !$plan_id) {
        die("Invalid request. Missing required parameters.");
    }

    // Ensure database connection is established
    if (!$db_connection) {
        die("Database connection error.");
    }

    // Fetch membership plan details
    $query = "SELECT plan_name, price FROM membership_plans WHERE plan_id = ?";
    $stmt = $db_connection->prepare($query);
    $stmt->bind_param("i", $plan_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $plan = $result->fetch_assoc();
    $stmt->close();

    if (!$plan) {
        die("Plan not found.");
    }

    // Prepare PayMongo checkout session
    $amount = $plan['price'] * 100; // Convert PHP to cents
    $checkout_data = [
        "data" => [
            "attributes" => [
                "billing" => ["email" => $user_email], // User's email
                "line_items" => [
                    [
                        "currency" => "PHP",
                        "amount" => $amount,
                        "name" => $plan['plan_name'],
                        "quantity" => 1,
                    ]
                ],
                "payment_method_types" => ["gcash", "card"],
                "success_url" => "http://localhost/Capstone/pages/payment_success.php?gym_id=$gym_id&user_id=$user_id&plan_id=$plan_id",
                "cancel_url" => "http://localhost/Capstone/pages/gym_details.php?gym_id=$gym_id",
            ]
        ]
    ];

    // Make PayMongo API request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.paymongo.com/v1/checkout_sessions");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Basic " . base64_encode($paymongo_secret_key . ":"),
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($checkout_data));

    $response = curl_exec($ch);

    // Check for cURL errors
    if (curl_errno($ch)) {
        die("cURL error: " . curl_error($ch));
    }

    curl_close($ch);

    // Decode response
    $response_data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        die("JSON decoding error: " . json_last_error_msg());
    }

    // Redirect user to PayMongo checkout page
    if (isset($response_data['data']['attributes']['checkout_url'])) {
        header("Location: " . $response_data['data']['attributes']['checkout_url']);
        exit();
    } else {
        die("Payment processing error: " . $response);
    }
}
