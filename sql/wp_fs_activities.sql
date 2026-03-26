-- rewind begin page null
CREATE TABLE `wp_fs_activities` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `person_id` bigint(20) DEFAULT NULL,
  `person_type` varchar(192) DEFAULT NULL,
  `event_type` varchar(192) DEFAULT NULL,
  `object_id` bigint(20) DEFAULT NULL,
  `object_type` varchar(192) DEFAULT NULL,
  `description` mediumtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
-- rewind end page null
