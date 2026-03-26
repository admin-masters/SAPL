-- rewind begin page null
CREATE TABLE `wp_fs_data_metrix` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `stat_date` date NOT NULL,
  `data_type` varchar(100) DEFAULT 'agent_stat',
  `agent_id` bigint(20) unsigned DEFAULT NULL,
  `replies` int(11) unsigned DEFAULT 0,
  `active_tickets` int(11) unsigned DEFAULT 0,
  `resolved_tickets` int(11) unsigned DEFAULT 0,
  `new_tickets` int(11) unsigned DEFAULT 0,
  `unassigned_tickets` int(11) unsigned DEFAULT 0,
  `close_to_average` int(11) unsigned DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
-- rewind end page null
