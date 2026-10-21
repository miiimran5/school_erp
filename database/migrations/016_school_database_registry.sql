-- Central registry for isolated per-school databases.
-- Run this migration in the primary school_erp database.
CREATE TABLE IF NOT EXISTS `school_portal_schools` (
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

INSERT IGNORE INTO `school_portal_schools`
  (`id`, `school_code`, `school_name`, `database_name`, `status`, `is_primary`)
SELECT
  1,
  'OASIS',
  COALESCE(NULLIF(MAX(CASE WHEN `setting_key`='school_name' THEN `setting_value` END), ''), 'Oasis Public School'),
  DATABASE(),
  'Active',
  1
FROM `school_settings`;

ALTER TABLE `school_portal_schools` AUTO_INCREMENT=2;
