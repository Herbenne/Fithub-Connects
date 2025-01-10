<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Registration</title>
    <link rel="stylesheet" href="../Frontend/AuthCss/regisster.css" />
    <script
        src="https://kit.fontawesome.com/b098b18a13.js"
        crossorigin="anonymous"
    ></script>
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
            } else {
                complexityMessage.textContent = "";
            }

            // Password match check
            if (password !== confirmPassword) {
                message.textContent = "Passwords do not match.";
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
							<h2>Register</h2>
                <i class="fa-solid fa-user"></i>
							<p class="welcome-text">Hi! Join Fithub-connects</p>
						</div>
							<a href="../Frontend/html/index1.php"><i class="fa-solid fa-house"></i></a>
        </div>

        <?php if (isset($error_message)): ?>
            <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
        <?php endif; ?>

        <?php if (isset($success_message)): ?>
            <p class="success-message"><?php echo htmlspecialchars($success_message); ?></p>
        <?php endif; ?>

        <form action="register.php" method="post" enctype="multipart/form-data">
        
            
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
            
            <div class="form-group">
                <label for="full_name">Full Name</label>
                <input type="text" id="full_name" name="full_name" required>
            </div>
            
            <div class="form-group">
                <label for="age">Age</label>
                <input type="number" id="age" name="age" required min="1" max="120">
            </div>
            
            <div class="form-group">
                <label for="contact_number">Contact Number</label>
                <input type="text" id="contact_number" name="contact_number" required>
            </div>
            
            <div class="form-group">
                <label for="profile_picture">Profile Picture</label>
                <input type="file" id="profile_picture" name="profile_picture">
            </div>

            <button type="submit" class="primary-button">Register</button>
        </form>

        <form action="login.php" method="get">
            <button type="submit" class="secondary-button">Login</button>
        </form>
    </div>
</body>
</html>