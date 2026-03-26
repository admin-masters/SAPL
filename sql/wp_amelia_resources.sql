-- rewind begin page null
CREATE TABLE `wp_amelia_resources` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT '',
  `quantity` int(11) DEFAULT 1,
  `shared` enum('service','location') DEFAULT NULL,
  `status` enum('hidden','visible','disabled') NOT NULL DEFAULT 'visible',
  `countAdditionalPeople` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
-- rewind end page null
