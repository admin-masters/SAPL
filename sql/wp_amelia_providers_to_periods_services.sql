-- rewind begin page null
CREATE TABLE `wp_amelia_providers_to_periods_services` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `periodId` int(11) NOT NULL,
  `serviceId` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
INSERT INTO `wp_amelia_providers_to_periods_services` (`id`,`periodId`,`serviceId`) VALUES('35','85','6');
INSERT INTO `wp_amelia_providers_to_periods_services` (`id`,`periodId`,`serviceId`) VALUES('36','86','6');
INSERT INTO `wp_amelia_providers_to_periods_services` (`id`,`periodId`,`serviceId`) VALUES('37','87','6');
INSERT INTO `wp_amelia_providers_to_periods_services` (`id`,`periodId`,`serviceId`) VALUES('38','88','6');
INSERT INTO `wp_amelia_providers_to_periods_services` (`id`,`periodId`,`serviceId`) VALUES('39','89','6');
INSERT INTO `wp_amelia_providers_to_periods_services` (`id`,`periodId`,`serviceId`) VALUES('40','90','6');
INSERT INTO `wp_amelia_providers_to_periods_services` (`id`,`periodId`,`serviceId`) VALUES('41','91','6');
-- rewind end page null
