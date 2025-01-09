    <h3>Payments List</h3>
    <div class="card">
        <h4>Total Amount</h4>
        <?php
        // Initialize variables
        $search_reference = isset($_GET['reference']) ? trim($_GET['reference']) : '';
        $filtered_payments = [];
        $total_amount = 0;

        // cURL request to fetch payment details
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

            // Filter payments by reference number if a search query exists
            if (isset($data['data']) && is_array($data['data'])) {
                foreach ($data['data'] as $payment) {
                    // If searching, add only matching payments to filtered array
                    if (!empty($search_reference)) {
                        if (strpos($payment['id'], $search_reference) !== false) {
                            $filtered_payments[] = $payment;
                            $total_amount += $payment['attributes']['amount']; // Sum for filtered payments
                        }
                    } else {
                        // Add all payments to filtered array and calculate total
                        $filtered_payments[] = $payment;
                        $total_amount += $payment['attributes']['amount'];
                    }
                }
            }
        }

        // Display total amount
        echo '<p>Total Amount: ' . htmlspecialchars(number_format($total_amount / 100, 2)) . ' PHP</p>';
        ?>
    </div>

    <!-- Search Form -->
    <form method="GET">
        <label for="reference">Search by Reference Number:</label>
        <input type="text" id="reference" name="reference" value="<?php echo htmlspecialchars($search_reference); ?>">
        <button type="submit">Search</button>
    </form>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Amount</th>
                <th>Currency</th>
                <th>Status</th>
                <th>Description</th>
                <th>Created At</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if (!empty($filtered_payments)) {
                foreach ($filtered_payments as $payment) {
                    // Convert amount from cents to dollars
                    $amount_in_dollars = $payment['attributes']['amount'] / 100;
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($payment['id']) . '</td>';
                    echo '<td>' . htmlspecialchars(number_format($amount_in_dollars, 2)) . '</td>';
                    echo '<td>' . htmlspecialchars($payment['attributes']['currency']) . '</td>';
                    echo '<td>' . htmlspecialchars($payment['attributes']['status']) . '</td>';
                    echo '<td>' . htmlspecialchars($payment['attributes']['description']) . '</td>';
                    echo '<td>' . htmlspecialchars(date('Y-m-d H:i:s', strtotime($payment['attributes']['created_at']))) . '</td>';
                    echo '</tr>';
                }
            } else {
                echo '<tr><td colspan="6">No payments found.</td></tr>';
            }
            ?>
        </tbody>
    </table>