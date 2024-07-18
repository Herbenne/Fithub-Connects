<?php

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
} else {
    // Decode the JSON response
    $data = json_decode($response, true);
    
    // Check if the data key exists
    if (isset($data['data']) && is_array($data['data'])) {
        echo '<h2>Payments List</h2>';
        echo '<table border="1" cellpadding="5" cellspacing="0">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>ID</th>';
        echo '<th>Amount</th>';
        echo '<th>Currency</th>';
        echo '<th>Status</th>';
        echo '<th>Description</th>';
        echo '<th>Created At</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($data['data'] as $payment) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($payment['id']) . '</td>';
            echo '<td>' . htmlspecialchars($payment['attributes']['amount']) . '</td>';
            echo '<td>' . htmlspecialchars($payment['attributes']['currency']) . '</td>';
            echo '<td>' . htmlspecialchars($payment['attributes']['status']) . '</td>';
            echo '<td>' . htmlspecialchars($payment['attributes']['description']) . '</td>';
            echo '<td>' . htmlspecialchars(date('Y-m-d H:i:s', strtotime($payment['attributes']['created_at']))) . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
    } else {
        echo 'No payments found.';
    }
}

?>
