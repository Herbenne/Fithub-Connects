<?php
$plan = $_GET['plan'] ?? 'Default Plan';
$duration = $_GET['duration'] ?? 'Default Duration';
$price = ($_GET['price'] ?? 0) * 100; // Convert to cents for the PayMongo API

$curl = curl_init();

curl_setopt_array($curl, [
  CURLOPT_URL => "https://api.paymongo.com/v1/links",
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => "",
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 30,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => "POST",
  CURLOPT_POSTFIELDS => json_encode([
    'data' => [
      'attributes' => [
        'amount' => $price,
        'description' => "$plan - $duration",
        'remarks' => ''
      ]
    ]
  ]),
  CURLOPT_HTTPHEADER => [
    "accept: application/json",
    "authorization: Basic c2tfdGVzdF9CeGRpRVpyeDRXOFRMUVBRSkpYN2hhVHQ6SGVyYmVubmUxMjM0",
    "content-type: application/json"
  ],
]);

$response = curl_exec($curl);
$decoded = json_decode($response, true);
$err = curl_error($curl);

curl_close($curl);

if ($err) {
  echo "cURL Error #:" . $err;
} else {
  $checkout_url = $decoded['data']['attributes']['checkout_url'];

  // Redirect directly to the payment link
  header("Location: $checkout_url");
  exit;
}
