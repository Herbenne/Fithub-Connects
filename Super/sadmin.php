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
    <title>Superadmin Dashboard</title>
</head>

<body>

    <h1>Superadmin Dashboard - Settings</h1>

    <form method="POST">
        <label for="site_title">Site Title</label>
        <input type="text" name="site_title" value="<?php echo htmlspecialchars($settings['site_title'] ?? ''); ?>" /><br>

        <label for="site_logo">Logo Image URL</label>
        <input type="text" name="site_logo" value="<?php echo htmlspecialchars($settings['site_logo'] ?? ''); ?>" /><br>

        <label for="site_tagline">Site Tagline</label>
        <input type="text" name="site_tagline" value="<?php echo htmlspecialchars($settings['site_tagline'] ?? ''); ?>" /><br>

        <label for="contact_email">Contact Email</label>
        <input type="email" name="contact_email" value="<?php echo htmlspecialchars($settings['contact_email'] ?? ''); ?>" /><br>

        <label for="contact_phone">Contact Phone</label>
        <input type="text" name="contact_phone" value="<?php echo htmlspecialchars($settings['contact_phone'] ?? ''); ?>" /><br>

        <label for="facebook_url">Facebook URL</label>
        <input type="text" name="facebook_url" value="<?php echo htmlspecialchars($settings['facebook_url'] ?? ''); ?>" /><br>

        <label for="instagram_url">Instagram URL</label>
        <input type="text" name="instagram_url" value="<?php echo htmlspecialchars($settings['instagram_url'] ?? ''); ?>" /><br>

        <label for="linkedin_url">LinkedIn URL</label>
        <input type="text" name="linkedin_url" value="<?php echo htmlspecialchars($settings['linkedin_url'] ?? ''); ?>" /><br>

        <label for="home_description">Home Description</label>
        <textarea name="home_description"><?php echo htmlspecialchars($settings['home_description'] ?? ''); ?></textarea><br>

        <label for="about_us_description">About Us Description</label>
        <textarea name="about_us_description"><?php echo htmlspecialchars($settings['about_us_description'] ?? ''); ?></textarea><br>

        <label for="location_map_url">Location Map URL</label>
        <input type="text" name="location_map_url" value="<?php echo htmlspecialchars($settings['location_map_url'] ?? ''); ?>" /><br>

        <label for="footer_content">Footer Content</label>
        <textarea name="footer_content"><?php echo htmlspecialchars($settings['footer_content'] ?? ''); ?></textarea><br>

        <button type="submit">Update Settings</button>
    </form>

</body>

</html>
