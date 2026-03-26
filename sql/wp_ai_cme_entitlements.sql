-- rewind begin page null
CREATE TABLE `wp_ai_cme_entitlements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `module_id` bigint(20) unsigned NOT NULL,
  `source` varchar(20) NOT NULL,
  `purchased_at` datetime NOT NULL,
  `expires_at` datetime DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_module` (`user_id`,`module_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
-- rewind end page null
