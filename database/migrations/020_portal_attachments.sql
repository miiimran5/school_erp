CREATE TABLE IF NOT EXISTS `portal_content_attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `announcement_id` int(11) DEFAULT NULL,
  `homework_id` int(11) DEFAULT NULL,
  `stored_name` varchar(100) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_portal_attachment_announcement` (`announcement_id`),
  KEY `idx_portal_attachment_homework` (`homework_id`),
  CONSTRAINT `fk_portal_attachment_announcement` FOREIGN KEY (`announcement_id`) REFERENCES `portal_announcements` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_portal_attachment_homework` FOREIGN KEY (`homework_id`) REFERENCES `portal_homework` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
