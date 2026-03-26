-- rewind begin page null
CREATE TABLE `wp_amelia_taxes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_bin NOT NULL,
  `amount` double NOT NULL,
  `type` enum('percentage','fixed') NOT NULL,
  `status` enum('hidden','visible') NOT NULL,
  `allServices` tinyint(1) NOT NULL DEFAULT 0,
  `allEvents` tinyint(1) NOT NULL DEFAULT 0,
  `allPackages` tinyint(1) NOT NULL DEFAULT 0,
  `allExtras` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
-- rewind end page null
