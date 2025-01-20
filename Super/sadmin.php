<?php
// Include the database connection
require 'db_connection.php';

// Initialize an empty settings array
$settings = [];

// Fetch current settings from the database
$settings_query = "SELECT setting_name, setting_value FROM settings";
$settings_result = $db_connection->query($settings_query);

// Store settings in the $settings array
if ($settings_result) {
    while ($row = $settings_result->fetch_assoc()) {
        $settings[$row['setting_name']] = $row['setting_value'];
    }
} else {
    echo "Error fetching settings: " . $db_connection->error;
}

// Update settings if form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Capture and escape all settings inputs
    $new_settings = [
        'site_title' => mysqli_real_escape_string($db_connection, $_POST['site_title']),
        'site_logo' => mysqli_real_escape_string($db_connection, $_POST['site_logo']),
        'site_tagline' => mysqli_real_escape_string($db_connection, $_POST['site_tagline']),
        'contact_email' => mysqli_real_escape_string($db_connection, $_POST['contact_email']),
        'contact_phone' => mysqli_real_escape_string($db_connection, $_POST['contact_phone']),
        'facebook_url' => mysqli_real_escape_string($db_connection, $_POST['facebook_url']),
        'instagram_url' => mysqli_real_escape_string($db_connection, $_POST['instagram_url']),
        'linkedin_url' => mysqli_real_escape_string($db_connection, $_POST['linkedin_url']),
        'home_description' => mysqli_real_escape_string($db_connection, $_POST['home_description']),
        'about_us_description' => mysqli_real_escape_string($db_connection, $_POST['about_us_description']),
        'location_map_url' => mysqli_real_escape_string($db_connection, $_POST['location_map_url']),
        'footer_content' => mysqli_real_escape_string($db_connection, $_POST['footer_content'])
    ];

    // Update each setting in the database
    foreach ($new_settings as $key => $value) {
        $update_query = "UPDATE settings SET setting_value = '$value' WHERE setting_name = '$key'";
        if (!$db_connection->query($update_query)) {
            echo "Error updating $key: " . $db_connection->error;
        }
    }

    // Redirect to refresh the page after updating
    header('Location: superadmin_dashboard.php');
    exit;
}

// Close the database connection
$db_connection->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="./superAdminCss/sadMin.css">
    <script src="https://kit.fontawesome.com/b098b18a13.js" crossorigin="anonymous"></script>
    <title>Superadmin Dashboard</title>
</head>

<body>
    <div class="dashboard-container">
        <header>
            <h1>Admin Control Panel</h1>
        </header>

        <nav>
            <a href="superadmin_dashboard.php"><i class="fa-solid fa-table-columns"></i>Dashboard</a>
            <a href="manage_users.php"><i class="fa-solid fa-user"></i>Manage Users</a>
            <a href="manage_gyms.php"><i class="fa-solid fa-dumbbell"></i>Gyms</a>
            <a href="manage_gym_applications.php"><i class="fa-solid fa-paperclip"></i>Applications</a>
            <a href="paymentlist.php"><i class="fa-solid fa-money-bill"></i>View Payment</a>
            <a href="sadmin.php"><i class="fa-solid fa-gear"></i>Site Settings</a>
            <a href="backup_restore.php"><i class="fa-solid fa-file"></i>Backup & Restore</a>
            <a href="../Admin/admin_login_form.php"><i class="fa-solid fa-right-from-bracket"></i>Logout</a>
        </nav>

        <main>
            <div class="card">
            <h2 class="spanlabel">Settings</h2>
                <form method="POST">
                    <div class="form-group">
                        <label for="site_title">Site Title</label>
                        <input type="text" id="site_title" name="site_title" value="<?php echo htmlspecialchars($settings['site_title'] ?? ''); ?>" />
                    </div>

                    <div class="form-group">
                        <label for="site_logo">Logo Image URL</label>
                        <input type="text" id="site_logo" name="site_logo" value="<?php echo htmlspecialchars($settings['site_logo'] ?? ''); ?>" />
                    </div>

                    <div class="form-group">
                        <label for="site_tagline">Site Tagline</label>
                        <input type="text" id="site_tagline" name="site_tagline" value="<?php echo htmlspecialchars($settings['site_tagline'] ?? ''); ?>" />
                    </div>

                    <div class="form-group">
                        <label for="contact_email">Contact Email</label>
                        <input type="email" id="contact_email" name="contact_email" value="<?php echo htmlspecialchars($settings['contact_email'] ?? ''); ?>" />
                    </div>

                    <div class="form-group">
                        <label for="contact_phone">Contact Phone</label>
                        <input type="text" id="contact_phone" name="contact_phone" value="<?php echo htmlspecialchars($settings['contact_phone'] ?? ''); ?>" />
                    </div>

                    <div class="form-group">
                        <label for="facebook_url">Facebook URL</label>
                        <input type="text" id="facebook_url" name="facebook_url" value="<?php echo htmlspecialchars($settings['facebook_url'] ?? ''); ?>" />
                    </div>

                    <div class="form-group">
                        <label for="instagram_url">Instagram URL</label>
                        <input type="text" id="instagram_url" name="instagram_url" value="<?php echo htmlspecialchars($settings['instagram_url'] ?? ''); ?>" />
                    </div>

                    <div class="form-group">
                        <label for="linkedin_url">LinkedIn URL</label>
                        <input type="text" id="linkedin_url" name="linkedin_url" value="<?php echo htmlspecialchars($settings['linkedin_url'] ?? ''); ?>" />
                    </div>

                    <div class="form-group">
                        <label for="home_description">Home Description</label>
                        <textarea id="home_description" name="home_description"><?php echo htmlspecialchars($settings['home_description'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="about_us_description">About Us Description</label>
                        <textarea id="about_us_description" name="about_us_description"><?php echo htmlspecialchars($settings['about_us_description'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="location_map_url">Location Map URL</label>
                        <input type="text" id="location_map_url" name="location_map_url" value="<?php echo htmlspecialchars($settings['location_map_url'] ?? ''); ?>" />
                    </div>

                    <div class="form-group">
                        <label for="footer_content">Footer Content</label>
                        <textarea id="footer_content" name="footer_content"><?php echo htmlspecialchars($settings['footer_content'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <button type="submit">Update Settings</button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>

</html>