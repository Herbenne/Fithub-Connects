-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3307
-- Generation Time: Apr 03, 2025 at 04:46 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `gymdb1`
--

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
  `gym_amenities` text NOT NULL,
  `equipment_images` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`equipment_images`)),
  `owner_id` int(11) DEFAULT NULL,
  `gym_thumbnail` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `rating` decimal(3,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `gyms`
--

INSERT INTO `gyms` (`gym_id`, `gym_name`, `gym_location`, `gym_phone_number`, `gym_description`, `gym_amenities`, `equipment_images`, `owner_id`, `gym_thumbnail`, `created_at`, `status`, `rating`) VALUES
(7, 'Admin', '123 Munoz', '09090909090', 'Munoz', 'Cardio Machine', '[\"..\\/assets\\/images\\/Screenshot (1964).png\",\"..\\/assets\\/images\\/Screenshot (1963).png\",\"..\\/assets\\/images\\/Screenshot (1962).png\"]', 18, '../assets/images/Screenshot (2014).png', '2025-03-09 02:27:43', 'approved', 3.00),
(8, 'Admin 1', '456 Caloocan City', '+09999999999', 'Caloocan', 'Wifi', '[\"..\\/assets\\/images\\/Screenshot (2037).png\",\"..\\/assets\\/images\\/Screenshot (1627).png\",\"..\\/assets\\/images\\/Screenshot (1843).png\"]', 19, '../assets/images/Screenshot (1844).png', '2025-03-09 02:28:22', 'approved', 4.50),
(9, 'Admin 2', '789 Valenzuela', '09789456126', 'Valenzuela', 'Water', '[\"..\\/assets\\/images\\/Screenshot (1168).png\",\"..\\/assets\\/images\\/Screenshot (931).png\"]', 20, '../assets/images/Screenshot (1212).png', '2025-03-09 02:28:47', 'approved', 4.00),
(10, 'Admin 3', 'Quezon', '09456123789', 'Quezon', 'Gloves', '[\"..\\/assets\\/images\\/Screenshot (968).png\",\"..\\/assets\\/images\\/Screenshot (973).png\"]', 21, '../assets/images/Screenshot (970).png', '2025-03-09 02:29:20', 'approved', 2.00);

-- --------------------------------------------------------

--
-- Table structure for table `gym_members`
--

CREATE TABLE `gym_members` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `gym_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` varchar(20) DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `gym_members`
--

INSERT INTO `gym_members` (`id`, `user_id`, `gym_id`, `plan_id`, `joined_at`, `start_date`, `end_date`, `status`) VALUES
(12, 23, 8, 5, '2025-04-03 01:35:31', '2025-04-03', '2025-05-03', 'active'),
(13, 24, 10, 6, '2025-04-03 02:23:51', '2025-04-03', '2025-10-03', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `gym_reviews`
--

CREATE TABLE `gym_reviews` (
  `review_id` int(11) NOT NULL,
  `gym_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` int(1) NOT NULL CHECK (`rating` between 1 and 5),
  `comment` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `gym_reviews`
--

INSERT INTO `gym_reviews` (`review_id`, `gym_id`, `user_id`, `rating`, `comment`, `created_at`) VALUES
(18, 8, 18, 5, 'WOAH', '2025-03-09 03:27:30'),
(19, 10, 18, 2, 'ANo yan?', '2025-03-09 03:27:54');

-- --------------------------------------------------------

--
-- Table structure for table `membership_plans`
--

CREATE TABLE `membership_plans` (
  `plan_id` int(11) NOT NULL,
  `gym_id` int(11) NOT NULL,
  `plan_name` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `duration` varchar(50) NOT NULL COMMENT 'Format: X months',
  `description` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `membership_plans`
--

INSERT INTO `membership_plans` (`plan_id`, `gym_id`, `plan_name`, `price`, `duration`, `description`, `created_at`) VALUES
(5, 7, 'Monthly', 1000.00, '1 month', '30 Days', '2025-03-09 02:31:45'),
(6, 8, 'Barilan Session', 5000.00, '1 month', 'Bang Bang', '2025-03-09 02:33:53'),
(7, 9, 'Axie Scholarship', 5000.00, '3 months', 'Turuan ka mag Axie', '2025-03-09 02:36:46'),
(8, 10, 'Minecraft', 12000.00, '6 months', 'Road to Elder', '2025-03-09 02:39:35'),
(9, 9, 'Pegaxy', 2500.00, '1 Month', 'Kabayo', '2025-03-09 03:47:31');

-- --------------------------------------------------------

--
-- Table structure for table `review_comments`
--

CREATE TABLE `review_comments` (
  `comment_id` int(11) NOT NULL,
  `review_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `comment` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `unique_id` varchar(10) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `age` int(3) DEFAULT NULL,
  `contact_number` varchar(15) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `reg_date` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `role` enum('superadmin','admin','user','member') NOT NULL DEFAULT 'user',
  `gym_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `unique_id`, `username`, `email`, `first_name`, `last_name`, `password`, `age`, `contact_number`, `profile_picture`, `reg_date`, `created_at`, `role`, `gym_id`) VALUES
(4, 'SADM001', 'superadmin', 'superadmin@admin.com', 'Super', 'Admin', '$2y$10$OoZZUHZMS0DkmWmLbkba3OJpztFmuH6j.IKH.vRXpx7f9m3ELk2RO', NULL, '', 'assets/images/profile_pictures/profile_4_1743646283.jpg', '2025-04-03 02:11:23', '2025-03-08 07:38:23', 'superadmin', NULL),
(18, 'USR67ccfc4', 'admin', 'admin@gmail.com', 'admin', 'admin', '$2y$10$59FhhkoJHET8xxs5GgQQROEw26I/NpMtRuvATQnbXnek8lAiFsqc2', NULL, NULL, NULL, '2025-03-09 02:29:36', '2025-03-09 02:26:11', 'admin', NULL),
(19, 'USR67ccfc6', 'admin1', 'admin1@gmail.com', 'admin', 'admin', '$2y$10$jIVt5ydoGncD2K8n9zWfgeUYZ34tOetfk4Nsodhn19CFVjzAU4Hdq', NULL, NULL, NULL, '2025-03-09 02:30:01', '2025-03-09 02:26:47', 'admin', NULL),
(20, 'USR67ccfc7', 'admin2', 'admin2@gmail.com', 'admin', 'admin', '$2y$10$rEKZPrZawt5jOkq2/J5KsuiyKN43wZkK8bB1KJxHjGotgOcSdKoHy', NULL, NULL, NULL, '2025-03-09 02:29:57', '2025-03-09 02:27:01', 'admin', NULL),
(21, 'USR67ccfc8', 'Admin3', 'admin3@gmail.com', 'admin', 'admin', '$2y$10$j/6amBrbizsf8lnL5rIckei5hhsdF6lvlKx4KIh/Nyw6jNJPtCrl.', NULL, NULL, NULL, '2025-03-09 02:29:54', '2025-03-09 02:27:13', 'admin', NULL),
(22, 'USR67cd2f6', 'user1', 'user1@gmail.com', 'User', 'Second', '$2y$10$pVDrYZToBbTgifHU5sRwW.bmwW7R5ZjbCuxbQLUW5QSpONoSmJWAK', NULL, NULL, NULL, '2025-03-09 06:04:26', '2025-03-09 06:04:26', 'user', NULL),
(23, 'USR67ce42f', 'user', 'hi@gmail.com', 'user', 'user', '$2y$10$VJWFbISi6qrB79rhmhp5.ejGVVL9b5UMGLt//gbL1cOePqZBdI6xy', NULL, NULL, NULL, '2025-04-03 01:35:31', '2025-03-10 01:40:05', 'member', NULL),
(24, 'USR9b41e3', 'rey', 'rey@gmail.com', 'Rey', 'Rey', '$2y$10$wrY.PLjSGYCZ/lKsCSjOjunBAbc.7/Se3SWJuUp2EcrrxF41lKuAG', NULL, NULL, NULL, '2025-04-03 02:23:51', '2025-04-03 01:00:23', 'member', NULL),
(25, 'USR957207', 'gabayuban', 'gabayuban@gmail.com', 'Gab', 'Ayuban', '$2y$10$hLrphZp3KI0DzlxD6WPMAugl/vFPXzvV/ce967HSro8DKGbNb4p36', NULL, NULL, NULL, '2025-04-03 02:45:36', '2025-04-03 02:45:36', 'user', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `gyms`
--
ALTER TABLE `gyms`
  ADD PRIMARY KEY (`gym_id`),
  ADD KEY `owner_id` (`owner_id`),
  ADD KEY `idx_owner_id` (`owner_id`);

--
-- Indexes for table `gym_members`
--
ALTER TABLE `gym_members`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `gym_id` (`gym_id`),
  ADD KEY `plan_id` (`plan_id`);

--
-- Indexes for table `gym_reviews`
--
ALTER TABLE `gym_reviews`
  ADD PRIMARY KEY (`review_id`),
  ADD KEY `gym_id` (`gym_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `membership_plans`
--
ALTER TABLE `membership_plans`
  ADD PRIMARY KEY (`plan_id`),
  ADD KEY `gym_id` (`gym_id`);

--
-- Indexes for table `review_comments`
--
ALTER TABLE `review_comments`
  ADD PRIMARY KEY (`comment_id`),
  ADD KEY `review_id` (`review_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `unique_id` (`unique_id`),
  ADD KEY `idx_owner` (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `gyms`
--
ALTER TABLE `gyms`
  MODIFY `gym_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `gym_members`
--
ALTER TABLE `gym_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `gym_reviews`
--
ALTER TABLE `gym_reviews`
  MODIFY `review_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `membership_plans`
--
ALTER TABLE `membership_plans`
  MODIFY `plan_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `review_comments`
--
ALTER TABLE `review_comments`
  MODIFY `comment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `gyms`
--
ALTER TABLE `gyms`
  ADD CONSTRAINT `gyms_ibfk_1` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `gym_members`
--
ALTER TABLE `gym_members`
  ADD CONSTRAINT `gym_members_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `gym_members_ibfk_2` FOREIGN KEY (`gym_id`) REFERENCES `gyms` (`gym_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `gym_members_ibfk_3` FOREIGN KEY (`plan_id`) REFERENCES `membership_plans` (`plan_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `gym_reviews`
--
ALTER TABLE `gym_reviews`
  ADD CONSTRAINT `gym_reviews_ibfk_1` FOREIGN KEY (`gym_id`) REFERENCES `gyms` (`gym_id`),
  ADD CONSTRAINT `gym_reviews_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `membership_plans`
--
ALTER TABLE `membership_plans`
  ADD CONSTRAINT `membership_plans_ibfk_1` FOREIGN KEY (`gym_id`) REFERENCES `gyms` (`gym_id`) ON DELETE CASCADE;

--
-- Constraints for table `review_comments`
--
ALTER TABLE `review_comments`
  ADD CONSTRAINT `review_comments_ibfk_1` FOREIGN KEY (`review_id`) REFERENCES `gym_reviews` (`review_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `review_comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
