-- rewind begin page null
CREATE TABLE `wp_tutor_scheduler` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(50) NOT NULL COMMENT 'Type of schedule, e.g., gift, email, reminder',
  `reference_id` varchar(255) NOT NULL COMMENT 'Unique reference id, token, etc',
  `scheduled_at_gmt` datetime NOT NULL COMMENT 'When the action should be executed',
  `status` varchar(255) NOT NULL DEFAULT 'processing',
  `payload` longtext DEFAULT NULL,
  `created_at_gmt` datetime DEFAULT NULL,
  `updated_at_gmt` datetime DEFAULT NULL,
  `scheduled_by` bigint(20) unsigned DEFAULT NULL COMMENT 'User who scheduled the action',
  `scheduled_for` bigint(20) unsigned DEFAULT NULL COMMENT 'Target user of the scheduled action',
  PRIMARY KEY (`id`),
  KEY `idx_context_status` (`type`,`status`),
  KEY `idx_status` (`status`),
  KEY `idx_scheduled_at_gmt` (`scheduled_at_gmt`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
-- rewind end page null
