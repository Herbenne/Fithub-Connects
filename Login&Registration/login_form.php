<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../Frontend/AuthCss/login.css" />
		<script
        src="https://kit.fontawesome.com/b098b18a13.js"
        crossorigin="anonymous"
    ></script>
    <title>Login</title>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="login-container">
								<h1>Login</h1>
                <i class="fa-solid fa-user"></i>
								<p class="welcome-text">Welcome to Fithub-connects</p>
            </div>
						<a href="../Frontend/html/index1.php"><i class="fa-solid fa-house"></i></a>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="message error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <?php if (isset($success_message)): ?>
            <div class="message success"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <form action="login.php" method="post">
            <div class="form-group">
                <label for="email">Email</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    placeholder="E.g. johndoe@email.com"
                    required
                >
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    placeholder="Enter your password"
                    required
                >
            </div>
            
            <button type="submit" class="button login-btn">Login</button>
        </form>

        <form action="register_form.php" method="get">
            <button type="submit" class="button register-btn">Register</button>
        </form>
    </div>
</body>
</html>