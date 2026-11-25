-- Run after migration 016 and any school-specific schema migrations.
-- This default script migrates from school_erp into school_erp_control.
-- If ERP_DB_NAME or ERP_CONTROL_DB are customized, replace those two names below.

CREATE DATABASE IF NOT EXISTS `school_erp_control`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `school_erp_control`.`school_portal_schools` (
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

CREATE TABLE IF NOT EXISTS `school_erp_control`.`super_admin_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_super_admin_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `school_erp_control`.`school_portal_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `school_erp_control`.`school_settings` LIKE `school_erp`.`school_settings`;

INSERT IGNORE INTO `school_erp_control`.`school_settings` (`setting_key`,`setting_value`)
SELECT `setting_key`,`setting_value`
FROM `school_erp`.`school_settings`
WHERE `setting_key` IN ('app_name','app_logo','login_background','school_timezone');

INSERT IGNORE INTO `school_erp_control`.`school_settings` (`setting_key`,`setting_value`)
VALUES ('school_name','Super Admin'),('school_tagline','Super Admin Portal');

INSERT IGNORE INTO `school_erp_control`.`school_portal_schools`
  (`id`,`school_code`,`school_name`,`database_name`,`status`,`is_primary`,`created_at`)
SELECT `id`,`school_code`,`school_name`,`database_name`,`status`,0,`created_at`
FROM `school_erp`.`school_portal_schools`;

UPDATE `school_erp_control`.`school_portal_schools`
SET `is_primary`=0
WHERE `database_name`='school_erp';

UPDATE `school_erp`.`school_portal_schools`
SET `is_primary`=0
WHERE `database_name`='school_erp';

INSERT IGNORE INTO `school_erp_control`.`super_admin_accounts`
  (`id`,`full_name`,`username`,`password_hash`,`status`,`created_at`)
SELECT `id`,`full_name`,`username`,`password`,`status`,`created_at`
FROM `school_erp`.`users`
WHERE `role_id`=1;

INSERT IGNORE INTO `school_erp_control`.`school_portal_settings` (`setting_key`,`setting_value`)
VALUES ('template_database','school_erp');

ALTER TABLE `school_erp_control`.`school_portal_schools` AUTO_INCREMENT=1;
ALTER TABLE `school_erp_control`.`super_admin_accounts` AUTO_INCREMENT=1;
