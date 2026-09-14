-- Class-specific subject assignment and result role.
-- Safe to run repeatedly on existing installations.

CREATE TABLE IF NOT EXISTS `class_subjects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `role` enum('Main','Grading','Additional') NOT NULL DEFAULT 'Main',
  `is_optional` tinyint(1) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_class_subject` (`class_id`,`subject_id`),
  KEY `idx_class_subject_status` (`class_id`,`status`,`role`),
  KEY `idx_class_subject_subject` (`subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `class_subjects`
  ADD COLUMN IF NOT EXISTS `role` enum('Main','Grading','Additional') NOT NULL DEFAULT 'Main' AFTER `subject_id`,
  ADD COLUMN IF NOT EXISTS `status` tinyint(1) NOT NULL DEFAULT 1 AFTER `is_optional`,
  ADD COLUMN IF NOT EXISTS `sort_order` int(11) NOT NULL DEFAULT 0 AFTER `status`;

UPDATE class_subjects cs
INNER JOIN classes c ON c.id = cs.class_id
INNER JOIN subjects s ON s.id = cs.subject_id
SET cs.role = CASE
  WHEN UPPER(TRIM(s.subject_name)) = 'PHYSICAL EDUCATION' AND c.class_order >= 14 THEN 'Main'
  WHEN UPPER(TRIM(s.subject_name)) = 'PHYSICAL EDUCATION' THEN 'Grading'
  WHEN UPPER(TRIM(s.subject_name)) IN ('COMPUTER', 'COMPUTER SCIENCE', 'URDU', 'GK', 'G.K', 'G.K.') THEN 'Grading'
  ELSE cs.role
END;
