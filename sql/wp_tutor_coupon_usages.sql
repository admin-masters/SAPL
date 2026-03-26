-- rewind begin page null
CREATE TABLE `wp_tutor_coupon_usages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `coupon_code` varchar(50) NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `coupon_code` (`coupon_code`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
-- rewind end page null
