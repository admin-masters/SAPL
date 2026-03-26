-- rewind begin page null
CREATE TABLE `wp_amelia_locations_views` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `locationId` int(11) NOT NULL,
  `date` date NOT NULL,
  `views` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
INSERT INTO `wp_amelia_locations_views` (`id`,`locationId`,`date`,`views`) VALUES('1','1','2025-12-09','5');
INSERT INTO `wp_amelia_locations_views` (`id`,`locationId`,`date`,`views`) VALUES('2','1','2025-12-18','2');
INSERT INTO `wp_amelia_locations_views` (`id`,`locationId`,`date`,`views`) VALUES('3','1','2025-12-19','2');
INSERT INTO `wp_amelia_locations_views` (`id`,`locationId`,`date`,`views`) VALUES('4','1','2025-12-26','1');
INSERT INTO `wp_amelia_locations_views` (`id`,`locationId`,`date`,`views`) VALUES('5','1','2026-01-07','2');
INSERT INTO `wp_amelia_locations_views` (`id`,`locationId`,`date`,`views`) VALUES('6','1','2026-02-14','2');
INSERT INTO `wp_amelia_locations_views` (`id`,`locationId`,`date`,`views`) VALUES('7','1','2026-03-05','2');
INSERT INTO `wp_amelia_locations_views` (`id`,`locationId`,`date`,`views`) VALUES('8','1','2026-03-07','1');
-- rewind end page null
