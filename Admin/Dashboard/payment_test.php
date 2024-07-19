<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_POST['name'])) {
    $name = htmlspecialchars($_POST['name']);
    $amount = 20000; // You can adjust the amount as needed

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
                    'amount' => $amount,
                    'description' => $name,
                    'remarks' => '1 Month Subscription'
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
        $type = $decoded['data']['type'];
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Payment Link</title>
        </head>
        <body>
            <button onclick="window.location.href='<?php echo $checkout_url; ?>'">Pay Now</button>
        </body>
        </html>
        <?php
    }
} else {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Payment Link</title>
    </head>
    <body>
        <form method="post" action="">
            <label for="name">Enter Your Name:</label>
            <input type="text" id="name" name="name" required>
            <button type="submit">Generate Payment Link</button>
        </form>
    </body>
    </html>
    <?php
}
?>
