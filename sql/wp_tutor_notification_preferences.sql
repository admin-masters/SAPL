-- rewind begin page null
CREATE TABLE `wp_tutor_notification_preferences` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `notification_type` varchar(50) NOT NULL,
  `group_name` varchar(50) NOT NULL,
  `trigger_name` varchar(255) NOT NULL,
  `opt_in` tinyint(4) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
-- rewind end page null
