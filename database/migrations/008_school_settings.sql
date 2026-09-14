-- Per-installation school branding and operational settings.
CREATE TABLE IF NOT EXISTS `school_settings` (
  `setting_key` varchar(80) NOT NULL,
  `setting_value` text NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO school_settings (setting_key, setting_value) VALUES
 ('school_name', 'Oasis Public School'),
 ('school_tagline', 'School ERP'),
 ('school_address', ''),
 ('school_phone', ''),
 ('school_email', ''),
 ('school_logo', ''),
 ('school_timezone', 'Asia/Kolkata'),
 ('academic_session', '')
ON DUPLICATE KEY UPDATE setting_key = VALUES(setting_key);
