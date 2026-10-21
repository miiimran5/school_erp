-- Restore core lookup tables from the consolidated schema on older installs.
-- Existing rows are preserved; default records are inserted only when their IDs are unused.

CREATE TABLE IF NOT EXISTS `categories` (
  `id` int(11) NOT NULL,
  `category_name` varchar(50) NOT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO `categories` (`id`, `category_name`, `status`) VALUES
(1, 'General', 1),
(2, 'SC', 1),
(3, 'ST', 1),
(4, 'OBC', 1),
(5, 'BC', 1),
(6, 'EWS', 1);

CREATE TABLE IF NOT EXISTS `concessions` (
  `id` int(11) NOT NULL,
  `concession_name` varchar(100) NOT NULL,
  `concession_amount` decimal(10,2) DEFAULT 0.00,
  `status` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO `concessions` (`id`, `concession_name`, `concession_amount`, `status`) VALUES
(1, 'No Concession', 0.00, 1),
(2, 'Sibling Concession', 0.00, 1),
(3, 'Staff Concession', 0.00, 1),
(4, 'Management Concession', 0.00, 1),
(5, 'Other', 0.00, 1);

CREATE TABLE IF NOT EXISTS `games` (
  `id` int(11) NOT NULL,
  `game_name` varchar(100) NOT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO `games` (`id`, `game_name`, `status`) VALUES
(1, 'Cricket', 1),
(2, 'Football', 1),
(3, 'Basketball', 1),
(4, 'Volleyball', 1),
(5, 'Badminton', 1),
(6, 'Kabaddi', 1),
(7, 'Kho-Kho', 1),
(8, 'Athletics', 1);

CREATE TABLE IF NOT EXISTS `religions` (
  `id` int(11) NOT NULL,
  `religion_name` varchar(50) NOT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO `religions` (`id`, `religion_name`, `status`) VALUES
(1, 'Islam', 1),
(2, 'Hindu', 1),
(3, 'Sikh', 1),
(4, 'Christian', 1),
(5, 'Jain', 1),
(6, 'Buddhist', 1),
(7, 'Others', 1);

CREATE TABLE IF NOT EXISTS `transport_routes` (
  `id` int(11) NOT NULL,
  `route_name` varchar(100) NOT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
