CREATE TABLE IF NOT EXISTS `teacher_feature_permissions` (
  `teacher_id` int(11) NOT NULL,
  `feature_key` varchar(60) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 0,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`teacher_id`,`feature_key`),
  KEY `idx_teacher_feature_enabled` (`feature_key`,`enabled`),
  CONSTRAINT `fk_teacher_feature_permission_teacher`
    FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
