-- rewind begin page null
CREATE TABLE `wp_tutor_customers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `billing_first_name` varchar(255) NOT NULL,
  `billing_last_name` varchar(255) NOT NULL,
  `billing_email` varchar(255) NOT NULL,
  `billing_phone` varchar(20) NOT NULL,
  `billing_zip_code` varchar(20) NOT NULL,
  `billing_address` text NOT NULL,
  `billing_country` varchar(100) NOT NULL,
  `billing_state` varchar(100) NOT NULL,
  `billing_city` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `billing_email` (`billing_email`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
-- rewind end page null
