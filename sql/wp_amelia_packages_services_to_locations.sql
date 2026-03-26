-- rewind begin page null
CREATE TABLE `wp_amelia_packages_services_to_locations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `packageServiceId` int(11) NOT NULL,
  `locationId` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
-- rewind end page null
