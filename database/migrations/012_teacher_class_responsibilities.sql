-- Assign class/section responsibility independently of subject teaching.
CREATE TABLE IF NOT EXISTS `teacher_class_responsibilities` (
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

ALTER TABLE `portal_homework`
  ADD COLUMN IF NOT EXISTS `section_id` int(11) DEFAULT NULL AFTER `class_id`;

ALTER TABLE `portal_announcements`
  ADD COLUMN IF NOT EXISTS `class_id` int(11) DEFAULT NULL AFTER `audience`,
  ADD COLUMN IF NOT EXISTS `section_id` int(11) DEFAULT NULL AFTER `class_id`;
