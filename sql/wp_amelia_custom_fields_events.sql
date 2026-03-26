-- rewind begin page null
CREATE TABLE `wp_amelia_custom_fields_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customFieldId` int(11) NOT NULL,
  `eventId` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
-- rewind end page null
