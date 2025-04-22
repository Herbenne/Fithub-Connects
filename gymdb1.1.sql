-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3307
-- Generation Time: Apr 22, 2025 at 05:04 AM
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
(16, 'Gym', 'Munoz', '09789456123', 'Near Waltermart', 'Free Water', '[]', 35, NULL, '2025-04-22 02:03:27', 'rejected', 0.00),
(17, 'Gym1', 'Caloocan', '0978456123', 'Near Dali Store', 'Free WIFI', '[]', 36, NULL, '2025-04-22 02:04:05', 'approved', 0.00),
(18, 'Gym2', 'Valenzuela', '09852369741', 'Near SM Valenzuela', 'Free Water', '[]', 37, NULL, '2025-04-22 02:04:46', 'approved', 0.00),
(19, 'Gym3', 'Makati', '09852365148', 'Near BGC', 'Free For All', '[]', 38, NULL, '2025-04-22 02:05:21', 'approved', 0.00),
(20, 'Gym', 'Munoz', '09856658451', 'Near Waltermart', 'Free Locker with money hehe', '[\"..\\/assets\\/images\\/Screenshot (6806fcc990b47).jpg\",\"..\\/assets\\/images\\/Screenshot (6806fde377556).jpeg\"]', 35, '../assets/images/Screenshot (6806fcc9903fc).jpeg', '2025-04-22 02:13:04', 'approved', 0.00);

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
(16, 34, 20, 16, '2025-04-22 02:58:18', '2025-04-22', '2025-05-22', 'active');

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
(26, 20, 30, 3, 'Nice Shrek', '2025-04-22 02:25:00');

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
(16, 20, 'ML', 500.00, '1 month', 'EMEL LEGENDS', '2025-04-22 02:39:06'),
(18, 20, 'COD', 1000.00, '3 months', 'COD OF DUTY', '2025-04-22 02:40:23');

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

--
-- Dumping data for table `review_comments`
--

INSERT INTO `review_comments` (`comment_id`, `review_id`, `user_id`, `comment`, `created_at`) VALUES
(34, 26, 34, 'Me too, I love Shreeek', '2025-04-22 02:42:20');

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
(30, 'USRc980d6', 'Gabby', 'gab@gmail.com', 'Gabriel', 'Ayuban', '$2y$10$fsf8WSjcb3yHLvNXaYuDe.QXDed8XLuv3WMHU/4TiBm.QlbEWSJNq', NULL, NULL, NULL, '2025-04-22 01:59:47', '2025-04-22 01:59:47', 'user', NULL),
(31, 'USR53fe13', 'Raffyroad', 'raffyroad@gmail.com', 'Rafael', 'Fernandez', '$2y$10$bFyDtV3fqAwUslMPIObU3uHn12mcqB.KGIAbP6lR3/wRdZxB9Ph.q', NULL, NULL, NULL, '2025-04-22 02:00:09', '2025-04-22 02:00:09', 'user', NULL),
(32, 'USR80062e', 'Jallen', 'jallen@gmail.com', 'Jallen', 'Portugal', '$2y$10$6ZFQcf36THJMAwCb7tCK..HtOXWTSSfX3MLNG7kkuhZyz2hpbE8p6', NULL, NULL, NULL, '2025-04-22 02:00:29', '2025-04-22 02:00:29', 'user', NULL),
(33, 'USR75f974', 'Nyl', 'nyl@gmail.com', 'Nyl', 'Oreas', '$2y$10$YOeTWLe5CW3Nrgqx4YglMOf/acLEOCLkaz5UYzyTm59ezdQOCGYKy', NULL, NULL, NULL, '2025-04-22 02:00:53', '2025-04-22 02:00:53', 'user', NULL),
(34, 'USR86bc7f', 'Imman', 'imman@gmail.com', 'Immanuel', 'Dichosa', '$2y$10$E9yPuuCiLVyApmS6zJqBF..5Ok6t5R4J.Axw6N7lbbCvI84YFUjzi', NULL, NULL, NULL, '2025-04-22 02:58:18', '2025-04-22 02:01:12', 'member', NULL),
(35, 'USR49fd5c', 'Gym', 'gym@gmail.com', 'Munoz', 'Gym', '$2y$10$HDxS91w5fUTVE5P4YqzSWOzJo.6DGSM7uwlQRr1obKi15oyTL.JAG', NULL, NULL, NULL, '2025-04-22 02:13:29', '2025-04-22 02:01:39', 'admin', NULL),
(36, 'USR4313ea', 'Gym1', 'gym1@gmail.com', 'Caloocan', 'Gym', '$2y$10$fi0kbl0U6K4y/78b2ijtMORate6Fuc.XwpIQAuMO4FOuf0srwL/Li', NULL, NULL, NULL, '2025-04-22 02:12:08', '2025-04-22 02:02:01', 'admin', NULL),
(37, 'USR3fe2eb', 'Gym2', 'gym2@gmail.com', 'Valenzuela', 'Gym', '$2y$10$Y9aDbK46iM08Qma3ngwhUun19xKf6ly8HZw0L..wPNZIFtVnH/2X2', NULL, NULL, NULL, '2025-04-22 02:12:03', '2025-04-22 02:02:24', 'admin', NULL),
(38, 'USRf6285a', 'Gym3', 'gym3@gmail.com', 'Makati', 'Gym', '$2y$10$U6zuMBO4RjbQQ5nAHqV/6.uoAYor0JGTkHbHDWHygY2KORAsYNkFW', NULL, NULL, NULL, '2025-04-22 02:11:56', '2025-04-22 02:02:47', 'admin', NULL);

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
  MODIFY `gym_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `gym_members`
--
ALTER TABLE `gym_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `gym_reviews`
--
ALTER TABLE `gym_reviews`
  MODIFY `review_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `membership_plans`
--
ALTER TABLE `membership_plans`
  MODIFY `plan_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `review_comments`
--
ALTER TABLE `review_comments`
  MODIFY `comment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

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
