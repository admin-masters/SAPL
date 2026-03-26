-- rewind begin page null
CREATE TABLE `wp_amelia_packages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT '',
  `description` mediumtext DEFAULT NULL,
  `color` varchar(255) NOT NULL DEFAULT '',
  `price` double NOT NULL,
  `status` enum('hidden','visible','disabled') NOT NULL DEFAULT 'visible',
  `pictureFullPath` varchar(767) DEFAULT NULL,
  `pictureThumbPath` varchar(767) DEFAULT NULL,
  `position` int(11) DEFAULT 0,
  `calculatedPrice` tinyint(1) DEFAULT 1,
  `discount` double NOT NULL,
  `endDate` datetime DEFAULT NULL,
  `durationType` enum('day','week','month') DEFAULT NULL,
  `durationCount` int(4) DEFAULT NULL,
  `settings` mediumtext DEFAULT NULL,
  `translations` text DEFAULT NULL,
  `depositPayment` enum('disabled','fixed','percentage') DEFAULT 'disabled',
  `deposit` double DEFAULT 0,
  `fullPayment` tinyint(1) DEFAULT 0,
  `sharedCapacity` tinyint(1) DEFAULT 0,
  `quantity` int(11) DEFAULT 1,
  `limitPerCustomer` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
-- rewind end page null
