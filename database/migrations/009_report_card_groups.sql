CREATE TABLE IF NOT EXISTS `report_card_group_subjects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_key` varchar(20) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_report_group_subject` (`group_key`,`subject_id`),
  KEY `idx_report_group_status` (`group_key`,`status`,`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
