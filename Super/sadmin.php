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
    // Capture all settings
    $new_site_title = $_POST['site_title'];
    $new_site_logo = $_POST['site_logo'];
    $new_site_tagline = $_POST['site_tagline'];
    $new_contact_email = $_POST['contact_email'];
    $new_contact_phone = $_POST['contact_phone'];
    $new_facebook_url = $_POST['facebook_url'];
    $new_instagram_url = $_POST['instagram_url'];
    $new_linkedin_url = $_POST['linkedin_url'];
    $new_home_description = $_POST['home_description'];
    $new_about_us_description = $_POST['about_us_description'];
    $new_location_map_url = $_POST['location_map_url'];
    $new_footer_content = $_POST['footer_content'];


    // Prepare the update query with all the settings
    $update_query = "UPDATE settings SET setting_value = CASE
        WHEN setting_name = 'site_title' THEN '$new_site_title'
        WHEN setting_name = 'site_logo' THEN '$new_site_logo'
        WHEN setting_name = 'site_tagline' THEN '$new_site_tagline'
        WHEN setting_name = 'contact_email' THEN '$new_contact_email'
        WHEN setting_name = 'contact_phone' THEN '$new_contact_phone'
        WHEN setting_name = 'facebook_url' THEN '$new_facebook_url'
        WHEN setting_name = 'instagram_url' THEN '$new_instagram_url'
        WHEN setting_name = 'linkedin_url' THEN '$new_linkedin_url'
        WHEN setting_name = 'home_description' THEN '$new_home_description'
        WHEN setting_name = 'about_us_description' THEN '$new_about_us_description'
        WHEN setting_name = 'location_map_url' THEN '$new_location_map_url'
        WHEN setting_name = 'footer_content' THEN '$new_footer_content'
    END WHERE setting_name IN (
        'site_title', 'site_logo', 'site_theme', 'site_tagline', 'contact_email', 'contact_phone',
        'facebook_url', 'instagram_url', 'linkedin_url', 'google_analytics_id', 'home_description',
        'about_us_description', 'location_map_url', 'footer_content', 'banner_image')";

    if ($db_connection->query($update_query) === TRUE) {
        // Redirect to refresh the page after updating
        header('Location: superadmin_dashboard.php');
    } else {
        echo "Error updating settings: " . $db_connection->error;
    }
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
        <input type="text" name="site_title" value="<?php echo htmlspecialchars($settings['site_title']); ?>" /><br>

        <label for="site_logo">Logo Image URL</label>
        <input type="text" name="site_logo" value="<?php echo htmlspecialchars($settings['site_logo']); ?>" /><br>

        <label for="site_tagline">Site Tagline</label>
        <input type="text" name="site_tagline" value="<?php echo htmlspecialchars($settings['site_tagline']); ?>" /><br>

        <label for="contact_email">Contact Email</label>
        <input type="email" name="contact_email" value="<?php echo htmlspecialchars($settings['contact_email']); ?>" /><br>

        <label for="contact_phone">Contact Phone</label>
        <input type="text" name="contact_phone" value="<?php echo htmlspecialchars($settings['contact_phone']); ?>" /><br>

        <label for="facebook_url">Facebook URL</label>
        <input type="text" name="facebook_url" value="<?php echo htmlspecialchars($settings['facebook_url']); ?>" /><br>

        <label for="instagram_url">Instagram URL</label>
        <input type="text" name="instagram_url" value="<?php echo htmlspecialchars($settings['instagram_url']); ?>" /><br>

        <label for="linkedin_url">LinkedIn URL</label>
        <input type="text" name="linkedin_url" value="<?php echo htmlspecialchars($settings['linkedin_url']); ?>" /><br>

        <label for="home_description">Home Description</label>
        <textarea name="home_description"><?php echo htmlspecialchars($settings['home_description']); ?></textarea><br>

        <label for="about_us_description">About Us Description</label>
        <textarea name="about_us_description"><?php echo htmlspecialchars($settings['about_us_description']); ?></textarea><br>

        <label for="location_map_url">Location Map URL</label>
        <input type="text" name="location_map_url" value="<?php echo htmlspecialchars($settings['location_map_url']); ?>" /><br>

        <label for="footer_content">Footer Content</label>
        <textarea name="footer_content"><?php echo htmlspecialchars($settings['footer_content']); ?></textarea><br>

        <button type="submit">Update Settings</button>
    </form>

</body>

</html>