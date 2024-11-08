<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Registration</title>
    <script>
        function validatePassword() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const message = document.getElementById('password-message');
            const complexityMessage = document.getElementById('complexity-message');

            // Password complexity check
            const complexityRegex = /^(?=.*[A-Z])(?=.*\d).{8,}$/;
            if (!complexityRegex.test(password)) {
                complexityMessage.textContent = "Password must be at least 8 characters long, contain at least one capital letter and one number.";
                complexityMessage.style.color = "red";
            } else {
                complexityMessage.textContent = "";
            }

            // Password match check
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

    <?php if (isset($error_message)): ?>
        <p style="color: red;"><?php echo htmlspecialchars($error_message); ?></p>
    <?php endif; ?>

    <?php if (isset($success_message)): ?>
        <p style="color: green;"><?php echo htmlspecialchars($success_message); ?></p>
    <?php endif; ?>

    <form action="register.php" method="post" enctype="multipart/form-data"> <!-- Added enctype for file upload -->

        <label for="profile_picture">Profile Picture:</label><br>
        <input type="file" id="profile_picture" name="profile_picture"><br><br>
        
        <label for="username">Username:</label><br>
        <input type="text" id="username" name="username" required><br><br>
        
        <label for="email">Email:</label><br>
        <input type="email" id="email" name="email" required><br><br>
        
        <label for="password">Password:</label><br>
        <input type="password" id="password" name="password" required oninput="validatePassword()"><br><br>
        <div id="complexity-message"></div>
        
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

    <br>
    <form action="login.php" method="get">
        <input type="submit" value="Login">
    </form>
</body>
</html>
