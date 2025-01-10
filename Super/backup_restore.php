<?php
// Database connection parameters
$db_host = 'localhost';
$db_user = 'root';
$db_pass = ''; // Update if you have a password
$db_name = 'gymdb';
$db_port = 3307; // Update the port if necessary

// Set the path to mysqldump and mysql executable
$mysqldump_path = 'C:\\xampp\\mysql\\bin\\mysqldump.exe'; // Correct path for mysqldump
$mysql_path = 'C:\\xampp\\mysql\\bin\\mysql.exe';         // Correct path for mysql

// Handle file upload and restoration
$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['backup_file'])) {
        $error_code = $_FILES['backup_file']['error'];

        switch ($error_code) {
            case UPLOAD_ERR_OK:
                $uploaded_file = $_FILES['backup_file']['tmp_name'];
                $file_info = pathinfo($_FILES['backup_file']['name']);
                if ($file_info['extension'] !== 'sql') {
                    $message = "Invalid file type. Please upload a .sql file.";
                } else {
                    // Execute the restoration command
                    $command = "\"$mysql_path\" --host=$db_host --user=$db_user --password=$db_pass --port=$db_port $db_name < \"$uploaded_file\"";
                    exec($command . " 2>&1", $output, $result);

                    if ($result === 0) {
                        $message = "Database restored successfully!";
                    } else {
                        $error_output = implode("\n", $output);
                        $message = "Error during restore process: $error_output";
                    }
                }
                break;

            case UPLOAD_ERR_NO_FILE:
                $message = "No file was uploaded.";
                break;

            default:
                $message = "Error during file upload.";
                break;
        }
    } elseif (isset($_POST['backup_action']) && $_POST['backup_action'] === 'create') {
        // Handle backup creation
        $backup_filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $backup_command = "\"$mysqldump_path\" --host=$db_host --user=$db_user --password=$db_pass --port=$db_port $db_name > \"$backup_filename\"";

        exec($backup_command . " 2>&1", $output, $result);

        if ($result === 0) {
            $message = "Backup created successfully. <a href='$backup_filename' download>Download here</a>.";
        } else {
            $error_output = implode("\n", $output);
            $message = "Error during backup process: $error_output";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup & Restore</title>
</head>

<body>
    <header>
        <h1>Backup & Restore</h1>
    </header>

    <nav>
        <a href="superadmin_dashboard.php">Superadmin Dashboard</a>
    </nav>

    <div class="container">
        <!-- Display messages -->
        <?php if (!empty($message)) : ?>
            <p><?= $message ?></p>
        <?php endif; ?>

        <!-- Restore Section -->
        <h2>Restore Database</h2>
        <form method="POST" enctype="multipart/form-data">
            <label for="backup_file">Select a backup file (.sql):</label>
            <input type="file" name="backup_file" id="backup_file" required>
            <button type="submit">Restore Backup</button>
        </form>

        <!-- Backup Section -->
        <h2>Create Backup</h2>
        <form method="POST">
            <button type="submit" name="backup_action" value="create">Create Backup</button>
        </form>
    </div>
</body>

</html>