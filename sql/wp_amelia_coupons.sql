-- rewind begin page null
CREATE TABLE `wp_amelia_coupons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_bin NOT NULL,
  `discount` double NOT NULL,
  `deduction` double NOT NULL,
  `limit` double NOT NULL,
  `customerLimit` double NOT NULL DEFAULT 0,
  `status` enum('hidden','visible') NOT NULL,
  `notificationInterval` int(11) NOT NULL DEFAULT 0,
  `notificationRecurring` tinyint(1) NOT NULL DEFAULT 0,
  `expirationDate` datetime DEFAULT NULL,
  `allServices` tinyint(1) NOT NULL DEFAULT 0,
  `allEvents` tinyint(1) NOT NULL DEFAULT 0,
  `allPackages` tinyint(1) NOT NULL DEFAULT 0,
  `startDate` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
-- rewind end page null
