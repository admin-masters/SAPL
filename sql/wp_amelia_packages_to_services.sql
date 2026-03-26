-- rewind begin page null
CREATE TABLE `wp_amelia_packages_to_services` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `serviceId` int(11) NOT NULL,
  `packageId` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `minimumScheduled` int(5) DEFAULT 1,
  `maximumScheduled` int(5) DEFAULT 1,
  `allowProviderSelection` tinyint(1) DEFAULT 1,
  `position` int(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
-- rewind end page null
