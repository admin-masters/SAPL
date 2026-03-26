-- rewind begin page null
CREATE TABLE `wp_tutor_coupons` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `coupon_status` varchar(50) DEFAULT NULL,
  `coupon_type` varchar(100) DEFAULT 'code',
  `coupon_code` varchar(50) NOT NULL,
  `coupon_title` varchar(255) NOT NULL,
  `coupon_description` text DEFAULT NULL,
  `discount_type` enum('percentage','flat') NOT NULL,
  `discount_amount` decimal(13,2) NOT NULL,
  `applies_to` varchar(100) DEFAULT 'all_courses_and_bundles',
  `total_usage_limit` int(10) unsigned DEFAULT NULL,
  `per_user_usage_limit` tinyint(4) unsigned DEFAULT NULL,
  `purchase_requirement` varchar(50) DEFAULT 'no_minimum',
  `purchase_requirement_value` decimal(13,2) DEFAULT NULL,
  `start_date_gmt` datetime NOT NULL,
  `expire_date_gmt` datetime DEFAULT NULL,
  `created_at_gmt` datetime NOT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `updated_at_gmt` datetime DEFAULT NULL,
  `updated_by` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `coupon_code` (`coupon_code`),
  KEY `start_date_gmt` (`start_date_gmt`),
  KEY `expire_date_gmt` (`expire_date_gmt`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
-- rewind end page null
