-- Inquiry, calendar events, extra student fields, and school-leaving records.

ALTER TABLE `students`
  ADD COLUMN IF NOT EXISTS `registration_no` varchar(40) DEFAULT NULL AFTER `admission_no`,
  ADD COLUMN IF NOT EXISTS `previous_school` varchar(150) DEFAULT NULL AFTER `address`,
  ADD COLUMN IF NOT EXISTS `parking` tinyint(1) NOT NULL DEFAULT 0 AFTER `transport`,
  ADD COLUMN IF NOT EXISTS `practical` tinyint(1) NOT NULL DEFAULT 0 AFTER `parking`;

CREATE TABLE IF NOT EXISTS `admission_inquiries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_name` varchar(100) NOT NULL,
  `class_id` int(11) NOT NULL,
  `mobile` varchar(15) NOT NULL,
  `father_name` varchar(100) NOT NULL,
  `address` text DEFAULT NULL,
  `previous_school` varchar(150) DEFAULT NULL,
  `status` enum('Open','Admitted','Closed') NOT NULL DEFAULT 'Open',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_inquiry_class_status` (`class_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `calendar_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_date` date NOT NULL,
  `title` varchar(150) NOT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_calendar_event_date` (`event_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `school_leaving_certificates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `leaving_date` date NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `last_class_id` int(11) DEFAULT NULL,
  `certificate_no` varchar(50) DEFAULT NULL,
  `mark_inactive` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_leaving_student` (`student_id`),
  CONSTRAINT `fk_school_leaving_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `school_settings` (`setting_key`, `setting_value`)
SELECT 'bonafide_certificate_text', 'This is to certify that {gender_title} {student_name}, {gender_pronoun_is} a bonafide student of {school_name}. {gender_pronoun_cap} is studying in Class {class}{section} during the academic session {session}. {gender_possessive_cap} father''s name is {father_name}. Admission / Registration No. of the student is {admission_no}.'
WHERE NOT EXISTS (SELECT 1 FROM school_settings WHERE setting_key='bonafide_certificate_text');

INSERT INTO `school_settings` (`setting_key`, `setting_value`)
SELECT 'leaving_certificate_text', 'This is to certify that {gender_title} {student_name}, {gender_child} of {father_name}, was a student of {school_name}. {gender_pronoun_cap} studied in Class {class}{section} (Admission / Registration No. {admission_no}) and left the school on {leaving_date}. Reason for leaving: {reason}. According to the school records {gender_possessive} date of birth is {dob}.'
WHERE NOT EXISTS (SELECT 1 FROM school_settings WHERE setting_key='leaving_certificate_text');
