<?php
// Initialize variables
$monthly_revenue = [];

// cURL request to fetch payment details from the API
$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => "https://api.paymongo.com/v1/payments?limit=10",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_HTTPHEADER => [
        "accept: application/json",
        "authorization: Basic c2tfdGVzdF9CeGRpRVpyeDRXOFRMUVBRSkpYN2hhVHQ6SGVyYmVubmUhMjM0"
    ],
]);

$response = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

if ($err) {
    echo "cURL Error #:" . $err;
    exit;
} else {
    // Decode the JSON response
    $data = json_decode($response, true);

    // Process the payments and calculate monthly revenue
    if (isset($data['data']) && is_array($data['data'])) {
        foreach ($data['data'] as $payment) {
            // Extract the month and year from the created_at field
            $payment_date = date('Y-m', strtotime($payment['attributes']['created_at']));

            // Accumulate the revenue for the month
            if (isset($monthly_revenue[$payment_date])) {
                $monthly_revenue[$payment_date] += $payment['attributes']['amount'];
            } else {
                $monthly_revenue[$payment_date] = $payment['attributes']['amount'];
            }
        }
    }
}

// Return the data in JSON format for the chart
echo json_encode(['monthlyRevenue' => $monthly_revenue]);
