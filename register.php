<?php
// Database connection code remains unchanged
$user = 'root';
$pass = ''; // Change this to the actual password if it's not empty
$db = 'gymdb';
$port = 3307;

$db_connection = new mysqli('localhost', $user, $pass, $db, $port);

if ($db_connection->connect_error) {
    die("Connection failed: " . $db_connection->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve form data
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name = $_POST['full_name'];
    $age = $_POST['age'];
    $contact_number = $_POST['contact_number'];

    // Validate form data
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password) || empty($full_name) || empty($age) || empty($contact_number)) {
        echo "All fields are required.";
    } elseif ($password !== $confirm_password) {
        echo "Passwords do not match.";
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $db_connection->prepare("INSERT INTO users (username, email, password, full_name, age, contact_number) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssis", $username, $email, $hashed_password, $full_name, $age, $contact_number);

        if ($stmt->execute()) {
            echo "Registration successful!";
        } else {
            echo "Error: " . $stmt->error;
        }

        $stmt->close();
    }
}

$db_connection->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>User Registration</title>
    <script>
        function validatePassword() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const message = document.getElementById('password-message');

            if (password !== confirmPassword) {
                message.textContent = "Passwords do not match.";
                message.style.color = "red";
            } else {
                message.textContent = "";
            }
        }
    </script>
</head>
<body>
    <h2>Register</h2>
    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <label for="username">Username:</label><br>
        <input type="text" id="username" name="username" required><br><br>
        
        <label for="email">Email:</label><br>
        <input type="email" id="email" name="email" required><br><br>
        
        <label for="password">Password:</label><br>
        <input type="password" id="password" name="password" required oninput="validatePassword()"><br><br>
        
        <label for="confirm_password">Confirm Password:</label><br>
        <input type="password" id="confirm_password" name="confirm_password" required oninput="validatePassword()">
        <div id="password-message"></div><br>
        
        <label for="full_name">Full Name:</label><br>
        <input type="text" id="full_name" name="full_name" required><br><br>
        
        <label for="age">Age:</label><br>
        <input type="number" id="age" name="age" required min="1" max="120"><br><br>
        
        <label for="contact_number">Contact Number:</label><br>
        <input type="text" id="contact_number" name="contact_number" required><br><br>
        
        <input type="submit" value="Register">
    </form>
</body>
</html>
