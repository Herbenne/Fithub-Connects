<?php
// Include the database connection
require 'db_connection.php';

// Fetch current settings
$settings_query = "SELECT setting_name, setting_value FROM settings";
$settings_result = $db_connection->query($settings_query);
$settings = [];
while ($row = $settings_result->fetch_assoc()) {
    $settings[$row['setting_name']] = $row['setting_value'];
}

// Query for membership plans
$offers_query = "SELECT * FROM membership_plans";
$offers_result = $db_connection->query($offers_query);

$gyms_query = "SELECT * FROM gyms"; // Adjust table name if necessary
$gyms_result = $db_connection->query($gyms_query);

// Check if the user is logged in
session_start();
$is_logged_in = isset($_SESSION['user_id']); // Check if user_id session exists
$user_name = $is_logged_in && isset($_SESSION['username']) ? $_SESSION['username'] : 'Guest'; // Fetch logged-in username

// Close the database connection after all queries are done
$db_connection->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="../css/nav.css" />
    <link rel="stylesheet" href="../css/Mainpage.css" />
    <link rel="stylesheet" href="../css/abouts.css" />
    <link rel="stylesheet" href="../css/gymsPartner.css" />
    <link rel="stylesheet" href="../css/contact.css" />
    <script src="https://kit.fontawesome.com/b098b18a13.js" crossorigin="anonymous"></script>
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet" />
    <link href="https://unpkg.com/maplibre-gl@2.4.0/dist/maplibre-gl.css" rel="stylesheet" />
    <script src="https://unpkg.com/maplibre-gl@2.4.0/dist/maplibre-gl.js"></script>
    <title><?php echo htmlspecialchars($settings['site_title']); ?></title>
</head>

<body>
    <nav>
        <div class="logo" id="logo">
            <h1>
                <a href="#">
                    <!-- Fix the logo image path here -->
                    <img class="logo-image" src="<?php echo htmlspecialchars($settings['site_logo']); ?>" alt="FITHUB" />
                </a>
            </h1>
        </div>
        <div class="openMenu"><i class="fa-solid fa-bars"></i></div>
        <ul class="mainMenu">
            <li><a href="#">HOME</a></li>
            <li><a href="#about-us">ABOUT</a></li>
            <li><a href="#offers">OFFERS</a></li>
            <li><a href="#come-together">CONTACT</a></li>
            <li><a href="../forum/index.php">Forum</a></li>
            <div class="closeMenu"><i class="fa fa-times"></i></div>
            <span class="icons">
                <a href="<?php echo htmlspecialchars($settings['facebook_url']); ?>"><i class="fa-brands fa-facebook"></i></a>
                <a href="<?php echo htmlspecialchars($settings['instagram_url']); ?>"><i class="fa-brands fa-instagram"></i></a>
                <a href="<?php echo htmlspecialchars($settings['github_url']); ?>"><i class="fa-brands fa-github"></i></a>
                <a href="mailto:<?php echo htmlspecialchars($settings['contact_email']); ?>"><i class="fa-regular fa-envelope"></i></a>
            </span>
        </ul>
        <div class="auth-buttons">
            <?php if ($is_logged_in): ?>
                <a href="user_profile.php"><button><span class="user"><?php echo htmlspecialchars($user_name); ?>!</span></button></a>
                <a href="../../Login&Registration/login_form.php"><button class="logout-button">Logout</button></a>
            <?php else: ?>
                <button class="login-button">LOGIN</button>
                <button class="signup-button">SIGNUP</button>
            <?php endif; ?>
        </div>
    </nav>

    <main>
        <div class="home-parent">
            <div class="home-tagline"><?php echo htmlspecialchars($settings['site_tagline']); ?></div>
            <div class="home-description">
                <p>
                    <?php echo htmlspecialchars($settings['home_description']); ?>
                </p>
            </div>
            <div class="home-buttons">
                <a href="#about-us"><button class="learn-more-button">Learn more</button></a>
                <a href="../../Login&Registration/register_form.php"><button class="join-now-button">Join now</button></a>
            </div>
            <div class="home-gymGuy">
                <img src="../img/young-fitness-man-studio.png" alt="Gym Guy" />
            </div>
        </div>
    </main>

    <section class="about-container">
        <div class="about">
            <div id="about-us" class="about-us">
                <h1>About Us</h1>
                <div class="about-descriptions">
                    <p>
                        <?php echo htmlspecialchars($settings['about_us_description']); ?>
                    </p>
                </div>
            </div>
            <div class="map-container">
                <iframe class="map" src="<?php echo htmlspecialchars($settings['location_map_url']); ?>" width="600" height="450" style="border: 0" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
            </div>
        </div>
    </section>

    <section id="gyms" class="gyms-container">
        <h1 class="gyms-title">Our Partner Gyms</h1>
        <div class="gyms">
            <?php while ($gym = $gyms_result->fetch_assoc()): ?>
                <div class="gym">
                    <h3><?php echo htmlspecialchars($gym['gym_name']); ?></h3>
                    <a href="gym.php?gym_id=<?php echo $gym['gym_id']; ?>">View Details</a>
                </div>
            <?php endwhile; ?>
        </div>
    </section>

    <section class="come-together" id="come-together">
        <div class="container">
            <div class="content-wrapper">
                <div class="text-content">
                    <h2 class="title">
                        Join FitHub - Your Fitness Journey Starts Here!
                    </h2>
                    <h3 class="subtitle">Every Rep Counts</h3>
                    <p class="description">
                        Transform your body, boost your energy, and build confidence. At FitHub, we provide the best equipment, expert trainers, and a supportive community to help you reach your fitness goals. No excuses just results. Start your journey today!
                    </p>
                    <button class="talk-button">Let's talk</button>
                    <div class="social-links">
                        <a href="<?php echo htmlspecialchars($settings['facebook_url']); ?>" target="_blank"><i class="fa-brands fa-facebook"></i></a>
                        <a href="#"><i class="fa-brands fa-linkedin"></i></a>
                        <a href="#"><i class="fa-brands fa-instagram"></i></a>
                        <a href="#"><i class="fa-regular fa-envelope"></i></a>
                        <a href="#"><i class="fa-brands fa-x-twitter"></i></a>
                    </div>
                </div>
                <div class="illustration">
                    <img src="../img/undraw_work-time_zbsw.svg" alt="People collaborating" />
                </div>
            </div>
        </div>
    </section>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init();
    </script>
    <script>
    document.addEventListener("DOMContentLoaded", function () {
    document.querySelector(".talk-button").addEventListener("click", function () {
        window.location.href = "../forum/index.php";
    });
});
</script>
    <script src="../js/nav.js"></script>
</body>

</html>