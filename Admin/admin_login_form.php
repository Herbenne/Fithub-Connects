<!DOCTYPE html>
<html>
<head>
    <title>Admin Login</title>
</head>
<body>
    <h2>Admin Login</h2>

    <?php if (isset($error_message)): ?>
        <p style="color: red;"><?php echo $error_message; ?></p>
    <?php endif; ?>

    <?php if (isset($success_message)): ?>
        <p style="color: green;"><?php echo $success_message; ?></p>
    <?php endif; ?>

    <form action="admin_login.php" method="post">
        <label for="email">Email:</label><br>
        <input type="email" id="email" name="email" required><br><br>
        
        <label for="password">Password:</label><br>
        <input type="password" id="password" name="password" required><br><br>
        
        <input type="submit" value="Login">
    </form>

    <br>
    <form action="admin_register.php" method="get">
        <input type="submit" value="Register">
    </form>
</body>
</html>
