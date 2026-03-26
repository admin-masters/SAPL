-- rewind begin page null
CREATE TABLE `wp_amelia_resources_to_entities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `resourceId` int(11) NOT NULL,
  `entityId` int(11) NOT NULL,
  `entityType` enum('service','location','employee') NOT NULL DEFAULT 'service',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
-- rewind end page null
