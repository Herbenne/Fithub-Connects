<!DOCTYPE html>
<html>

<head>
    <title>Admin Login</title>
</head>

<body>
    <h2>Admin Login</h2>

    <?php
    session_start();

    // Display error message if login fails
    if (isset($_SESSION['error_message'])) {
        echo "<p style='color: red;'>" . $_SESSION['error_message'] . "</p>";
        // Clear the error message after displaying it
        unset($_SESSION['error_message']);
    }
    ?>

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