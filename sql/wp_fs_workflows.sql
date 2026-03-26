-- rewind begin page null
CREATE TABLE `wp_fs_workflows` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_by` bigint(20) DEFAULT NULL,
  `priority` int(10) DEFAULT 10,
  `title` varchar(192) DEFAULT NULL,
  `trigger_key` varchar(192) DEFAULT NULL,
  `trigger_type` varchar(50) DEFAULT 'manual',
  `settings` longtext DEFAULT NULL,
  `status` varchar(50) DEFAULT 'draft',
  `last_ran_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
-- rewind end page null
