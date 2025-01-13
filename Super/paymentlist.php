<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments List</title>
    <script src="https://kit.fontawesome.com/b098b18a13.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="./superAdminCss/paymentList.css">
</head>

<body>
    <div class="dashboard-container">
        <header>
            <h1>Manage Gym Applications</h1>
        </header>
        <nav>
            <a href="superadmin_dashboard.php"><i class="fa-solid fa-table-columns"></i>Dashboard</a>
            <a href="manage_admins.php"><i class="fa-solid fa-lock"></i>Manage Admins</a>
            <a href="manage_users.php"><i class="fa-solid fa-user"></i>Manage Users</a>
            <a href="manage_gym_applications.php"><i class="fa-solid fa-paperclip"></i>Applications</a>
            <a href="sadmin.php"><i class="fa-solid fa-gear"></i>Site Settings</a>
            <a href="manage_gyms.php"><i class="fa-solid fa-dumbbell"></i>Gyms</a>
            <a href="backup_restore.php"><i class="fa-solid fa-file"></i>Backup & Restore</a>
            <a href="../Admin/admin_login_form.php"><i class="fa-solid fa-right-from-bracket"></i>Logout</a>
        </nav>

        <main>
            <h1 class="spanlabel">Payments List</h1>
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
                <label for="reference">Search by Reference Number:</label><br>
                <input type="text" id="reference" name="reference" value="<?php echo htmlspecialchars($search_reference); ?>"><br>
                <button type="submit">Search</button>
            </form>

            <div class="card">
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
            </div>
        </main>
    </div>
</body>

</html>