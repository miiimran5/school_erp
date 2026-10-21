-- Keep an audit trail for every student class promotion.
-- Student and class/section references are intentionally not foreign keys so
-- historical promotion records remain available if a source record is removed.
CREATE TABLE IF NOT EXISTS `student_promotions` (
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
