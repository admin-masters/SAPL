-- rewind begin page null
CREATE TABLE `wp_amelia_services_views` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `serviceId` int(11) NOT NULL,
  `date` date NOT NULL,
  `views` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
INSERT INTO `wp_amelia_services_views` (`id`,`serviceId`,`date`,`views`) VALUES('1','1','2025-10-18','1');
INSERT INTO `wp_amelia_services_views` (`id`,`serviceId`,`date`,`views`) VALUES('3','1','2025-10-31','1');
INSERT INTO `wp_amelia_services_views` (`id`,`serviceId`,`date`,`views`) VALUES('4','1','2025-11-01','4');
INSERT INTO `wp_amelia_services_views` (`id`,`serviceId`,`date`,`views`) VALUES('6','1','2025-11-06','1');
INSERT INTO `wp_amelia_services_views` (`id`,`serviceId`,`date`,`views`) VALUES('7','1','2025-11-07','1');
INSERT INTO `wp_amelia_services_views` (`id`,`serviceId`,`date`,`views`) VALUES('9','6','2025-12-09','2');
INSERT INTO `wp_amelia_services_views` (`id`,`serviceId`,`date`,`views`) VALUES('10','1','2025-12-09','1');
INSERT INTO `wp_amelia_services_views` (`id`,`serviceId`,`date`,`views`) VALUES('16','1','2025-12-26','1');
INSERT INTO `wp_amelia_services_views` (`id`,`serviceId`,`date`,`views`) VALUES('17','1','2026-01-07','2');
INSERT INTO `wp_amelia_services_views` (`id`,`serviceId`,`date`,`views`) VALUES('18','1','2026-02-14','2');
INSERT INTO `wp_amelia_services_views` (`id`,`serviceId`,`date`,`views`) VALUES('19','6','2026-03-05','2');
INSERT INTO `wp_amelia_services_views` (`id`,`serviceId`,`date`,`views`) VALUES('20','1','2026-03-07','1');
-- rewind end page null
