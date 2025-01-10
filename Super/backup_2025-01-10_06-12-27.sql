-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: gymdb
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `admins`
--

DROP TABLE IF EXISTS `admins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `role` enum('superadmin','admin') NOT NULL DEFAULT 'admin',
  `gym_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admins`
--

LOCK TABLES `admins` WRITE;
/*!40000 ALTER TABLE `admins` DISABLE KEYS */;
INSERT INTO `admins` VALUES (1,'SuperAdmin','superadmin@example.com','$2y$10$Ud0dYJnc3JN5X1rg1xt4seXC/HkSK1iaScsxDTEJnGOLHl.VhBqc6','2025-01-09 04:05:35','superadmin',0),(5,'Admin','admin1@gmail.com','$2y$10$kdI4hHRQPoQCW7L5sbnnZeYmcA2CYmbkYXDVSXWdn0QxUcweEm3U2','2024-11-05 11:07:28','admin',1),(9,'New Admin','newadmin@gmail.com','$2y$10$blrzaoHjf3NPpeJdIrGoMeX3mXJXR2/RmLKE3DWlpq.IBHPHBmlfq','2025-01-09 12:03:41','admin',2),(11,'Admin99','admin99@gmail.com','$2y$10$nqZruXAhOf8npS4HzVA4qO4hLBkd.Y8JQLgpXKF4EQRiZTCiE1aj.','2025-01-09 15:38:44','admin',0),(12,'Admin123','admin123@gmail.com','$2y$10$EGlTr9iLunFQQb3Rk0xgMeY7OuW5SKJS8yMa7MAoa4KWP8CkJ.Yoq','2025-01-09 16:10:04','admin',3),(13,'Hadmin','hadmin@gmail.com','$2y$10$x6BLRpqxqo42KXWqlR2i0.fURFU4K/QFtew9DGlN3AaJRG7ogzos.','2025-01-10 03:45:06','admin',4);
/*!40000 ALTER TABLE `admins` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `attendance`
--

DROP TABLE IF EXISTS `attendance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(6) unsigned NOT NULL,
  `check_in` datetime NOT NULL,
  `check_out` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attendance`
--

LOCK TABLES `attendance` WRITE;
/*!40000 ALTER TABLE `attendance` DISABLE KEYS */;
/*!40000 ALTER TABLE `attendance` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gym_equipment_images`
--

DROP TABLE IF EXISTS `gym_equipment_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gym_equipment_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `gym_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `gym_id` (`gym_id`),
  CONSTRAINT `gym_equipment_images_ibfk_1` FOREIGN KEY (`gym_id`) REFERENCES `gyms` (`gym_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gym_equipment_images`
--

LOCK TABLES `gym_equipment_images` WRITE;
/*!40000 ALTER TABLE `gym_equipment_images` DISABLE KEYS */;
INSERT INTO `gym_equipment_images` VALUES (7,2,'uploads/cat.jpg');
/*!40000 ALTER TABLE `gym_equipment_images` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gyms`
--

DROP TABLE IF EXISTS `gyms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gyms` (
  `gym_id` int(11) NOT NULL AUTO_INCREMENT,
  `gym_name` varchar(255) NOT NULL,
  `gym_location` varchar(255) NOT NULL,
  `gym_phone_number` varchar(20) NOT NULL,
  `gym_description` text NOT NULL,
  `gym_amenities` text NOT NULL,
  PRIMARY KEY (`gym_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gyms`
--

LOCK TABLES `gyms` WRITE;
/*!40000 ALTER TABLE `gyms` DISABLE KEYS */;
INSERT INTO `gyms` VALUES (1,'FitHub Gym','123 Fitness Street, Cityville','+1234567890','FitHub Gym is a state-of-the-art facility offering various fitness services.','Free Wi-Fi, Locker Rooms, Showers, Bembang'),(2,'Peak Performance Gym','456 Power Road, Metropolis','+0987654321','Peak Performance Gym focuses on personalized fitness plans and high-performance training.','Sauna, Personal Trainers, Pool'),(3,'Raffyroad Gymnasium','123 Munoz City','+09999999999','Si Nyl na ang may ari yun naman ang asawa ni raf','Free Wi-Fi, Free Bed, Free Monster Hunter, Investment, Mary Grace, and Kenny Rogers'),(4,'Request Gym','Request Lcoation','09090909090','Testing lang lods','Libreng Sapak');
/*!40000 ALTER TABLE `gyms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gyms_applications`
--

DROP TABLE IF EXISTS `gyms_applications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gyms_applications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `gym_name` varchar(255) NOT NULL,
  `gym_location` varchar(255) NOT NULL,
  `gym_phone_number` varchar(15) NOT NULL,
  `gym_description` text NOT NULL,
  `gym_amenities` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gyms_applications`
--

LOCK TABLES `gyms_applications` WRITE;
/*!40000 ALTER TABLE `gyms_applications` DISABLE KEYS */;
INSERT INTO `gyms_applications` VALUES (1,'Request Gym','Request Lcoation','09090909090','Testing lang lods','Libreng Sapak','2025-01-10 04:14:19','approved');
/*!40000 ALTER TABLE `gyms_applications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `membership_plans`
--

DROP TABLE IF EXISTS `membership_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `membership_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `gym_id` int(11) NOT NULL,
  `plan_name` varchar(255) NOT NULL,
  `duration` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `gym_id` (`gym_id`),
  CONSTRAINT `membership_plans_ibfk_1` FOREIGN KEY (`gym_id`) REFERENCES `gyms` (`gym_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `membership_plans`
--

LOCK TABLES `membership_plans` WRITE;
/*!40000 ALTER TABLE `membership_plans` DISABLE KEYS */;
INSERT INTO `membership_plans` VALUES (1,1,'Boxing Session',14,1500.00),(2,1,'Swimming',30,3000.00),(3,1,'Muay Thai',14,3000.00),(4,2,'Pilates',14,2500.00);
/*!40000 ALTER TABLE `membership_plans` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `settings` (
  `setting_id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_name` varchar(255) NOT NULL,
  `setting_value` text NOT NULL,
  PRIMARY KEY (`setting_id`),
  UNIQUE KEY `setting_name` (`setting_name`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES (1,'site_title','FITHUB'),(2,'site_logo','../img/FITHUB LOGO.png'),(4,'site_tagline','Every rep, every drop of sweat, every challenge you overcome brings'),(5,'contact_email','info@fithub.com'),(6,'contact_phone','+1234567890'),(7,'facebook_url','https://www.facebook.com/yourpage'),(8,'instagram_url','https://www.instagram.com/yourpage'),(9,'github_url','https://www.github.com/yourpage'),(10,'linkedin_url','https://www.linkedin.com/in/yourprofile'),(12,'home_description','Every rep, every drop of sweat, every challenge you overcome brings\r\nYou one step closer to your goals. '),(13,'about_us_description','At FitHub, we believe in empowering individuals to achieve their fitness goals. CHECHEHCEHCEHCEHC'),(14,'location_map_url','https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3859.747795512362!2d120.99468357510744!3d14.670249085824388!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3397b69876d12707%3A0x71c2930033703143!2sSterling%20Fitness-Araneta%20Malabon!5e0!3m2!1sen!2sph!4v1734877618999!5m2!1sen!2sph'),(15,'footer_content','© 2025 FitHub. All Rights Reserved.');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tb_data`
--

DROP TABLE IF EXISTS `tb_data`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tb_data` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `comment` varchar(150) NOT NULL,
  `tags` varchar(255) DEFAULT NULL,
  `date` varchar(50) NOT NULL,
  `reply_id` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=80 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tb_data`
--

LOCK TABLES `tb_data` WRITE;
/*!40000 ALTER TABLE `tb_data` DISABLE KEYS */;
INSERT INTO `tb_data` VALUES (75,'Imman','HELLO GUYS','First','January 09 2025, 11:46:57 AM',0),(76,'Jallenigga','WASSUP','random','January 09 2025, 11:47:13 AM',0),(77,'BASTE','WTF','','January 09 2025, 11:47:38 AM',76),(78,'Rafnigga','MAHAL KO SI NYL','Nyl','January 09 2025, 12:40:18 PM',0),(79,'Nyl','Mahal din kita pero dapat may 5k ako sa graduation','','January 09 2025, 12:41:05 PM',78);
/*!40000 ALTER TABLE `tb_data` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(6) unsigned NOT NULL AUTO_INCREMENT,
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
  `membership_status` enum('active','inactive') DEFAULT 'inactive',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `unique_id` (`unique_id`)
) ENGINE=InnoDB AUTO_INCREMENT=130 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (112,'SG-1000','Gab','gab@gmail.com','$2y$10$2eYqFx7mFfK3WpA7rgP6qujJfFav4janOjvG2H60ZMFpFqJwP5rxa','Gab Bag',23,'09123456789',NULL,'2025-01-03 17:56:34','2025-01-03','2025-02-02','active'),(120,'SG-1001','Bastian','try@gmail.com','','Try Lang',22,'09123456789',NULL,'2025-01-03 17:11:28','2024-11-05','2024-12-05','inactive'),(124,'SG-1003','Mv','mv@gmail.com','$2y$10$Q7u7GxBqwf.2HbEMffB.9uo0GK5zH779EPemY.QkI4ao5AAsXabES','M V',21,'09123456789',NULL,'2024-11-05 14:29:07',NULL,NULL,'inactive'),(126,'SG-1005','Nyl','nyl@gmail.com','$2y$10$v91QShhM7qdL5uaOnOdH8epfq9g4ycg/kFDn/7ti7sJEHU.qELmvm','Nyl Oreas',22,'09789456123',NULL,'2024-11-05 14:30:21',NULL,NULL,'inactive'),(127,'SG-1006','Real','somoherbenne09@gmail.com','$2y$10$dJJ9lQn2LHXd9ovWhpV.vej8MczI3grmG0qJj5LTAP1dIHnUAq7Ky','Hirben',23,'09453819149',NULL,'2024-11-18 03:15:23',NULL,NULL,'inactive'),(128,'SG-1007','Aight','test@gmail.com','$2y$10$7u0EqYcTDOTtuo5opP7hZeqAUcHlO6KRmwBxjR9nJ0yXe2xOGX0Wa','Testing',23,'09123456789',NULL,'2025-01-03 17:09:06',NULL,NULL,'inactive'),(129,'SG-1008','bembang','bembang@gmail.com','$2y$10$EmP6jvgawdsQ5K79oJAaF.M6S9i/SDU80uq.bwWfxqoPfKDzOhur.','Bembang Ednis',29,'09123456879',NULL,'2025-01-08 13:11:24',NULL,NULL,'inactive');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-01-10 13:12:28
