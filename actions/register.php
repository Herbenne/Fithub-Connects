// Update the registration processing
<?php
// ...existing code...

$username = trim($_POST['username']);
$email = trim($_POST['email']);
$first_name = trim($_POST['first_name']);
$last_name = trim($_POST['last_name']);
$password = $_POST['password'];
$confirm_password = $_POST['confirm_password'];
$age = $_POST['age'];
$contact_number = trim($_POST['contact_number']);

// Validate inputs
if (empty($username) || empty($email) || empty($first_name) || 
    empty($last_name) || empty($password) || empty($confirm_password)) {
    header("Location: ../pages/register.php?error=empty_fields");
    exit();
}

// ...password validation and other checks...

// Insert new user
$query = "INSERT INTO users (unique_id, username, email, first_name, last_name, 
          password, age, contact_number, reg_date, role) 
          VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'user')";
$stmt = $db_connection->prepare($query);
$stmt->bind_param("ssssssss", $unique_id, $username, $email, $first_name, 
                  $last_name, $hashed_password, $age, $contact_number);

// When displaying user's name, concatenate first and last names
$full_name = $user['first_name'] . ' ' . $user['last_name'];

// ...rest of the code...
