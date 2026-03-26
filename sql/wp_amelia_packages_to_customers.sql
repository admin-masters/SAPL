-- rewind begin page null
CREATE TABLE `wp_amelia_packages_to_customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `packageId` int(11) NOT NULL,
  `customerId` int(11) NOT NULL,
  `price` double NOT NULL,
  `tax` varchar(255) DEFAULT NULL,
  `start` datetime DEFAULT NULL,
  `end` datetime DEFAULT NULL,
  `purchased` datetime NOT NULL,
  `status` enum('approved','pending','canceled','rejected') DEFAULT NULL,
  `bookingsCount` int(5) DEFAULT NULL,
  `couponId` int(11) DEFAULT NULL,
  `token` varchar(10) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
-- rewind end page null
