-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3307
-- Generation Time: Jan 09, 2025 at 01:05 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `gymdb`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `role` enum('superadmin','admin') NOT NULL DEFAULT 'admin',
  `gym_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `email`, `password`, `created_at`, `role`, `gym_id`) VALUES
(1, 'SuperAdmin', 'superadmin@example.com', '$2y$10$Ud0dYJnc3JN5X1rg1xt4seXC/HkSK1iaScsxDTEJnGOLHl.VhBqc6', '2025-01-09 04:05:35', 'superadmin', 0),
(5, 'Admin', 'admin1@gmail.com', '$2y$10$kdI4hHRQPoQCW7L5sbnnZeYmcA2CYmbkYXDVSXWdn0QxUcweEm3U2', '2024-11-05 11:07:28', 'admin', 1),
(6, 'Admin2', 'admin2@gmail.com', '$2y$10$TQ2kZmgB88IF5nRNgFPxDu.RFRqvLUfXDIdcbjNnnpjlhhDwyvXFu', '2024-11-08 08:56:46', 'admin', 2),
(8, 'Admin3', 'admin3@gmail.com', '$2y$10$uOH3VzOdYYrDwA7kx6eqsOdvwop/8f7XfxkjveuUc9rXlRNO3GQJe', '2025-01-08 18:26:42', 'admin', 0),
(9, 'New Admin', 'newadmin@gmail.com', '$2y$10$blrzaoHjf3NPpeJdIrGoMeX3mXJXR2/RmLKE3DWlpq.IBHPHBmlfq', '2025-01-09 12:03:41', 'admin', 0);

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `user_id` int(6) UNSIGNED NOT NULL,
  `check_in` datetime NOT NULL,
  `check_out` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `gyms`
--

CREATE TABLE `gyms` (
  `gym_id` int(11) NOT NULL,
  `gym_name` varchar(255) NOT NULL,
  `gym_location` varchar(255) NOT NULL,
  `gym_phone_number` varchar(20) NOT NULL,
  `gym_description` text NOT NULL,
  `gym_amenities` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `gyms`
--

INSERT INTO `gyms` (`gym_id`, `gym_name`, `gym_location`, `gym_phone_number`, `gym_description`, `gym_amenities`) VALUES
(1, 'FitHub Gym', '123 Fitness Street, Cityville', '+1234567890', 'FitHub Gym is a state-of-the-art facility offering various fitness services.', 'Free Wi-Fi, Locker Rooms, Showers, Bembang'),
(2, 'Peak Performance Gym', '456 Power Road, Metropolis', '+0987654321', 'Peak Performance Gym focuses on personalized fitness plans and high-performance training.', 'Sauna, Personal Trainers, Pool');

-- --------------------------------------------------------

--
-- Table structure for table `gym_equipment_images`
--

CREATE TABLE `gym_equipment_images` (
  `id` int(11) NOT NULL,
  `gym_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `membership_plans`
--

CREATE TABLE `membership_plans` (
  `id` int(11) NOT NULL,
  `gym_id` int(11) NOT NULL,
  `plan_name` varchar(255) NOT NULL,
  `duration` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `membership_plans`
--

INSERT INTO `membership_plans` (`id`, `gym_id`, `plan_name`, `duration`, `price`) VALUES
(1, 1, 'Boxing Session', 14, 1500.00),
(2, 1, 'Swimming', 30, 3000.00),
(3, 1, 'Muay Thai', 14, 3000.00);

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `setting_id` int(11) NOT NULL,
  `setting_name` varchar(255) NOT NULL,
  `setting_value` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`setting_id`, `setting_name`, `setting_value`) VALUES
(1, 'site_title', 'FEETHUB'),
(2, 'site_logo', '../img/FITHUB LOGO.png'),
(4, 'site_tagline', 'RAF MASARAP'),
(5, 'contact_email', 'info@fithub.com'),
(6, 'contact_phone', '+1234567890'),
(7, 'facebook_url', 'https://www.facebook.com/yourpage'),
(8, 'instagram_url', 'https://www.instagram.com/yourpage'),
(9, 'github_url', 'https://www.github.com/yourpage'),
(10, 'linkedin_url', 'https://www.linkedin.com/in/yourprofile'),
(12, 'home_description', 'Welcome to FeetHub, your one-stop fitness center!'),
(13, 'about_us_description', 'At FitHub, we believe in empowering individuals to achieve their fitness goals. CHECHEHCEHCEHCEHC'),
(14, 'location_map_url', 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3859.747795512362!2d120.99468357510744!3d14.670249085824388!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3397b69876d12707%3A0x71c2930033703143!2sSterling%20Fitness-Araneta%20Malabon!5e0!3m2!1sen!2sph!4v1734877618999!5m2!1sen!2sph'),
(15, 'footer_content', '© 2025 FitHub. All Rights Reserved.');

-- --------------------------------------------------------

--
-- Table structure for table `tb_data`
--

CREATE TABLE `tb_data` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `comment` varchar(150) NOT NULL,
  `tags` varchar(255) DEFAULT NULL,
  `date` varchar(50) NOT NULL,
  `reply_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tb_data`
--

INSERT INTO `tb_data` (`id`, `name`, `comment`, `tags`, `date`, `reply_id`) VALUES
(75, 'Imman', 'HELLO GUYS', 'First', 'January 09 2025, 11:46:57 AM', 0),
(76, 'Jallenigga', 'WASSUP', 'random', 'January 09 2025, 11:47:13 AM', 0),
(77, 'BASTE', 'WTF', '', 'January 09 2025, 11:47:38 AM', 76),
(78, 'Rafnigga', 'MAHAL KO SI NYL', 'Nyl', 'January 09 2025, 12:40:18 PM', 0),
(79, 'Nyl', 'Mahal din kita pero dapat may 5k ako sa graduation', '', 'January 09 2025, 12:41:05 PM', 78);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(6) UNSIGNED NOT NULL,
  `unique_id` varchar(10) NOT NULL,
  `username` varchar(30) NOT NULL,
  `email` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `age` int(3) NOT NULL,
  `contact_number` varchar(15) NOT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `reg_date` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `membership_start_date` date DEFAULT NULL,
  `membership_end_date` date DEFAULT NULL,
  `membership_status` enum('active','inactive') DEFAULT 'inactive'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `unique_id`, `username`, `email`, `password`, `full_name`, `age`, `contact_number`, `profile_picture`, `reg_date`, `membership_start_date`, `membership_end_date`, `membership_status`) VALUES
(112, 'SG-1000', 'Gab', 'gab@gmail.com', '$2y$10$2eYqFx7mFfK3WpA7rgP6qujJfFav4janOjvG2H60ZMFpFqJwP5rxa', 'Gab Bag', 23, '09123456789', NULL, '2025-01-03 17:56:34', '2025-01-03', '2025-02-02', 'active'),
(120, 'SG-1001', 'Bastian', 'try@gmail.com', '', 'Try Lang', 22, '09123456789', NULL, '2025-01-03 17:11:28', '2024-11-05', '2024-12-05', 'inactive'),
(124, 'SG-1003', 'Mv', 'mv@gmail.com', '$2y$10$Q7u7GxBqwf.2HbEMffB.9uo0GK5zH779EPemY.QkI4ao5AAsXabES', 'M V', 21, '09123456789', NULL, '2024-11-05 14:29:07', NULL, NULL, 'inactive'),
(126, 'SG-1005', 'Nyl', 'nyl@gmail.com', '$2y$10$v91QShhM7qdL5uaOnOdH8epfq9g4ycg/kFDn/7ti7sJEHU.qELmvm', 'Nyl Oreas', 22, '09789456123', NULL, '2024-11-05 14:30:21', NULL, NULL, 'inactive'),
(127, 'SG-1006', 'Real', 'somoherbenne09@gmail.com', '$2y$10$dJJ9lQn2LHXd9ovWhpV.vej8MczI3grmG0qJj5LTAP1dIHnUAq7Ky', 'Hirben', 23, '09453819149', NULL, '2024-11-18 03:15:23', NULL, NULL, 'inactive'),
(128, 'SG-1007', 'Aight', 'test@gmail.com', '$2y$10$7u0EqYcTDOTtuo5opP7hZeqAUcHlO6KRmwBxjR9nJ0yXe2xOGX0Wa', 'Testing', 23, '09123456789', NULL, '2025-01-03 17:09:06', NULL, NULL, 'inactive'),
(129, 'SG-1008', 'bembang', 'bembang@gmail.com', '$2y$10$EmP6jvgawdsQ5K79oJAaF.M6S9i/SDU80uq.bwWfxqoPfKDzOhur.', 'Bembang Ednis', 29, '09123456879', NULL, '2025-01-08 13:11:24', NULL, NULL, 'inactive');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `gyms`
--
ALTER TABLE `gyms`
  ADD PRIMARY KEY (`gym_id`);

--
-- Indexes for table `gym_equipment_images`
--
ALTER TABLE `gym_equipment_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `gym_id` (`gym_id`);

--
-- Indexes for table `membership_plans`
--
ALTER TABLE `membership_plans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `gym_id` (`gym_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`setting_id`),
  ADD UNIQUE KEY `setting_name` (`setting_name`);

--
-- Indexes for table `tb_data`
--
ALTER TABLE `tb_data`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `unique_id` (`unique_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `gyms`
--
ALTER TABLE `gyms`
  MODIFY `gym_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `gym_equipment_images`
--
ALTER TABLE `gym_equipment_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `membership_plans`
--
ALTER TABLE `membership_plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `tb_data`
--
ALTER TABLE `tb_data`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=80;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(6) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=130;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `gym_equipment_images`
--
ALTER TABLE `gym_equipment_images`
  ADD CONSTRAINT `gym_equipment_images_ibfk_1` FOREIGN KEY (`gym_id`) REFERENCES `gyms` (`gym_id`) ON DELETE CASCADE;

--
-- Constraints for table `membership_plans`
--
ALTER TABLE `membership_plans`
  ADD CONSTRAINT `membership_plans_ibfk_1` FOREIGN KEY (`gym_id`) REFERENCES `gyms` (`gym_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
