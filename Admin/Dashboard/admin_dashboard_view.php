<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table, th, td {
            border: 1px solid black;
            padding: 8px;
        }
        th {
            background-color: #f2f2f2;
        }
        td {
            text-align: left;
        }
        .actions {
            white-space: nowrap;
        }
        .actions a {
            margin-right: 5px;
        }
        .card {
            border: 1px solid #ddd;
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
    <h2>Admin Dashboard</h2>
    <p>Welcome, Admin!</p>
    
    <h3>User Management</h3>
    <table>
        <thead>
            <tr>
                <th>Username</th>
                <th>Email</th>
                <th>Full Name</th>
                <th>Age</th>
                <th>Contact Number</th>
                <th>Membership Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if (isset($result)) {
                while ($row = $result->fetch_assoc()) {
                    // Determine membership status
                    $status = $row['membership_status'];
                    if ($status === 'active') {
                        $end_date = new DateTime($row['membership_end_date']);
                        if ($end_date < new DateTime()) {
                            $status = 'inactive';
                            $row['membership_status'] = 'inactive';
                            $stmt = $db_connection->prepare("UPDATE users SET membership_status = ? WHERE id = ?");
                            $stmt->bind_param("si", $status, $row['id']);
                            $stmt->execute();
                            $stmt->close();
                        }
                    }
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['username']); ?></td>
                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                        <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['age']); ?></td>
                        <td><?php echo htmlspecialchars($row['contact_number']); ?></td>
                        <td><?php echo htmlspecialchars(ucfirst($status)); ?></td>
                        <td class="actions">
                            <a href="edit_user.php?id=<?php echo $row['id']; ?>">Edit</a>
                            <a href="delete_user.php?id=<?php echo $row['id']; ?>">Delete</a>
                        </td>
                    </tr>
                <?php }
            } else {
                echo '<tr><td colspan="7">No users found.</td></tr>';
            }
            ?>
        </tbody>
    </table>

    <h3>Add User with Membership</h3>
    <form action="admin_dashboard.php" method="post">
        <input type="hidden" name="add_user" value="1">
        
        <label for="username">Username:</label>
        <input type="text" id="username" name="username" required><br><br>
        
        <label for="membership_plan_id">Membership Plan:</label>
        <select id="membership_plan_id" name="membership_plan_id" required>
            <?php
            if (isset($plans_result)) {
                while ($plan = $plans_result->fetch_assoc()) {
                    echo '<option value="' . htmlspecialchars($plan['id']) . '">' . htmlspecialchars($plan['plan_name']) . '</option>';
                }
            }
            ?>
        </select><br><br>
        
        <input type="submit" value="Add User">
    </form>

    <h3>Membership Management</h3>
    <form action="add_membership_plan.php" method="post">
        <label for="plan_name">Plan Name:</label>
        <input type="text" id="plan_name" name="plan_name" required><br><br>
        
        <label for="duration">Duration (days):</label>
        <input type="number" id="duration" name="duration" required><br><br>
        
        <label for="price">Price:</label>
        <input type="number" id="price" name="price" step="0.01" required><br><br>
        
        <input type="submit" value="Add Plan">
    </form>
    
    <h3>Existing Membership Plans</h3>
    <table>
        <thead>
            <tr>
                <th>Plan Name</th>
                <th>Duration (days)</th>
                <th>Price</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if (isset($plans_result)) {
                $plans_result->data_seek(0); // Reset the result pointer
                while ($plan = $plans_result->fetch_assoc()) { ?>
                    <tr>
                        <td><?php echo htmlspecialchars($plan['plan_name']); ?></td>
                        <td><?php echo htmlspecialchars($plan['duration']); ?></td>
                        <td><?php echo htmlspecialchars($plan['price']); ?></td>
                        <td class="actions">
                            <a href="edit_membership_plan.php?id=<?php echo $plan['id']; ?>">Edit</a>
                            <a href="delete_membership_plan.php?id=<?php echo $plan['id']; ?>" onclick="return confirm('Are you sure you want to delete this plan?');">Delete</a>
                        </td>
                    </tr>
                <?php }
            } else {
                echo '<tr><td colspan="4">No membership plans found.</td></tr>';
            }
            ?>
        </tbody>
    </table>
    
    <h3>Payments List</h3>
    <div class="card">
        <h4>Total Amount</h4>
        <?php
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
            
            // Calculate total amount
            $total_amount = 0;
            if (isset($data['data']) && is_array($data['data'])) {
                foreach ($data['data'] as $payment) {
                    $total_amount += $payment['attributes']['amount'];
                }
                echo '<p>Total Amount: ' . htmlspecialchars(number_format($total_amount / 100, 2)) . ' USD</p>';
            } else {
                echo 'No payments found.';
            }
        }
        ?>
    </div>
    
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
            if (isset($data['data']) && is_array($data['data'])) {
                foreach ($data['data'] as $payment) {
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
    
    <br>
    <form action="../admin_logout.php" method="post">
        <input type="submit" value="Logout">
    </form>
    </div>
</body>
</html>
