-- rewind begin page null
CREATE TABLE `wp_fs_products` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `source_uid` bigint(20) unsigned DEFAULT NULL,
  `mailbox_id` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(192) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `settings` longtext DEFAULT NULL,
  `source` varchar(100) DEFAULT 'local',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
-- rewind end page null
