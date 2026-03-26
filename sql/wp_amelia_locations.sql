-- rewind begin page null
CREATE TABLE `wp_amelia_locations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `status` enum('hidden','visible','disabled') NOT NULL DEFAULT 'visible',
  `name` varchar(255) NOT NULL DEFAULT '',
  `description` mediumtext DEFAULT NULL,
  `address` varchar(255) NOT NULL,
  `phone` varchar(63) NOT NULL,
  `latitude` decimal(8,6) NOT NULL,
  `longitude` decimal(9,6) NOT NULL,
  `pictureFullPath` varchar(767) DEFAULT NULL,
  `pictureThumbPath` varchar(767) DEFAULT NULL,
  `pin` mediumtext DEFAULT NULL,
  `translations` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
INSERT INTO `wp_amelia_locations` (`id`,`status`,`name`,`description`,`address`,`phone`,`latitude`,`longitude`,`pictureFullPath`,`pictureThumbPath`,`pin`,`translations`) VALUES('1','visible','Location 1','','','','40.748441','-73.987853',NULL,NULL,'',NULL);
-- rewind end page null
