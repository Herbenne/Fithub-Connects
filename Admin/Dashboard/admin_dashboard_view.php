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
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // Ensure $result is defined and contains fetched user data
            if (isset($result)) {
                while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['username']); ?></td>
                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                        <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['age']); ?></td>
                        <td><?php echo htmlspecialchars($row['contact_number']); ?></td>
                        <td class="actions">
                            <a href="edit_user.php?id=<?php echo $row['id']; ?>">Edit</a>
                            <a href="delete_user.php?id=<?php echo $row['id']; ?>">Delete</a>
                        </td>
                    </tr>
                <?php endwhile;
            } else {
                echo '<tr><td colspan="6">No users found.</td></tr>';
            }
            ?>
        </tbody>
    </table>
    
    <h3>Payments List</h3>
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
        
        // Check if the data key exists
        if (isset($data['data']) && is_array($data['data'])) {
            echo '<table>';
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
    
    <br>
    <form action="../admin_logout.php" method="post">
        <input type="submit" value="Logout">
    </form>
</body>
</html>
