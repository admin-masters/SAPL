-- rewind begin page null
CREATE TABLE `wp_platform_calendar_map` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `amelia_booking_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `role` varchar(32) NOT NULL DEFAULT 'student',
  `gcal_event_id` varchar(128) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  `source` varchar(50) NOT NULL,
  `object_id` bigint(20) NOT NULL,
  `google_event_id` varchar(255) DEFAULT NULL,
  `zoom_url` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_booking` (`amelia_booking_id`),
  KEY `idx_user` (`user_id`),
  KEY `source_object` (`source`,`object_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
-- rewind end page null
