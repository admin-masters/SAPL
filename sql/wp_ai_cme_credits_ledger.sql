-- rewind begin page null
CREATE TABLE `wp_ai_cme_credits_ledger` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `delta` int(11) NOT NULL,
  `balance_after` int(11) NOT NULL,
  `reason` varchar(100) NOT NULL,
  `session_id` varchar(64) DEFAULT NULL,
  `order_id` bigint(20) unsigned DEFAULT NULL,
  `jti` varchar(64) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `jti` (`jti`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
-- rewind end page null
