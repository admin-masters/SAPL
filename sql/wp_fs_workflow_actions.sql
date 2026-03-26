-- rewind begin page null
CREATE TABLE `wp_fs_workflow_actions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(192) DEFAULT NULL,
  `action_name` varchar(192) DEFAULT NULL,
  `workflow_id` bigint(20) DEFAULT NULL,
  `settings` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
-- rewind end page null
