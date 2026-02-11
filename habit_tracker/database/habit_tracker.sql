-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 11, 2026 at 04:16 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `habit_tracker`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `CalculateUserStreak` (IN `p_user_id` INT)   BEGIN
    SELECT 
        COUNT(DISTINCT DATE(completion_date)) as current_streak
    FROM habit_completions hc
    JOIN habits h ON hc.habit_id = h.id
    WHERE h.user_id = p_user_id
    AND hc.completion_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ORDER BY hc.completion_date DESC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `CleanupOldNotifications` (IN `p_days_old` INT)   BEGIN
    DELETE FROM notifications 
    WHERE sent_at < DATE_SUB(CURDATE(), INTERVAL p_days_old DAY)
    AND status IN ('Sent', 'Failed');
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `GetDailyCalorieSummary` (IN `p_user_id` INT, IN `p_date` DATE)   BEGIN
    SELECT 
        COALESCE(SUM(fe.calories), 0) as calories_consumed,
        COALESCE(SUM(ac.calories_burned), 0) as calories_burned,
        COALESCE(SUM(fe.calories), 0) - COALESCE(SUM(ac.calories_burned), 0) as net_calories
    FROM users u
    LEFT JOIN food_entries fe ON u.id = fe.user_id AND fe.entry_date = p_date
    LEFT JOIN activity_calories ac ON u.id = ac.user_id AND ac.activity_date = p_date
    WHERE u.id = p_user_id;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `activity_calories`
--

CREATE TABLE `activity_calories` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `activity_name` varchar(100) NOT NULL,
  `duration` int(11) NOT NULL COMMENT 'in minutes',
  `calories_burned` int(11) NOT NULL,
  `activity_type` enum('Cardio','Strength','Flexibility','Sports','Other') DEFAULT 'Cardio',
  `activity_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_calories`
--

INSERT INTO `activity_calories` (`id`, `user_id`, `activity_name`, `duration`, `calories_burned`, `activity_type`, `activity_date`, `notes`, `created_at`) VALUES
(1, 2, 'Running', 30, 300, 'Cardio', '2026-02-09', NULL, '2026-02-09 07:41:45'),
(2, 2, 'Yoga', 20, 150, 'Flexibility', '2026-02-09', NULL, '2026-02-09 07:41:45'),
(3, 13, 'Cycling', 10, 20, 'Sports', '2026-02-11', '', '2026-02-11 07:38:09'),
(4, 13, 'Brisk Walking', 30, 150, 'Cardio', '2026-02-11', '', '2026-02-11 07:56:22');

-- --------------------------------------------------------

--
-- Table structure for table `admin_settings`
--

CREATE TABLE `admin_settings` (
  `id` int(11) NOT NULL,
  `smtp_host` varchar(100) DEFAULT 'smtp.gmail.com',
  `smtp_username` varchar(100) DEFAULT NULL,
  `smtp_password` varchar(255) DEFAULT NULL,
  `smtp_port` int(11) DEFAULT 587,
  `sms_provider` varchar(50) DEFAULT 'fast2sms',
  `sms_api_key` varchar(255) DEFAULT NULL,
  `sms_api_secret` varchar(255) DEFAULT NULL,
  `sms_sender_id` varchar(50) DEFAULT 'HABITR',
  `theme_color` varchar(50) DEFAULT '#4CAF50',
  `maintenance_mode` tinyint(4) DEFAULT 0,
  `site_title` varchar(100) DEFAULT 'Habit Tracker',
  `site_description` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_settings`
--

INSERT INTO `admin_settings` (`id`, `smtp_host`, `smtp_username`, `smtp_password`, `smtp_port`, `sms_provider`, `sms_api_key`, `sms_api_secret`, `sms_sender_id`, `theme_color`, `maintenance_mode`, `site_title`, `site_description`, `updated_at`) VALUES
(1, 'smtp.gmail.com', NULL, NULL, 587, 'fast2sms', NULL, NULL, 'HABITR', '#4CAF50', 0, 'Habit Tracker', NULL, '2026-02-09 07:37:45');

-- --------------------------------------------------------

--
-- Table structure for table `backup_history`
--

CREATE TABLE `backup_history` (
  `id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `file_size` bigint(20) DEFAULT NULL,
  `backup_type` enum('manual','auto') DEFAULT 'manual',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `daily_progress`
-- (See below for the actual view)
--
CREATE TABLE `daily_progress` (
`date` date
,`active_users` bigint(21)
,`completed_habits` bigint(21)
,`users_with_habits` bigint(21)
);

-- --------------------------------------------------------

--
-- Table structure for table `food_entries`
--

CREATE TABLE `food_entries` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `food_name` varchar(100) NOT NULL,
  `quantity` varchar(50) NOT NULL,
  `calories` int(11) NOT NULL,
  `protein` decimal(5,2) DEFAULT NULL,
  `carbs` decimal(5,2) DEFAULT NULL,
  `fat` decimal(5,2) DEFAULT NULL,
  `fiber` decimal(5,2) DEFAULT NULL,
  `meal_type` enum('Breakfast','Lunch','Dinner','Snack') DEFAULT 'Snack',
  `serving_size` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `entry_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `food_entries`
--

INSERT INTO `food_entries` (`id`, `user_id`, `food_name`, `quantity`, `calories`, `protein`, `carbs`, `fat`, `fiber`, `meal_type`, `serving_size`, `notes`, `entry_date`, `created_at`) VALUES
(1, 2, 'Oatmeal', '1 bowl', 150, NULL, NULL, NULL, NULL, 'Breakfast', NULL, NULL, '2026-02-09', '2026-02-09 07:41:45'),
(2, 2, 'Chicken Salad', '1 plate', 350, NULL, NULL, NULL, NULL, 'Lunch', NULL, NULL, '2026-02-09', '2026-02-09 07:41:45'),
(3, 2, 'Apple', '1 piece', 95, NULL, NULL, NULL, NULL, 'Snack', NULL, NULL, '2026-02-09', '2026-02-09 07:41:45'),
(5, 13, 'Banana', '1', 105, NULL, NULL, NULL, NULL, 'Breakfast', '', '', '2026-02-11', '2026-02-11 07:36:16'),
(6, 13, 'bat', '150g', 20, NULL, NULL, NULL, NULL, 'Lunch', '', '', '2026-02-11', '2026-02-11 07:37:22'),
(7, 13, 'Chicken Breast', '1', 165, NULL, NULL, NULL, NULL, 'Dinner', '', '', '2026-02-11', '2026-02-11 07:59:23'),
(8, 13, 'Egg', '2', 78, NULL, NULL, NULL, NULL, 'Snack', '', '', '2026-02-11', '2026-02-11 08:14:33');

-- --------------------------------------------------------

--
-- Table structure for table `habits`
--

CREATE TABLE `habits` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(50) NOT NULL,
  `frequency` enum('Daily','Weekly') DEFAULT 'Daily',
  `reminder_time` time DEFAULT NULL,
  `is_important` tinyint(4) DEFAULT 0,
  `is_active` tinyint(4) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `habits`
--

INSERT INTO `habits` (`id`, `user_id`, `name`, `description`, `category`, `frequency`, `reminder_time`, `is_important`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 2, 'Morning Exercise', NULL, 'Fitness', 'Daily', '07:00:00', 0, 1, '2026-02-09 07:41:45', '2026-02-09 07:41:45'),
(2, 2, 'Read 30 minutes', NULL, 'Study', 'Daily', '20:00:00', 0, 1, '2026-02-09 07:41:45', '2026-02-09 07:41:45'),
(3, 2, 'Drink 8 glasses water', NULL, 'Health', 'Daily', NULL, 0, 1, '2026-02-09 07:41:45', '2026-02-09 07:41:45'),
(4, 3, 'Meditation', NULL, 'Personal', 'Daily', '08:00:00', 0, 1, '2026-02-09 07:41:45', '2026-02-09 07:41:45'),
(5, 3, 'Gym workout', NULL, 'Fitness', 'Weekly', '18:00:00', 0, 1, '2026-02-09 07:41:45', '2026-02-09 07:41:45'),
(6, 13, 'Morning exercise', 'Daily', 'Fitness', 'Daily', '12:51:00', 0, 1, '2026-02-11 06:50:20', '2026-02-11 07:21:41'),
(7, 13, 'walk', 'ty', 'Health', 'Daily', '20:29:00', 0, 1, '2026-02-11 14:26:43', '2026-02-11 14:26:43');

-- --------------------------------------------------------

--
-- Table structure for table `habit_completions`
--

CREATE TABLE `habit_completions` (
  `id` int(11) NOT NULL,
  `habit_id` int(11) NOT NULL,
  `completion_date` date NOT NULL,
  `completed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL,
  `mood` enum('?','?','?','?','?') DEFAULT '?'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `habit_completions`
--

INSERT INTO `habit_completions` (`id`, `habit_id`, `completion_date`, `completed_at`, `notes`, `mood`) VALUES
(1, 1, '2026-02-09', '2026-02-09 07:41:45', NULL, '😊'),
(2, 2, '2026-02-09', '2026-02-09 07:41:45', NULL, '😊'),
(3, 1, '2026-02-08', '2026-02-09 07:41:45', NULL, '😊'),
(4, 2, '2026-02-08', '2026-02-09 07:41:45', NULL, '😊'),
(5, 3, '2026-02-08', '2026-02-09 07:41:45', NULL, '😊'),
(6, 6, '2026-02-11', '2026-02-11 07:32:29', NULL, '😊'),
(7, 7, '2026-02-11', '2026-02-11 14:27:29', NULL, '😊');

-- --------------------------------------------------------

--
-- Stand-in structure for view `habit_success_rates`
-- (See below for the actual view)
--
CREATE TABLE `habit_success_rates` (
`habit_id` int(11)
,`habit_name` varchar(100)
,`user_id` int(11)
,`category` varchar(50)
,`times_completed` bigint(21)
,`days_since_creation` int(8)
,`success_rate` decimal(26,2)
);

-- --------------------------------------------------------

--
-- Table structure for table `login_history`
--

CREATE TABLE `login_history` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `login_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `success` tinyint(4) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` enum('Email','SMS','Push') NOT NULL,
  `title` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('Pending','Sent','Failed') DEFAULT 'Pending',
  `retry_count` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `token` varchar(100) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `email`, `token`, `expires_at`, `created_at`) VALUES
(1, 'jahangiruji2019@gmail.com', '726719', '2026-02-09 08:17:03', '2026-02-09 08:07:03');

-- --------------------------------------------------------

--
-- Table structure for table `system_logs`
--

CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL,
  `log_type` enum('error','warning','info','debug') DEFAULT 'info',
  `message` text NOT NULL,
  `context` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `email_verified` tinyint(4) DEFAULT 0,
  `verification_code` varchar(100) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT 'default.png',
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `height` decimal(5,2) DEFAULT NULL COMMENT 'in cm',
  `weight` decimal(5,2) DEFAULT NULL COMMENT 'in kg',
  `weight_goal` enum('Gain','Loss','Maintain') DEFAULT 'Maintain',
  `target_weight` decimal(5,2) DEFAULT NULL,
  `activity_level` enum('sedentary','light','moderate','very','extra') DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `notification_pref` enum('Email','SMS','Both','None') DEFAULT 'Both',
  `dark_mode` tinyint(4) DEFAULT 0,
  `language` varchar(10) DEFAULT 'en',
  `is_active` tinyint(4) DEFAULT 1,
  `is_admin` tinyint(4) DEFAULT 0,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `dob` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `phone`, `password`, `email_verified`, `verification_code`, `profile_picture`, `gender`, `age`, `height`, `weight`, `weight_goal`, `target_weight`, `activity_level`, `bio`, `notification_pref`, `dark_mode`, `language`, `is_active`, `is_admin`, `last_login`, `created_at`, `updated_at`, `dob`) VALUES
(1, 'Admin', 'admin@habittracker.com', NULL, '$2y$10$YourHashedPasswordHere', 1, NULL, 'default.png', NULL, NULL, NULL, NULL, 'Maintain', NULL, NULL, NULL, 'Both', 0, 'en', 1, 1, NULL, '2026-02-09 07:37:45', '2026-02-09 07:37:45', NULL),
(2, 'John Doe', 'john@example.com', NULL, '$2y$10$SampleHash123', 1, NULL, 'default.png', NULL, NULL, NULL, NULL, 'Maintain', NULL, NULL, NULL, 'Both', 0, 'en', 1, 0, NULL, '2026-02-09 07:41:45', '2026-02-09 07:41:45', NULL),
(3, 'Jane Smith', 'jane@example.com', NULL, '$2y$10$SampleHash456', 1, NULL, 'default.png', NULL, NULL, NULL, NULL, 'Maintain', NULL, NULL, NULL, 'Both', 0, 'en', 1, 0, NULL, '2026-02-09 07:41:45', '2026-02-09 07:41:45', NULL),
(13, '', 'jahangiruji2021@gmail.com', '01317334699', '$2y$10$UoaNEdwgKYrKqN3183tAmunxT3u6oPA2tnVMIdEr5813nQxpX6APO', 1, '07967496bf410b9ab3a2878bb288bb7c', '1770806536_images.jfif', NULL, 24, 170.00, 58.00, 'Gain', 65.00, 'moderate', NULL, 'Both', 0, 'bn', 1, 0, '2026-02-11 19:49:16', '2026-02-09 09:30:56', '2026-02-11 13:49:16', NULL);

-- --------------------------------------------------------

--
-- Stand-in structure for view `user_stats`
-- (See below for the actual view)
--
CREATE TABLE `user_stats` (
`user_id` int(11)
,`name` varchar(100)
,`email` varchar(100)
,`total_habits` bigint(21)
,`completed_habits` bigint(21)
,`active_days` bigint(21)
,`last_activity` date
);

-- --------------------------------------------------------

--
-- Table structure for table `water_intake`
--

CREATE TABLE `water_intake` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(4,2) NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `water_intake`
--

INSERT INTO `water_intake` (`id`, `user_id`, `amount`, `created_at`) VALUES
(1, 13, 2.30, '2026-02-11 13:55:16'),
(2, 13, 2.30, '2026-02-11 13:55:30');

-- --------------------------------------------------------

--
-- Structure for view `daily_progress`
--
DROP TABLE IF EXISTS `daily_progress`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `daily_progress`  AS SELECT cast(`hc`.`completion_date` as date) AS `date`, count(distinct `h`.`user_id`) AS `active_users`, count(distinct `hc`.`habit_id`) AS `completed_habits`, count(distinct `h`.`user_id`) AS `users_with_habits` FROM (`habit_completions` `hc` join `habits` `h` on(`hc`.`habit_id` = `h`.`id`)) GROUP BY cast(`hc`.`completion_date` as date) ORDER BY cast(`hc`.`completion_date` as date) DESC ;

-- --------------------------------------------------------

--
-- Structure for view `habit_success_rates`
--
DROP TABLE IF EXISTS `habit_success_rates`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `habit_success_rates`  AS SELECT `h`.`id` AS `habit_id`, `h`.`name` AS `habit_name`, `h`.`user_id` AS `user_id`, `h`.`category` AS `category`, count(`hc`.`id`) AS `times_completed`, to_days(curdate()) - to_days(cast(`h`.`created_at` as date)) + 1 AS `days_since_creation`, round(count(`hc`.`id`) / (to_days(curdate()) - to_days(cast(`h`.`created_at` as date)) + 1) * 100,2) AS `success_rate` FROM (`habits` `h` left join `habit_completions` `hc` on(`h`.`id` = `hc`.`habit_id`)) GROUP BY `h`.`id`, `h`.`name`, `h`.`user_id`, `h`.`category`, `h`.`created_at` ;

-- --------------------------------------------------------

--
-- Structure for view `user_stats`
--
DROP TABLE IF EXISTS `user_stats`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `user_stats`  AS SELECT `u`.`id` AS `user_id`, `u`.`name` AS `name`, `u`.`email` AS `email`, count(distinct `h`.`id`) AS `total_habits`, count(distinct `hc`.`id`) AS `completed_habits`, count(distinct cast(`hc`.`completion_date` as date)) AS `active_days`, max(`hc`.`completion_date`) AS `last_activity` FROM ((`users` `u` left join `habits` `h` on(`u`.`id` = `h`.`user_id`)) left join `habit_completions` `hc` on(`h`.`id` = `hc`.`habit_id`)) WHERE `u`.`is_admin` = 0 GROUP BY `u`.`id`, `u`.`name`, `u`.`email` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_calories`
--
ALTER TABLE `activity_calories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_activity_date` (`activity_date`),
  ADD KEY `idx_activity_type` (`activity_type`),
  ADD KEY `idx_activity_calories_user_date` (`user_id`,`activity_date`);

--
-- Indexes for table `admin_settings`
--
ALTER TABLE `admin_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `backup_history`
--
ALTER TABLE `backup_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `food_entries`
--
ALTER TABLE `food_entries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_entry_date` (`entry_date`),
  ADD KEY `idx_meal_type` (`meal_type`),
  ADD KEY `idx_food_entries_user_date` (`user_id`,`entry_date`);

--
-- Indexes for table `habits`
--
ALTER TABLE `habits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_frequency` (`frequency`),
  ADD KEY `idx_habits_active` (`is_active`);

--
-- Indexes for table `habit_completions`
--
ALTER TABLE `habit_completions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_habit_date` (`habit_id`,`completion_date`),
  ADD KEY `idx_completion_date` (`completion_date`),
  ADD KEY `idx_habit_completion` (`habit_id`,`completion_date`),
  ADD KEY `idx_habit_completions_user_date` (`habit_id`,`completion_date`);

--
-- Indexes for table `login_history`
--
ALTER TABLE `login_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_login_at` (`login_at`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_sent_at` (`sent_at`),
  ADD KEY `idx_notifications_type` (`type`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_email` (`email`);

--
-- Indexes for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_log_type` (`log_type`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_users_admin` (`is_admin`);

--
-- Indexes for table `water_intake`
--
ALTER TABLE `water_intake`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_calories`
--
ALTER TABLE `activity_calories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `admin_settings`
--
ALTER TABLE `admin_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `backup_history`
--
ALTER TABLE `backup_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `food_entries`
--
ALTER TABLE `food_entries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `habits`
--
ALTER TABLE `habits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `habit_completions`
--
ALTER TABLE `habit_completions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `login_history`
--
ALTER TABLE `login_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `system_logs`
--
ALTER TABLE `system_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `water_intake`
--
ALTER TABLE `water_intake`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_calories`
--
ALTER TABLE `activity_calories`
  ADD CONSTRAINT `activity_calories_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `backup_history`
--
ALTER TABLE `backup_history`
  ADD CONSTRAINT `backup_history_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `food_entries`
--
ALTER TABLE `food_entries`
  ADD CONSTRAINT `food_entries_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `habits`
--
ALTER TABLE `habits`
  ADD CONSTRAINT `habits_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `habit_completions`
--
ALTER TABLE `habit_completions`
  ADD CONSTRAINT `habit_completions_ibfk_1` FOREIGN KEY (`habit_id`) REFERENCES `habits` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `login_history`
--
ALTER TABLE `login_history`
  ADD CONSTRAINT `login_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD CONSTRAINT `system_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
