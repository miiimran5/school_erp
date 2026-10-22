-- EduCare resources, configurable portal access, grading, and parent portals.
-- Safe to re-run on MySQL/MariaDB versions supporting ADD COLUMN IF NOT EXISTS.

ALTER TABLE `users`
  MODIFY `account_type` enum('Admin','Teacher','Student','Parent') NOT NULL DEFAULT 'Admin';

ALTER TABLE `students`
  ADD COLUMN IF NOT EXISTS `additional_subject_id` int(11) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `photo` varchar(255) DEFAULT NULL;

ALTER TABLE `portal_announcements`
  ADD COLUMN IF NOT EXISTS `class_id` int(11) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `section_id` int(11) DEFAULT NULL;

ALTER TABLE `portal_homework`
  ADD COLUMN IF NOT EXISTS `section_id` int(11) DEFAULT NULL;

CREATE TABLE IF NOT EXISTS `parent_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_name` varchar(150) NOT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_parent_account_user` (`user_id`),
  KEY `idx_parent_account_mobile` (`mobile`)
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

INSERT IGNORE INTO `grading_scale_bands`
  (`class_id`,`grade`,`minimum_percent`,`maximum_percent`,`description`)
SELECT c.id, bands.grade, bands.minimum_percent, bands.maximum_percent, bands.description
FROM `classes` c
CROSS JOIN (
  SELECT 'A1' AS grade, 91.00 AS minimum_percent, 100.00 AS maximum_percent, 'Outstanding' AS description
  UNION ALL SELECT 'A2', 81.00, 90.99, 'Excellent'
  UNION ALL SELECT 'B1', 71.00, 80.99, 'Very Good'
  UNION ALL SELECT 'B2', 61.00, 70.99, 'Good'
  UNION ALL SELECT 'C1', 51.00, 60.99, 'Satisfactory'
  UNION ALL SELECT 'C2', 41.00, 50.99, 'Needs Improvement'
  UNION ALL SELECT 'D', 33.00, 40.99, 'Basic'
  UNION ALL SELECT 'E', 0.00, 32.99, 'Needs Support'
) bands
WHERE c.status = 1;

ALTER TABLE `students`
  ADD INDEX IF NOT EXISTS `idx_students_additional_subject` (`additional_subject_id`);

DROP PROCEDURE IF EXISTS `educare_apply_feature_constraints_019`;
DELIMITER $$
CREATE PROCEDURE `educare_apply_feature_constraints_019`()
BEGIN
  IF EXISTS (
    SELECT 1 FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'marks'
  ) THEN
    ALTER TABLE `marks`
      ADD COLUMN IF NOT EXISTS `entry_updated_by` int(11) DEFAULT NULL;
  END IF;

  IF EXISTS (
    SELECT 1 FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subjects'
  ) AND NOT EXISTS (
    SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'students'
      AND CONSTRAINT_NAME = 'fk_students_additional_subject'
  ) THEN
    ALTER TABLE `students`
      ADD CONSTRAINT `fk_students_additional_subject`
      FOREIGN KEY (`additional_subject_id`) REFERENCES `subjects` (`id`)
      ON UPDATE CASCADE ON DELETE SET NULL;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'parent_accounts'
      AND CONSTRAINT_NAME = 'fk_parent_account_user'
  ) THEN
    ALTER TABLE `parent_accounts`
      ADD CONSTRAINT `fk_parent_account_user`
      FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
      ON UPDATE CASCADE ON DELETE SET NULL;
  END IF;
END$$
CALL `educare_apply_feature_constraints_019`()$$
DROP PROCEDURE `educare_apply_feature_constraints_019`$$
DELIMITER ;
