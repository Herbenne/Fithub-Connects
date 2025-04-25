<?php include '../config/database.php'; ?>
<?php
// Initialize variables to store form data
$first_name = '';
$last_name = '';
$username = '';
$email = '';
$contact_number = '';
$birthdate = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Store form data to preserve it in case of errors
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $contact_number = $_POST['contact_number']; 
    $birthdate = $_POST['birthdate'] ?? '';
    $password = $_POST['password']; // Store original password for validation
    $confirm_password = $_POST['confirm_password'];
    $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT); // Hash password
    $role = 'user'; // Default role
    $unique_id = "USR" . bin2hex(random_bytes(3)); // Generate a unique ID
    
    // Calculate age from birthdate
    if (!empty($birthdate)) {
        $birthdate_obj = new DateTime($birthdate);
        $today = new DateTime();
        $age = $birthdate_obj->diff($today)->y;
    } else {
        $age = 0;
    }
    
    // Validation checks
    $errors = [];
    
    // Check for empty fields
    if (empty($first_name) || empty($last_name) || empty($username) || empty($email) || empty($password) || empty($contact_number) || empty($confirm_password)) {
        $errors[] = "All fields are required";
    }
    
    // Validate name format
    if (!preg_match("/^[A-Za-z ]+$/", $first_name) || !preg_match("/^[A-Za-z ]+$/", $last_name)) {
        $errors[] = "Names must contain only letters and spaces";
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address";
    }

    // Validate age (minimum 13 years)
    if ($age < 13) {
        $errors[] = "You must be at least 13 years old to register.";
    }

    // Validate contact number format
    if (!preg_match("/^[0-9+\-\s()]+$/", $contact_number)) {
        $errors[] = "Please enter a valid contact number";
    }

    // Simplified password validation - only length check
    if (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long";
    }
    
    // Check if passwords match
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    // Check terms agreement
    if (!isset($_POST['terms'])) {
        $errors[] = "You must agree to the Terms and Conditions";
    }
    
    // If there are errors, display them
    if (!empty($errors)) {
        $error = implode("<br>", $errors);
    } else {
        // Check if the username or email already exists
        $check_query = "SELECT * FROM users WHERE username = ? OR email = ?";
        $stmt = $db_connection->prepare($check_query);
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $error = "Username or Email already exists!";
        } else {
            // Insert new user
            $insert_query = "INSERT INTO users (unique_id, username, email, password, first_name, last_name, age, contact_number, role) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $db_connection->prepare($insert_query);
            $stmt->bind_param("sssssssss", $unique_id, $username, $email, $hashed_password, $first_name, $last_name, $age, $contact_number, $role);

            if ($stmt->execute()) {
                // Set a success message in session
                session_start();
                $_SESSION['registration_success'] = "Account created successfully! Please login.";
                // Redirect to login page
                header("Location: login.php");
                exit();
            } else {
                $error = "Error: " . $stmt->error;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register - FitHub</title>
    <link rel="stylesheet" href="../assets/css/mains.css">
    <link rel="stylesheet" href="../assets/css/auth.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-header">
            <h2>Create Account</h2>
            <p>Join FitHub today</p>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="first_name">First Name</label>
                <input type="text" id="first_name" name="first_name" required 
                       pattern="[A-Za-z ]+" title="Please enter a valid first name (letters only)"
                       value="<?php echo htmlspecialchars($first_name); ?>">
            </div>

            <div class="form-group">
                <label for="last_name">Last Name</label>
                <input type="text" id="last_name" name="last_name" required 
                       pattern="[A-Za-z ]+" title="Please enter a valid last name (letters only)"
                       value="<?php echo htmlspecialchars($last_name); ?>">
            </div>

            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required
                       value="<?php echo htmlspecialchars($username); ?>">
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required
                       value="<?php echo htmlspecialchars($email); ?>">
            </div>

            <div class="form-group">
                <label for="birthdate">Date of Birth</label>
                <input type="date" id="birthdate" name="birthdate" required max="<?php echo date('Y-m-d', strtotime('-13 years')); ?>"
                       value="<?php echo htmlspecialchars($birthdate); ?>">
                <small class="form-text">You must be at least 13 years old to register</small>
            </div>

            <div class="form-group">
                <label for="contact_number">Contact Number</label>
                <input type="tel" id="contact_number" name="contact_number" required
                    pattern="[0-9+\-\s()]+" title="Please enter a valid phone number"
                    value="<?php echo htmlspecialchars($contact_number); ?>">
                <small class="form-text">Format: +63 XXX XXX XXXX or 09XXXXXXXXX</small>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-toggle">
                    <input type="password" id="password" name="password" required>
                    <i class="fas fa-eye" id="togglePassword"></i>
                </div>
                <small class="form-text">Password must be at least 6 characters (numbers are allowed)</small>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <div class="password-toggle">
                    <input type="password" id="confirm_password" name="confirm_password" required>
                    <i class="fas fa-eye" id="toggleConfirmPassword"></i>
                </div>
            </div>

            <div class="form-group checkbox-group">
                <input type="checkbox" id="terms" name="terms" required
                       <?php echo (isset($_POST['terms'])) ? 'checked' : ''; ?>>
                <label for="terms">I agree to the <a href="#" onclick="showTerms(); return false;">Terms and Conditions</a> and Privacy Policy</label>
            </div>

            <button type="submit" class="submit-btn">Register</button>
        </form>

        <div class="auth-links">
            <a href="login.php">Already have an account? Login here</a>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const birthdateInput = document.getElementById('birthdate');
    const contactNumberInput = document.getElementById('contact_number');
    
    // Toggle password visibility
    const togglePassword = document.getElementById('togglePassword');
    const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
    
    togglePassword.addEventListener('click', function() {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        this.classList.toggle('fa-eye-slash');
    });
    
    toggleConfirmPassword.addEventListener('click', function() {
        const type = confirmPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        confirmPasswordInput.setAttribute('type', type);
        this.classList.toggle('fa-eye-slash');
    });
    
    // Set max date for birthdate (13 years ago)
    const today = new Date();
    const thirteenYearsAgo = new Date(today.getFullYear() - 13, today.getMonth(), today.getDate());
    const maxDate = thirteenYearsAgo.toISOString().split('T')[0];
    birthdateInput.setAttribute('max', maxDate);
    
    // Contact number validation
    contactNumberInput.addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9+\-\s()]/g, '');
    });
    
    // Form submission validation
    form.addEventListener('submit', function(e) {
        const password = passwordInput.value;
        const confirmPassword = confirmPasswordInput.value;
        
        // Simplified password validation - only length check, no character requirements
        if(password.length < 6) {
            e.preventDefault();
            alert('Password must be at least 6 characters long');
            passwordInput.focus();
            return;
        }
        
        if(password !== confirmPassword) {
            e.preventDefault();
            alert('Passwords do not match!');
            confirmPasswordInput.focus();
            return;
        }
        
        // Validate birthdate
        if(birthdateInput.value === '') {
            e.preventDefault();
            alert('Date of birth is required');
            birthdateInput.focus();
            return;
        }
        
        // Calculate age
        const birthdateObj = new Date(birthdateInput.value);
        const ageDifMs = Date.now() - birthdateObj.getTime();
        const ageDate = new Date(ageDifMs);
        const age = Math.abs(ageDate.getUTCFullYear() - 1970);
        
        if(age < 13) {
            e.preventDefault();
            alert('You must be at least 13 years old to register');
            birthdateInput.focus();
            return;
        }
        
        // Validate contact number
        if(contactNumberInput.value.trim() === '') {
            e.preventDefault();
            alert('Contact number is required');
            contactNumberInput.focus();
            return;
        }
        
        if(!contactNumberInput.value.match(/^[0-9+\-\s()]+$/)) {
            e.preventDefault();
            alert('Please enter a valid contact number');
            contactNumberInput.focus();
            return;
        }
    });
});

function showTerms() {
    alert("Terms and Conditions for FITHUB CONNECTS\n\nBy registering, you agree to our terms and privacy policy.");
}
</script>
    <script src="../assets/js/auth.js"></script>
</body>
</html>