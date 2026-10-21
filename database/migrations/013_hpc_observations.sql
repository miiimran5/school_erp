-- Store each holistic progress-card observation separately by term.
-- The repository's consolidated schema does not define academic_sessions;
-- student records are linked to the actual students primary key.
CREATE TABLE IF NOT EXISTS `hpc_observations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `session_id` int(11) NOT NULL DEFAULT 1,
  `category` varchar(100) NOT NULL,
  `observation` varchar(150) NOT NULL,
  `term1_value` varchar(10) DEFAULT NULL,
  `term2_value` varchar(10) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hpc_observation_student` (`student_id`,`session_id`,`category`,`observation`),
  KEY `idx_hpc_observation_session` (`session_id`,`student_id`),
  CONSTRAINT `fk_hpc_observation_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
