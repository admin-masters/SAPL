-- rewind begin page null
CREATE TABLE `wp_tutor_ordermeta` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned NOT NULL,
  `meta_key` varchar(255) NOT NULL,
  `meta_value` longtext NOT NULL,
  `created_at_gmt` datetime NOT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `updated_at_gmt` datetime DEFAULT NULL,
  `updated_by` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `meta_key` (`meta_key`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
-- rewind end page null
