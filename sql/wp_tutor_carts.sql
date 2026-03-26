-- rewind begin page null
CREATE TABLE `wp_tutor_carts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `coupon_code` varchar(50) DEFAULT NULL,
  `created_at_gmt` datetime NOT NULL,
  `updated_at_gmt` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `coupon_code` (`coupon_code`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
-- rewind end page null
