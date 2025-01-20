<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../Frontend/AuthCss/adminLogin.css" />
    <script src="https://kit.fontawesome.com/b098b18a13.js" crossorigin="anonymous"></script>
    <title>Admin Login</title>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="login-container">
                <h1>Admin Login</h1>
                <i class="fa-solid fa-lock"></i>
                <p class="welcome-text">Welcome to Admin Panel</p>
            </div>
            <a href="../Frontend/html/index1.php"><i class="fa-solid fa-house"></i></a>
        </div>

        <?php
        session_start();

        // Display error message if login fails
        if (isset($_SESSION['error_message'])) {
            echo "<div class='message error'>" . $_SESSION['error_message'] . "</div>";
            // Clear the error message after displaying it
            unset($_SESSION['error_message']);
        }
        ?>

        <form action="admin_login.php" method="post">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" placeholder="Enter admin email" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Enter password" required>
            </div>
            
            <button type="submit" class="button login-btn">Login</button>
        </form>

        <form action="admin_register.php" method="get">
            <button type="submit" class="button register-btn">Register</button>
        </form>
    </div>
</body>
</html>
