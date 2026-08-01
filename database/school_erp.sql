-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 01, 2026 at 08:10 PM
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
-- Database: `school_erp`
--

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `category_name` varchar(50) NOT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `category_name`, `status`, `created_at`) VALUES
(1, 'General', 1, '2026-08-01 17:40:30'),
(2, 'SC', 1, '2026-08-01 17:40:30'),
(3, 'ST', 1, '2026-08-01 17:40:30'),
(4, 'OBC', 1, '2026-08-01 17:40:30'),
(5, 'BC', 1, '2026-08-01 17:40:30'),
(6, 'EWS', 1, '2026-08-01 17:40:30');

-- --------------------------------------------------------

--
-- Table structure for table `classes`
--

CREATE TABLE `classes` (
  `id` int(11) NOT NULL,
  `class_name` varchar(50) NOT NULL,
  `class_order` int(11) NOT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `classes`
--

INSERT INTO `classes` (`id`, `class_name`, `class_order`, `status`, `created_at`) VALUES
(1, 'Nursery', 1, 1, '2026-08-01 15:49:04'),
(2, 'LKG', 2, 1, '2026-08-01 15:49:04'),
(3, 'UKG', 3, 1, '2026-08-01 15:49:04'),
(4, 'I', 4, 1, '2026-08-01 15:49:04'),
(5, 'II', 5, 1, '2026-08-01 15:49:04'),
(6, 'III', 6, 1, '2026-08-01 15:49:04'),
(7, 'IV', 7, 1, '2026-08-01 15:49:04'),
(8, 'V', 8, 1, '2026-08-01 15:49:04'),
(9, 'VI', 9, 1, '2026-08-01 15:49:04'),
(10, 'VII', 10, 1, '2026-08-01 15:49:04'),
(11, 'VIII', 11, 1, '2026-08-01 15:49:04'),
(12, 'IX', 12, 1, '2026-08-01 15:49:04'),
(13, 'X', 13, 1, '2026-08-01 15:49:04'),
(14, 'XI', 14, 1, '2026-08-01 15:49:04'),
(15, 'XII', 15, 1, '2026-08-01 15:49:04');

-- --------------------------------------------------------

--
-- Table structure for table `concessions`
--

CREATE TABLE `concessions` (
  `id` int(11) NOT NULL,
  `concession_name` varchar(100) NOT NULL,
  `concession_amount` decimal(10,2) DEFAULT 0.00,
  `status` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `concessions`
--

INSERT INTO `concessions` (`id`, `concession_name`, `concession_amount`, `status`, `created_at`) VALUES
(1, 'No Concession', 0.00, 1, '2026-08-01 17:40:30'),
(2, 'Sibling Concession', 0.00, 1, '2026-08-01 17:40:30'),
(3, 'Staff Concession', 0.00, 1, '2026-08-01 17:40:30'),
(4, 'Management Concession', 0.00, 1, '2026-08-01 17:40:30'),
(5, 'Other', 0.00, 1, '2026-08-01 17:40:30');

-- --------------------------------------------------------

--
-- Table structure for table `games`
--

CREATE TABLE `games` (
  `id` int(11) NOT NULL,
  `game_name` varchar(100) NOT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `games`
--

INSERT INTO `games` (`id`, `game_name`, `status`, `created_at`) VALUES
(1, 'Cricket', 1, '2026-08-01 17:40:30'),
(2, 'Football', 1, '2026-08-01 17:40:30'),
(3, 'Basketball', 1, '2026-08-01 17:40:30'),
(4, 'Volleyball', 1, '2026-08-01 17:40:30'),
(5, 'Badminton', 1, '2026-08-01 17:40:30'),
(6, 'Kabaddi', 1, '2026-08-01 17:40:30'),
(7, 'Kho-Kho', 1, '2026-08-01 17:40:30'),
(8, 'Athletics', 1, '2026-08-01 17:40:30');

-- --------------------------------------------------------

--
-- Table structure for table `houses`
--

CREATE TABLE `houses` (
  `id` int(11) NOT NULL,
  `house_name` varchar(50) NOT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `houses`
--

INSERT INTO `houses` (`id`, `house_name`, `status`, `created_at`) VALUES
(1, 'The Pacific', 1, '2026-08-01 17:51:16'),
(2, 'Sahara', 1, '2026-08-01 17:51:16'),
(3, 'Himalayas', 1, '2026-08-01 17:51:16'),
(4, 'Nile', 1, '2026-08-01 17:51:16');

-- --------------------------------------------------------

--
-- Table structure for table `religions`
--

CREATE TABLE `religions` (
  `id` int(11) NOT NULL,
  `religion_name` varchar(50) NOT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `religions`
--

INSERT INTO `religions` (`id`, `religion_name`, `status`, `created_at`) VALUES
(1, 'Islam', 1, '2026-08-01 17:40:30'),
(2, 'Hindu', 1, '2026-08-01 17:40:30'),
(3, 'Sikh', 1, '2026-08-01 17:40:30'),
(4, 'Christian', 1, '2026-08-01 17:40:30'),
(5, 'Jain', 1, '2026-08-01 17:40:30'),
(6, 'Buddhist', 1, '2026-08-01 17:40:30'),
(7, 'Others', 1, '2026-08-01 17:40:30');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `role_name`, `status`, `created_at`) VALUES
(1, 'Super Admin', 1, '2026-07-26 11:24:42'),
(2, 'Principal', 1, '2026-07-26 11:24:42'),
(3, 'Admin', 1, '2026-07-26 11:24:42'),
(4, 'Accountant', 1, '2026-07-26 11:24:42'),
(5, 'Teacher', 1, '2026-07-26 11:24:42'),
(6, 'Reception', 1, '2026-07-26 11:24:42'),
(7, 'Student', 1, '2026-07-26 11:24:42'),
(8, 'Parent', 1, '2026-07-26 11:24:42');

-- --------------------------------------------------------

--
-- Table structure for table `sections`
--

CREATE TABLE `sections` (
  `id` int(11) NOT NULL,
  `section_name` varchar(10) NOT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sections`
--

INSERT INTO `sections` (`id`, `section_name`, `status`, `created_at`) VALUES
(1, '1', 1, '2026-08-01 15:49:33'),
(2, '2', 1, '2026-08-01 15:49:33'),
(3, '3', 1, '2026-08-01 15:49:33'),
(4, '4', 1, '2026-08-01 15:49:33'),
(5, '5', 1, '2026-08-01 15:49:33'),
(6, '6', 1, '2026-08-01 15:49:33');

-- --------------------------------------------------------

--
-- Table structure for table `transport_routes`
--

CREATE TABLE `transport_routes` (
  `id` int(11) NOT NULL,
  `route_name` varchar(100) NOT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `mobile` varchar(15) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `role_id`, `full_name`, `username`, `password`, `email`, `mobile`, `status`, `created_at`) VALUES
(1, 1, 'Mohammad Imran', 'admin', '0192023a7bbd73250516f069df18b500', 'oasispublicschool2010@gmail.com', '9041499232', 1, '2026-07-26 11:27:54');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `classes`
--
ALTER TABLE `classes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `concessions`
--
ALTER TABLE `concessions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `games`
--
ALTER TABLE `games`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `houses`
--
ALTER TABLE `houses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `religions`
--
ALTER TABLE `religions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indexes for table `sections`
--
ALTER TABLE `sections`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `transport_routes`
--
ALTER TABLE `transport_routes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `role_id` (`role_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `classes`
--
ALTER TABLE `classes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `concessions`
--
ALTER TABLE `concessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `games`
--
ALTER TABLE `games`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `houses`
--
ALTER TABLE `houses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `religions`
--
ALTER TABLE `religions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `transport_routes`
--
ALTER TABLE `transport_routes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
