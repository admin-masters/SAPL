-- rewind begin page null
CREATE TABLE `wp_jetpack_sync_queue` (
  `ID` bigint(20) NOT NULL AUTO_INCREMENT,
  `queue_id` varchar(50) NOT NULL,
  `event_id` varchar(100) NOT NULL,
  `event_payload` longtext NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`ID`),
  KEY `event_id` (`event_id`),
  KEY `queue_id` (`queue_id`),
  KEY `queue_id_event_id` (`queue_id`,`event_id`),
  KEY `timestamp` (`timestamp`)
) ENGINE=InnoDB AUTO_INCREMENT=18233 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
-- rewind end page null
