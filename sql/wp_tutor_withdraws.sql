-- rewind begin page null
CREATE TABLE `wp_tutor_withdraws` (
  `withdraw_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) DEFAULT NULL,
  `amount` decimal(16,2) DEFAULT NULL,
  `method_data` text DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`withdraw_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
-- rewind end page null
