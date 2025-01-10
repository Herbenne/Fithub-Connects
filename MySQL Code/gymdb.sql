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
(9, 'New Admin', 'newadmin@gmail.com', '$2y$10$blrzaoHjf3NPpeJdIrGoMeX3mXJXR2/RmLKE3DWlpq.IBHPHBmlfq', '2025-01-09 12:03:41', 'admin', 2),
(11, 'Admin99', 'admin99@gmail.com', '$2y$10$nqZruXAhOf8npS4HzVA4qO4hLBkd.Y8JQLgpXKF4EQRiZTCiE1aj.', '2025-01-09 15:38:44', 'admin', 0),
(12, 'Admin123', 'admin123@gmail.com', '$2y$10$EGlTr9iLunFQQb3Rk0xgMeY7OuW5SKJS8yMa7MAoa4KWP8CkJ.Yoq', '2025-01-09 16:10:04', 'admin', 3),
(13, 'Hadmin', 'hadmin@gmail.com', '$2y$10$x6BLRpqxqo42KXWqlR2i0.fURFU4K/QFtew9DGlN3AaJRG7ogzos.', '2025-01-10 03:45:06', 'admin', 4);

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
(2, 'Peak Performance Gym', '456 Power Road, Metropolis', '+0987654321', 'Peak Performance Gym focuses on personalized fitness plans and high-performance training.', 'Sauna, Personal Trainers, Pool'),
(3, 'Raffyroad Gymnasium', '123 Munoz City', '+09999999999', 'Si Nyl na ang may ari yun naman ang asawa ni raf', 'Free Wi-Fi, Free Bed, Free Monster Hunter, Investment, Mary Grace, and Kenny Rogers'),
(4, 'Request Gym', 'Request Lcoation', '09090909090', 'Testing lang lods', 'Libreng Sapak'),
(5, 'Imman Gym', '456 Caloocan City', '09789456126', 'LALAKAS KA TAPOS LALAKI KATAWAN MO', 'Libreng Tubeg');

-- --------------------------------------------------------

--
-- Table structure for table `gyms_applications`
--

CREATE TABLE `gyms_applications` (
  `id` int(11) NOT NULL,
  `gym_name` varchar(255) NOT NULL,
  `gym_location` varchar(255) NOT NULL,
  `gym_phone_number` varchar(15) NOT NULL,
  `gym_description` text NOT NULL,
  `gym_amenities` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `gyms_applications`
--

INSERT INTO `gyms_applications` (`id`, `gym_name`, `gym_location`, `gym_phone_number`, `gym_description`, `gym_amenities`, `created_at`, `status`) VALUES
(1, 'Request Gym', 'Request Lcoation', '09090909090', 'Testing lang lods', 'Libreng Sapak', '2025-01-10 04:14:19', 'approved');

-- --------------------------------------------------------

--
-- Table structure for table `gym_equipment_images`
--

CREATE TABLE `gym_equipment_images` (
  `id` int(11) NOT NULL,
  `gym_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `gym_equipment_images`
--

INSERT INTO `gym_equipment_images` (`id`, `gym_id`, `image_path`) VALUES
(7, 2, 'uploads/cat.jpg');

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
(3, 1, 'Muay Thai', 14, 3000.00),
(4, 2, 'Pilates', 14, 2500.00);

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
(1, 'site_title', 'FITHUB'),
(2, 'site_logo', '../img/FITHUB LOGO.png'),
(4, 'site_tagline', 'Every rep, every drop of sweat, every challenge you overcome brings'),
(5, 'contact_email', 'info@fithub.com'),
(6, 'contact_phone', '+1234567890'),
(7, 'facebook_url', 'https://www.facebook.com/yourpage'),
(8, 'instagram_url', 'https://www.instagram.com/yourpage'),
(9, 'github_url', 'https://www.github.com/yourpage'),
(10, 'linkedin_url', 'https://www.linkedin.com/in/yourprofile'),
(12, 'home_description', 'Every rep, every drop of sweat, every challenge you overcome brings\r\nYou one step closer to your goals. '),
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
  `reg_date` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `unique_id`, `username`, `email`, `password`, `full_name`, `age`, `contact_number`, `profile_picture`, `reg_date`) VALUES
(112, 'SG-1000', 'Gab', 'gab@gmail.com', '$2y$10$3QIX1A7iHcs0IQm63wzzauswu29o67eoEizzP9SpQE4nr6jU0vIi2', 'Gab Bag', 23, '09123456789', 'cat.jpg', '2025-01-10 06:31:13'),
(120, 'SG-1001', 'Bastian', 'try@gmail.com', '', 'Try Lang', 22, '09123456789', NULL, '2025-01-03 17:11:28'),
(124, 'SG-1003', 'Mv', 'mv@gmail.com', '$2y$10$Q7u7GxBqwf.2HbEMffB.9uo0GK5zH779EPemY.QkI4ao5AAsXabES', 'M V', 21, '09123456789', NULL, '2024-11-05 14:29:07'),
(126, 'SG-1005', 'Nyl', 'nyl@gmail.com', '$2y$10$v91QShhM7qdL5uaOnOdH8epfq9g4ycg/kFDn/7ti7sJEHU.qELmvm', 'Nyl Oreas', 22, '09789456123', NULL, '2024-11-05 14:30:21'),
(127, 'SG-1006', 'Real', 'somoherbenne09@gmail.com', '$2y$10$dJJ9lQn2LHXd9ovWhpV.vej8MczI3grmG0qJj5LTAP1dIHnUAq7Ky', 'Hirben', 23, '09453819149', NULL, '2024-11-18 03:15:23'),
(128, 'SG-1007', 'IMMANNNN', 'imman@gmail.com', '$2y$10$C.Cp68pehEd.Fi2m5cEE3eKiBQl1VL82g08jhfWZ6cHyPEbfmbUJ.', 'Imman Pogi', 12, '9123456789', 'shrek.jpg', '2025-01-10 06:21:33');

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
-- Indexes for table `gyms_applications`
--
ALTER TABLE `gyms_applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `gyms`
--
ALTER TABLE `gyms`
  MODIFY `gym_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `gyms_applications`
--
ALTER TABLE `gyms_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `gym_equipment_images`
--
ALTER TABLE `gym_equipment_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `membership_plans`
--
ALTER TABLE `membership_plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

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