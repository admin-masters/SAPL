-- rewind begin page null
CREATE TABLE `wp_amelia_providers_to_locations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `userId` int(11) NOT NULL,
  `locationId` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=49 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
INSERT INTO `wp_amelia_providers_to_locations` (`id`,`userId`,`locationId`) VALUES('1','6','1');
INSERT INTO `wp_amelia_providers_to_locations` (`id`,`userId`,`locationId`) VALUES('2','1','1');
INSERT INTO `wp_amelia_providers_to_locations` (`id`,`userId`,`locationId`) VALUES('40','60','1');
INSERT INTO `wp_amelia_providers_to_locations` (`id`,`userId`,`locationId`) VALUES('47','71','1');
INSERT INTO `wp_amelia_providers_to_locations` (`id`,`userId`,`locationId`) VALUES('48','72','1');
-- rewind end page null
