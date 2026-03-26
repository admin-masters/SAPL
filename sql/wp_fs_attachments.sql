-- rewind begin page null
CREATE TABLE `wp_fs_attachments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ticket_id` bigint(20) unsigned DEFAULT NULL,
  `person_id` bigint(20) unsigned DEFAULT NULL,
  `conversation_id` bigint(20) unsigned DEFAULT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `file_path` text DEFAULT NULL,
  `full_url` text DEFAULT NULL,
  `settings` text DEFAULT NULL,
  `title` varchar(192) DEFAULT NULL,
  `file_hash` varchar(192) DEFAULT NULL,
  `driver` varchar(100) DEFAULT 'local',
  `status` varchar(100) DEFAULT 'active',
  `file_size` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
-- rewind end page null
