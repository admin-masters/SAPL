-- rewind begin page null
CREATE TABLE `wp_tutor_notifications` (
  `ID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(255) DEFAULT NULL,
  `title` tinytext DEFAULT NULL,
  `content` text DEFAULT NULL,
  `status` enum('READ','UNREAD') DEFAULT NULL,
  `receiver_id` bigint(20) unsigned DEFAULT NULL,
  `post_id` bigint(20) unsigned DEFAULT NULL,
  `topic_url` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
-- rewind end page null
