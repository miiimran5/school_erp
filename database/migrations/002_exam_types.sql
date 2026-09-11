-- Exam type master data. Safe to run repeatedly.

CREATE TABLE IF NOT EXISTS `exam_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `exam_type_name` varchar(100) NOT NULL,
  `max_marks` int(11) DEFAULT 100,
  `status` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_exam_types_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `exam_types` (`exam_type_name`, `max_marks`, `status`)
SELECT 'PA-1', 20, 1 WHERE NOT EXISTS (SELECT 1 FROM `exam_types` WHERE `exam_type_name` = 'PA-1');
INSERT INTO `exam_types` (`exam_type_name`, `max_marks`, `status`)
SELECT 'PA-2', 20, 1 WHERE NOT EXISTS (SELECT 1 FROM `exam_types` WHERE `exam_type_name` = 'PA-2');
INSERT INTO `exam_types` (`exam_type_name`, `max_marks`, `status`)
SELECT 'PA-3', 20, 1 WHERE NOT EXISTS (SELECT 1 FROM `exam_types` WHERE `exam_type_name` = 'PA-3');
INSERT INTO `exam_types` (`exam_type_name`, `max_marks`, `status`)
SELECT 'PA-4', 20, 1 WHERE NOT EXISTS (SELECT 1 FROM `exam_types` WHERE `exam_type_name` = 'PA-4');
INSERT INTO `exam_types` (`exam_type_name`, `max_marks`, `status`)
SELECT 'Term-1', 100, 1 WHERE NOT EXISTS (SELECT 1 FROM `exam_types` WHERE `exam_type_name` = 'Term-1');
INSERT INTO `exam_types` (`exam_type_name`, `max_marks`, `status`)
SELECT 'Term-2', 100, 1 WHERE NOT EXISTS (SELECT 1 FROM `exam_types` WHERE `exam_type_name` = 'Term-2');
