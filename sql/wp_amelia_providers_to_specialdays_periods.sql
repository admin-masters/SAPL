-- rewind begin page null
CREATE TABLE `wp_amelia_providers_to_specialdays_periods` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `specialDayId` int(11) NOT NULL,
  `locationId` int(11) DEFAULT NULL,
  `startTime` time NOT NULL,
  `endTime` time NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
-- rewind end page null
