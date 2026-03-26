-- rewind begin page null
CREATE TABLE `wp_amelia_custom_fields_options` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customFieldId` int(11) NOT NULL,
  `label` text DEFAULT NULL,
  `position` int(11) NOT NULL,
  `translations` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
-- rewind end page null
