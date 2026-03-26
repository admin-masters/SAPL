-- rewind begin page null
CREATE TABLE `wp_amelia_events_to_providers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `eventId` bigint(20) NOT NULL,
  `userId` bigint(20) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
INSERT INTO `wp_amelia_events_to_providers` (`id`,`eventId`,`userId`) VALUES('5','1','1');
INSERT INTO `wp_amelia_events_to_providers` (`id`,`eventId`,`userId`) VALUES('7','4','6');
INSERT INTO `wp_amelia_events_to_providers` (`id`,`eventId`,`userId`) VALUES('12','15','60');
INSERT INTO `wp_amelia_events_to_providers` (`id`,`eventId`,`userId`) VALUES('13','16','60');
INSERT INTO `wp_amelia_events_to_providers` (`id`,`eventId`,`userId`) VALUES('14','17','60');
INSERT INTO `wp_amelia_events_to_providers` (`id`,`eventId`,`userId`) VALUES('15','18','60');
INSERT INTO `wp_amelia_events_to_providers` (`id`,`eventId`,`userId`) VALUES('16','20','60');
INSERT INTO `wp_amelia_events_to_providers` (`id`,`eventId`,`userId`) VALUES('17','21','60');
INSERT INTO `wp_amelia_events_to_providers` (`id`,`eventId`,`userId`) VALUES('18','22','60');
INSERT INTO `wp_amelia_events_to_providers` (`id`,`eventId`,`userId`) VALUES('19','23','60');
INSERT INTO `wp_amelia_events_to_providers` (`id`,`eventId`,`userId`) VALUES('20','24','60');
-- rewind end page null
