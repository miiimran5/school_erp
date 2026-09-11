-- Student attendance migration.
-- Safe to run more than once. Existing attendance data is preserved.

CREATE TABLE IF NOT EXISTS `student_attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `status` enum('Present','Absent','Late','Half Day','Leave') NOT NULL DEFAULT 'Present',
  `remarks` varchar(500) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_student_attendance_date` (`student_id`,`attendance_date`),
  KEY `idx_attendance_date_status` (`attendance_date`,`status`),
  KEY `idx_attendance_created_by` (`created_by`),
  CONSTRAINT `fk_student_attendance_student`
    FOREIGN KEY (`student_id`) REFERENCES `students` (`id`)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,
  CONSTRAINT `fk_student_attendance_user`
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
