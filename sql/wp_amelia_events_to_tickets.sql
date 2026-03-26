-- rewind begin page null
CREATE TABLE `wp_amelia_events_to_tickets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `eventId` bigint(20) NOT NULL,
  `enabled` tinyint(1) DEFAULT 1,
  `name` varchar(255) NOT NULL,
  `price` double DEFAULT 0,
  `dateRanges` text DEFAULT NULL,
  `spots` int(11) NOT NULL,
  `waitingListSpots` int(11) NOT NULL,
  `translations` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
INSERT INTO `wp_amelia_events_to_tickets` (`id`,`eventId`,`enabled`,`name`,`price`,`dateRanges`,`spots`,`waitingListSpots`,`translations`) VALUES('1','11','1','','0','[]','1','0',NULL);
-- rewind end page null
