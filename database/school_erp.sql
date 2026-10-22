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
-- Table structure for table `teachers`
--

CREATE TABLE `teachers` (
  `id` int(11) NOT NULL,
  `employee_code` varchar(50) NOT NULL,
  `full_name` varchar(120) NOT NULL,
  `role` varchar(100) DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `alternate_mobile` varchar(20) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `designation` varchar(100) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `qualification` varchar(255) DEFAULT NULL,
  `joining_date` date DEFAULT NULL,
  `address` text DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `class_incharge_id` int(11) DEFAULT NULL,
  `class_incharge_section_id` int(11) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `teacher_subjects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_teacher_subject` (`teacher_id`,`subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `teacher_class_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `section_id` int(11) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_teacher_subject_class_section` (`teacher_id`,`subject_id`,`class_id`,`section_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `exam_types`
--

CREATE TABLE `exam_types` (
  `id` int(11) NOT NULL,
  `exam_type_name` varchar(100) NOT NULL,
  `max_marks` int(11) DEFAULT 100,
  `status` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `exam_types`
--

INSERT INTO `exam_types` (`id`, `exam_type_name`, `max_marks`, `status`, `created_at`) VALUES
(1, 'PA-1', 20, 1, CURRENT_TIMESTAMP),
(2, 'PA-2', 20, 1, CURRENT_TIMESTAMP),
(3, 'PA-3', 20, 1, CURRENT_TIMESTAMP),
(4, 'PA-4', 20, 1, CURRENT_TIMESTAMP),
(5, 'Term-1', 100, 1, CURRENT_TIMESTAMP),
(6, 'Term-2', 100, 1, CURRENT_TIMESTAMP);

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
-- Table structure for table `students`
-- The application relies on this table; older dumps omitted it.
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `admission_no` varchar(30) NOT NULL,
  `admission_date` date NOT NULL,
  `class_id` int(11) NOT NULL,
  `section_id` int(11) NOT NULL,
  `house_id` int(11) DEFAULT NULL,
  `roll_no` varchar(20) DEFAULT NULL,
  `student_name` varchar(100) NOT NULL,
  `gender` enum('Male','Female','Other') NOT NULL,
  `dob` date NOT NULL,
  `blood_group` varchar(5) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `father_name` varchar(100) NOT NULL,
  `father_occupation` varchar(100) DEFAULT NULL,
  `father_mobile` varchar(15) DEFAULT NULL,
  `mother_name` varchar(100) DEFAULT NULL,
  `mother_occupation` varchar(100) DEFAULT NULL,
  `mother_mobile` varchar(15) DEFAULT NULL,
  `guardian_name` varchar(100) DEFAULT NULL,
  `guardian_mobile` varchar(15) DEFAULT NULL,
  `alternate_mobile` varchar(15) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `district` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `pincode` varchar(10) DEFAULT NULL,
  `house` varchar(50) DEFAULT NULL,
  `transport` varchar(50) DEFAULT NULL,
  `game` varchar(100) DEFAULT NULL,
  `concession` decimal(10,2) DEFAULT 0.00,
  `medical_information` text DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `status` tinyint(4) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `account_type` enum('Admin','Teacher','Student') NOT NULL DEFAULT 'Admin',
  `linked_id` int(11) DEFAULT NULL,
  `full_name` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `mobile` varchar(15) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `fee_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fee_name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fee_type_name` (`fee_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `fee_structures` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `fee_type_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `frequency` enum('Monthly','Quarterly','Annual','One Time') NOT NULL DEFAULT 'Monthly',
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `fee_schedules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fee_structure_id` int(11) NOT NULL,
  `due_date` date NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `student_fee_concessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `fee_type_id` int(11) DEFAULT NULL,
  `concession_name` varchar(100) NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `valid_from` date DEFAULT NULL,
  `valid_to` date DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `fee_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `fee_type_id` int(11) DEFAULT NULL,
  `payment_date` date NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `receipt_no` varchar(50) DEFAULT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `portal_announcements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(150) NOT NULL,
  `body` text NOT NULL,
  `audience` enum('All','Students','Teachers') NOT NULL DEFAULT 'All',
  `class_id` int(11) DEFAULT NULL,
  `section_id` int(11) DEFAULT NULL,
  `publish_date` date NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `portal_holidays` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `holiday_name` varchar(150) NOT NULL,
  `holiday_date` date NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `portal_homework` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `section_id` int(11) DEFAULT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `title` varchar(150) NOT NULL,
  `description` text NOT NULL,
  `due_date` date DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `report_card_group_subjects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_key` varchar(20) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_report_group_subject` (`group_key`,`subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `report_card_class_settings` (
  `class_id` int(11) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`class_id`),
  CONSTRAINT `fk_report_class_setting_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `report_card_class_subjects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_report_class_subject` (`class_id`,`subject_id`),
  KEY `idx_report_class_order` (`class_id`,`display_order`),
  CONSTRAINT `fk_report_class_subject_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `hpc_observations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `session_id` int(11) NOT NULL DEFAULT 1,
  `category` varchar(100) NOT NULL,
  `observation` varchar(150) NOT NULL,
  `term1_value` varchar(10) DEFAULT NULL,
  `term2_value` varchar(10) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hpc_observation_student` (`student_id`,`session_id`,`category`,`observation`),
  KEY `idx_hpc_observation_session` (`session_id`,`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `student_promotions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `from_class_id` int(11) NOT NULL,
  `from_section_id` int(11) NOT NULL,
  `to_class_id` int(11) NOT NULL,
  `to_section_id` int(11) NOT NULL,
  `promoted_by` int(11) DEFAULT NULL,
  `promoted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_student_promotion_student` (`student_id`,`promoted_at`),
  KEY `idx_student_promotion_class` (`from_class_id`,`to_class_id`,`promoted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `teacher_class_responsibilities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `section_id` int(11) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_teacher_class_section` (`teacher_id`,`class_id`,`section_id`),
  KEY `idx_teacher_responsibility_scope` (`teacher_id`,`class_id`,`section_id`,`status`),
  CONSTRAINT `fk_teacher_responsibility_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_teacher_responsibility_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_teacher_responsibility_section` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `school_settings` (
  `setting_key` varchar(80) NOT NULL,
  `setting_value` text NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `school_settings` (`setting_key`, `setting_value`) VALUES
('app_name', 'EduCare ERP'),
('app_logo', 'educare-erp-logo.png'),
('login_background', 'educare-erp-login-background.png'),
('school_name', 'Oasis Public School'),
('school_tagline', 'School ERP'),
('school_address', ''),
('school_phone', ''),
('school_email', ''),
('school_logo', ''),
('school_timezone', 'Asia/Kolkata'),
('academic_session', '');

CREATE TABLE `school_portal_schools` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_code` varchar(20) NOT NULL,
  `school_name` varchar(150) NOT NULL,
  `database_name` varchar(64) NOT NULL,
  `status` enum('Provisioning','Active','Inactive','Failed') NOT NULL DEFAULT 'Provisioning',
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_school_portal_code` (`school_code`),
  UNIQUE KEY `uq_school_portal_database` (`database_name`),
  KEY `idx_school_portal_status` (`status`,`school_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `school_portal_schools` (`id`, `school_code`, `school_name`, `database_name`, `status`, `is_primary`)
SELECT 1, 'OASIS', COALESCE(NULLIF(MAX(CASE WHEN `setting_key`='school_name' THEN `setting_value` END), ''), 'Oasis Public School'), DATABASE(), 'Active', 1
FROM `school_settings`;

--
-- Table structure for table `student_attendance`
--

CREATE TABLE `student_attendance` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `status` enum('Present','Absent','Late','Half Day','Leave') NOT NULL DEFAULT 'Present',
  `remarks` varchar(500) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
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
-- Indexes for table `teachers`
--
ALTER TABLE `teachers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_teachers_employee_code` (`employee_code`),
  ADD KEY `idx_teachers_status_name` (`status`,`full_name`);

--
-- Indexes for table `exam_types`
--
ALTER TABLE `exam_types`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_exam_types_status` (`status`);

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
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `admission_no` (`admission_no`),
  ADD KEY `idx_students_class_section_status` (`class_id`,`section_id`,`status`),
  ADD KEY `idx_students_house` (`house_id`);

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
-- Indexes for table `student_attendance`
--
ALTER TABLE `student_attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_student_attendance_date` (`student_id`,`attendance_date`),
  ADD KEY `idx_attendance_date_status` (`attendance_date`,`status`),
  ADD KEY `idx_attendance_created_by` (`created_by`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `teachers`
--
ALTER TABLE `teachers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `exam_types`
--
ALTER TABLE `exam_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

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
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT for table `student_attendance`
--
ALTER TABLE `student_attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

-- Core academic tables omitted from earlier versions of this dump.
CREATE TABLE IF NOT EXISTS `academic_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `session_name` varchar(20) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `subjects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subject_name` varchar(100) NOT NULL,
  `subject_code` varchar(20) DEFAULT NULL,
  `subject_type` enum('Scholastic','Co-Scholastic','Practical') DEFAULT 'Scholastic',
  `status` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `class_subjects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `role` enum('Main','Grading','Additional') NOT NULL DEFAULT 'Main',
  `is_optional` tinyint(1) DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_class_subject` (`class_id`,`subject_id`),
  KEY `fk_cs_subject` (`subject_id`),
  CONSTRAINT `fk_cs_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_cs_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `exams` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `exam_name` varchar(100) NOT NULL,
  `exam_type_id` int(11) NOT NULL,
  `session_id` int(11) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_exam_type` (`exam_type_id`),
  KEY `fk_exam_session` (`session_id`),
  CONSTRAINT `fk_exam_session` FOREIGN KEY (`session_id`) REFERENCES `academic_sessions` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_exam_type` FOREIGN KEY (`exam_type_id`) REFERENCES `exam_types` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `marks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `section_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `exam_id` int(11) NOT NULL,
  `max_marks` decimal(5,2) NOT NULL DEFAULT 100.00,
  `pa_marks` decimal(5,2) NOT NULL DEFAULT 0.00,
  `se_marks` decimal(5,2) NOT NULL DEFAULT 0.00,
  `nb_marks` decimal(5,2) NOT NULL DEFAULT 0.00,
  `written_marks` decimal(5,2) NOT NULL DEFAULT 0.00,
  `obtained_marks` decimal(5,2) NOT NULL DEFAULT 0.00,
  `grade` varchar(10) DEFAULT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `entry_updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_marks` (`student_id`,`subject_id`,`exam_id`),
  KEY `fk_marks_class` (`class_id`),
  KEY `fk_marks_section` (`section_id`),
  KEY `fk_marks_subject` (`subject_id`),
  KEY `fk_marks_exam` (`exam_id`),
  CONSTRAINT `fk_marks_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_marks_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_marks_section` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_marks_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_marks_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `fk_students_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_students_section` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_students_house` FOREIGN KEY (`house_id`) REFERENCES `houses` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);

--
-- Constraints for table `student_attendance`
--
ALTER TABLE `student_attendance`
  ADD CONSTRAINT `fk_student_attendance_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_student_attendance_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL;

--
-- Constraints for table `hpc_observations`
--
ALTER TABLE `hpc_observations`
  ADD CONSTRAINT `fk_hpc_observation_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT;

-- EduCare portal features (also maintained in migrations/019_educare_features.sql).
ALTER TABLE `users`
  MODIFY `account_type` enum('Admin','Teacher','Student','Parent') NOT NULL DEFAULT 'Admin';

ALTER TABLE `students`
  ADD COLUMN IF NOT EXISTS `additional_subject_id` int(11) DEFAULT NULL,
  ADD KEY `idx_students_additional_subject` (`additional_subject_id`),
  ADD CONSTRAINT `fk_students_additional_subject` FOREIGN KEY (`additional_subject_id`) REFERENCES `subjects` (`id`) ON UPDATE CASCADE ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS `parent_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_name` varchar(150) NOT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_parent_account_user` (`user_id`),
  KEY `idx_parent_account_mobile` (`mobile`),
  CONSTRAINT `fk_parent_account_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `parent_students` (
  `parent_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`parent_id`,`student_id`),
  KEY `idx_parent_students_student` (`student_id`),
  CONSTRAINT `fk_parent_students_parent` FOREIGN KEY (`parent_id`) REFERENCES `parent_accounts` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_parent_students_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `role_feature_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `account_type` enum('Teacher','Student') NOT NULL,
  `feature_key` varchar(60) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 0,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_role_feature` (`account_type`,`feature_key`),
  KEY `idx_role_feature_enabled` (`account_type`,`enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `study_resources` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `resource_type` enum('Study Material','Syllabus','Time Table','Date Sheet') NOT NULL,
  `class_id` int(11) DEFAULT NULL,
  `section_id` int(11) DEFAULT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `session_id` int(11) DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `stored_name` varchar(100) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_by_type` enum('Admin','Teacher') NOT NULL DEFAULT 'Admin',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_study_resources_type_session` (`resource_type`,`session_id`,`created_at`),
  KEY `idx_study_resources_class_section` (`class_id`,`section_id`,`resource_type`),
  KEY `idx_study_resources_subject` (`subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `grading_scale_bands` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `grade` varchar(10) NOT NULL,
  `minimum_percent` decimal(5,2) NOT NULL,
  `maximum_percent` decimal(5,2) NOT NULL,
  `description` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_grading_scale_class_grade` (`class_id`,`grade`),
  KEY `idx_grading_scale_range` (`class_id`,`minimum_percent`,`maximum_percent`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `portal_content_attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `announcement_id` int(11) DEFAULT NULL,
  `homework_id` int(11) DEFAULT NULL,
  `stored_name` varchar(100) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_portal_attachment_announcement` (`announcement_id`),
  KEY `idx_portal_attachment_homework` (`homework_id`),
  CONSTRAINT `fk_portal_attachment_announcement` FOREIGN KEY (`announcement_id`) REFERENCES `portal_announcements` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_portal_attachment_homework` FOREIGN KEY (`homework_id`) REFERENCES `portal_homework` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
