<!DOCTYPE html>
<html>
<head>
    <title>Edit User</title>
</head>
<body>
    <h2>Edit User</h2>

    <?php if (isset($error_message)): ?>
        <p style="color: red;"><?php echo $error_message; ?></p>
    <?php endif; ?>

    <form action="edit_user.php" method="post">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($id); ?>">
        
        <label for="username">Username:</label><br>
        <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required><br><br>
        
        <label for="email">Email:</label><br>
        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required><br><br>
        
        <label for="full_name">Full Name:</label><br>
        <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($full_name); ?>" required><br><br>
        
        <label for="age">Age:</label><br>
        <input type="number" id="age" name="age" value="<?php echo htmlspecialchars($age); ?>" required min="1" max="120"><br><br>
        
        <label for="contact_number">Contact Number:</label><br>
        <input type="text" id="contact_number" name="contact_number" value="<?php echo htmlspecialchars($contact_number); ?>" required><br><br>
        
        <input type="submit" value="Update">
    </form>
</body>
</html>
