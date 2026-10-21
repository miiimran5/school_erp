-- Seed editable ERP branding defaults without overwriting customized settings.
INSERT INTO `school_settings` (`setting_key`, `setting_value`) VALUES
  ('app_name', 'EduCare ERP'),
  ('app_logo', 'educare-erp-logo.png'),
  ('login_background', 'educare-erp-login-background.png')
ON DUPLICATE KEY UPDATE `setting_key` = VALUES(`setting_key`);
