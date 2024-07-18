<!DOCTYPE html>
<html>
<head>
    <title>Admin Registration</title>
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
    <h2>Admin Register</h2>

    <?php if (isset($error_message)): ?>
        <p style="color: red;"><?php echo $error_message; ?></p>
    <?php endif; ?>

    <?php if (isset($success_message)): ?>
        <p style="color: green;"><?php echo $success_message; ?></p>
    <?php endif; ?>

    <form action="admin_register.php" method="post">
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
        
        <input type="submit" value="Register">
    </form>

    <br>
    <form action="admin_login.php" method="get">
        <input type="submit" value="Login">
    </form>
</body>
</html>
