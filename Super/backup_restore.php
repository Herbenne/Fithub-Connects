<?php
// Database connection parameters
$db_host = 'localhost';
$db_user = 'root';
$db_pass = ''; // Update if you have a password
$db_name = 'gymdb';
$db_port = 3307; // Update the port if necessary

// Set the path to mysqldump and mysql executable
$mysql_path = 'C:\\xampp\\mysql\\bin\\mysql.exe'; // Correct path for mysql executable

// Handle file upload and restoration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['backup_file'])) {
        $error_code = $_FILES['backup_file']['error'];

        // Handle specific error codes
        switch ($error_code) {
            case UPLOAD_ERR_OK:
                $uploaded_file = $_FILES['backup_file']['tmp_name'];
                $file_info = pathinfo($_FILES['backup_file']['name']);
                if ($file_info['extension'] !== 'sql') {
                    $message = "Invalid file type. Please upload a .sql file.";
                } else {
                    // Execute the restoration command
                    $command = "$mysql_path --host=$db_host --user=$db_user --password=$db_pass --port=$db_port $db_name < $uploaded_file";
                    exec($command, $output, $result);

                    // Check if the restore was successful
                    if ($result === 0) {
                        $message = "Database restored successfully!";
                    } else {
                        // Capture detailed error information
                        $error_output = implode("\n", $output);
                        $message = "Error during restore process: $error_output";
                    }
                }
                break;

            case UPLOAD_ERR_INI_SIZE:
                $message = "The uploaded file exceeds the upload_max_filesize directive in php.ini.";
                break;

            case UPLOAD_ERR_FORM_SIZE:
                $message = "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.";
                break;

            case UPLOAD_ERR_PARTIAL:
                $message = "The uploaded file was only partially uploaded.";
                break;

            case UPLOAD_ERR_NO_FILE:
                $message = "No file was uploaded.";
                break;

            case UPLOAD_ERR_NO_TMP_DIR:
                $message = "Missing a temporary folder.";
                break;

            case UPLOAD_ERR_CANT_WRITE:
                $message = "Failed to write file to disk.";
                break;

            case UPLOAD_ERR_EXTENSION:
                $message = "A PHP extension stopped the file upload.";
                break;

            default:
                $message = "Unknown error during file upload.";
                break;
        }
    } else {
        $message = "No file was uploaded.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup & Restore</title>
    <link rel="stylesheet" href="styles.css">
</head>

<body>
    <header>
        <h1>Backup & Restore</h1>
    </header>

    <nav>
        <a href="superadmin_dashboard.php">Superadmin Dashboard</a>
    </nav>

    <div class="container">
        <h2>Restore Database</h2>
        <form method="POST" enctype="multipart/form-data">
            <label for="backup_file">Select a backup file (.sql):</label>
            <input type="file" name="backup_file" id="backup_file" required>
            <button type="submit">Restore Backup</button>
        </form>

        <?php
        // Display message after the backup attempt
        if (!empty($message)) {
            echo "<p>$message</p>";
        }
        ?>

        <h2>Create Backup</h2>
        <form method="POST">
            <button type="submit" name="backup_action" value="create">Create Backup</button>
        </form>

        <?php
        // Handle backup creation if the form is submitted
        if (isset($_POST['backup_action']) && $_POST['backup_action'] === 'create') {
            $backup_filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
            $backup_command = "$mysql_path --host=$db_host --user=$db_user --password=$db_pass --port=$db_port $db_name > $backup_filename";

            exec($backup_command, $output, $result);

            if ($result === 0) {
                echo "<p>Backup created successfully. Download the file <a href='$backup_filename' download>here</a>.</p>";
            } else {
                echo "<p>Error during backup process.</p>";
            }
        }
        ?>
    </div>
</body>

</html>