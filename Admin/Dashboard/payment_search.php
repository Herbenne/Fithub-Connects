<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_POST['reference_number'])) {
    $reference_number = htmlspecialchars($_POST['reference_number']);

    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => "https://api.paymongo.com/v1/links/" . $reference_number,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => [
            "accept: application/json",
            "authorization: Basic c2tfdGVzdF9CeGRpRVpyeDRXOFRMUVBRSkpYN2hhVHQ6"
        ],
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
        echo "cURL Error #:" . $err;
    } else {
        $paymentDetails = json_decode($response, true);

        if (isset($paymentDetails['data'])) {
            $data = $paymentDetails['data'];
            $attributes = $data['attributes'];
            $billing = isset($attributes['billing']) ? $attributes['billing'] : null;

            echo "<table border='1'>";
            echo "<tr><th>Field</th><th>Value</th></tr>";
            echo "<tr><td>ID</td><td>" . htmlspecialchars($data['id']) . "</td></tr>";
            echo "<tr><td>Type</td><td>" . htmlspecialchars($data['type']) . "</td></tr>";
            echo "<tr><td>Amount</td><td>" . htmlspecialchars($attributes['amount']) . "</td></tr>";
            echo "<tr><td>Currency</td><td>" . htmlspecialchars($attributes['currency']) . "</td></tr>";
            echo "<tr><td>Status</td><td>" . htmlspecialchars($attributes['status']) . "</td></tr>";
            echo "<tr><td>Description</td><td>" . htmlspecialchars($attributes['description']) . "</td></tr>";
            echo "<tr><td>Created At</td><td>" . date('Y-m-d H:i:s', strtotime($attributes['created_at'])) . "</td></tr>";
            echo "</table>";
        } else {
            echo "No payment details found for the given reference number.";
        }
    }
} else {
?>
    <form method="post" action="">
        <label for="reference_number">Reference Number:</label>
        <input type="text" id="reference_number" name="reference_number" required>
        <button type="submit">Get Payment Details</button>
    </form>
<?php
}
?>
