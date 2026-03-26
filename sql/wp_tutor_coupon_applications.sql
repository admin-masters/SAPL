-- rewind begin page null
CREATE TABLE `wp_tutor_coupon_applications` (
  `coupon_code` varchar(50) NOT NULL,
  `reference_id` bigint(20) unsigned NOT NULL,
  KEY `coupon_code` (`coupon_code`),
  KEY `reference_id` (`reference_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
-- rewind end page null
