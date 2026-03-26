-- rewind begin page null
CREATE TABLE `wp_amelia_providers_to_specialdays_periods_services` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `periodId` int(11) NOT NULL,
  `serviceId` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
-- rewind end page null
