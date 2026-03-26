-- rewind begin page null
CREATE TABLE `wp_amelia_events_tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `eventId` bigint(20) NOT NULL,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
INSERT INTO `wp_amelia_events_tags` (`id`,`eventId`,`name`) VALUES('2','8','Cardiology');
INSERT INTO `wp_amelia_events_tags` (`id`,`eventId`,`name`) VALUES('3','12','Cardiology');
INSERT INTO `wp_amelia_events_tags` (`id`,`eventId`,`name`) VALUES('4','13','Cardiology');
-- rewind end page null
