<?php include '../config/database.php'; ?>
<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // Hash password
    $role = 'user'; // Default role
    $unique_id = "USR" . bin2hex(random_bytes(3)); // Generate a unique ID
    
    // Add password confirmation check
    if ($_POST['password'] !== $_POST['confirm_password']) {
        $error = "Passwords do not match!";
    } else if (!isset($_POST['terms'])) {
        $error = "You must agree to the Terms and Conditions";
    } else {
        // Check if the username or email already exists
        $check_query = "SELECT * FROM users WHERE username = ? OR email = ?";
        $stmt = $db_connection->prepare($check_query);
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $error = "Username or Email already exists!";
        } else {
            // Insert new user
            $insert_query = "INSERT INTO users (unique_id, username, email, password, first_name, last_name, role) 
                             VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $db_connection->prepare($insert_query);
            $stmt->bind_param("sssssss", $unique_id, $username, $email, $password, $first_name, $last_name, $role);

            if ($stmt->execute()) {
                echo "<div class='alert alert-success'>Registration successful! <a href='login.php'>Login here</a></div>";
            } else {
                $error = "Error: " . $stmt->error;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register - FitHub</title>
    <link rel="stylesheet" href="../assets/css/mains.css">
    <link rel="stylesheet" href="../assets/css/auth.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-header">
            <h2>Create Account</h2>
            <p>Join FitHub today</p>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="first_name">First Name</label>
                <input type="text" id="first_name" name="first_name" required 
                       pattern="[A-Za-z ]+" title="Please enter a valid first name (letters only)">
            </div>

            <div class="form-group">
                <label for="last_name">Last Name</label>
                <input type="text" id="last_name" name="last_name" required 
                       pattern="[A-Za-z ]+" title="Please enter a valid last name (letters only)">
            </div>

            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-toggle">
                    <input type="password" id="password" name="password" required>
                    <i class="fas fa-eye" id="togglePassword"></i>
                </div>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <div class="password-toggle">
                    <input type="password" id="confirm_password" name="confirm_password" required>
                    <i class="fas fa-eye" id="toggleConfirmPassword"></i>
                </div>
            </div>

            <div class="form-group checkbox-group">
                <input type="checkbox" id="terms" name="terms" required>
                <label for="terms">I agree to the <a href="#" onclick="showTerms(); return false;">Terms and Conditions</a> and Privacy Policy</label>
            </div>

            <button type="submit" class="submit-btn">Register</button>
        </form>

        <div class="auth-links">
            <a href="login.php">Already have an account? Login here</a>
        </div>
    </div>

    <script src="../assets/js/auth.js"></script>
</body>
</html>