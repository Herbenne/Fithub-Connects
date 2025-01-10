<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Register</title>
    <link rel="stylesheet" href="../Frontend/AuthCss/adminRegister.css" />
    <script src="https://kit.fontawesome.com/b098b18a13.js" crossorigin="anonymous"></script>
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
    <div class="card">
        <div class="card-header">
            <div class="welcome-text-container">
                <h2>Admin Register</h2>
								<i class="fa-solid fa-lock"></i>
                <p class="welcome-text">Join the Admin Panel</p>
            </div>
            <a href="../Frontend/html/index1.php"><i class="fa-solid fa-house"></i></a>
        </div>

        <?php if (isset($error_message)): ?>
            <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
        <?php endif; ?>

        <?php if (isset($success_message)): ?>
            <p class="success-message"><?php echo htmlspecialchars($success_message); ?></p>
        <?php endif; ?>

        <form action="admin_register.php" method="post">
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
                <input type="password" id="password" name="password" required oninput="validatePassword()">
                <div id="complexity-message"></div>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required oninput="validatePassword()">
                <div id="password-message"></div>
            </div>

            <button type="submit" class="primary-button">Register</button>
        </form>

        <form action="admin_login_form.php" method="get">
            <button type="submit" class="secondary-button">Login</button>
        </form>
    </div>
</body>
</html>
