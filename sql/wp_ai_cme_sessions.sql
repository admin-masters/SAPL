-- rewind begin page null
CREATE TABLE `wp_ai_cme_sessions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `module_id` bigint(20) unsigned DEFAULT NULL,
  `external_session_id` varchar(191) DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `finished_at` datetime DEFAULT NULL,
  `score` decimal(6,2) DEFAULT NULL,
  `summary_json` longtext DEFAULT NULL,
  `session_id` varchar(64) NOT NULL,
  `ended_at` datetime DEFAULT NULL,
  `credits_before` int(10) unsigned DEFAULT NULL,
  `credits_after` int(10) unsigned DEFAULT NULL,
  `credits_spent` int(11) NOT NULL DEFAULT 0,
  `source` varchar(24) NOT NULL DEFAULT 'return',
  `raw_payload` longtext DEFAULT NULL,
  `payload_out` longtext DEFAULT NULL,
  `payload_in` longtext DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_id` (`session_id`),
  KEY `idx_user_module` (`user_id`,`module_id`),
  KEY `user_id` (`user_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_external` (`external_session_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
INSERT INTO `wp_ai_cme_sessions` (`id`,`user_id`,`module_id`,`external_session_id`,`started_at`,`finished_at`,`score`,`summary_json`,`session_id`,`ended_at`,`credits_before`,`credits_after`,`credits_spent`,`source`,`raw_payload`,`payload_out`,`payload_in`) VALUES('1','237520614','0','c8c1e9b3-7f90-4e45-9f9f-287fcd313a5c','2025-12-06 18:21:38',NULL,NULL,NULL,'',NULL,'10',NULL,'0','return',NULL,'{\"credits\":10,\"email\":\"Manjeetgodara88020@gmail.com\",\"exp\":1765045598,\"iat\":1765045298,\"return_url_get\":\"https://staging-68a5-inditechsites.wpcomstaging.com/ai-cme/return\",\"return_url_post\":\"https://staging-68a5-inditechsites.wpcomstaging.com/wp-json/platform-core/v1/ai-cme/return\",\"uid\":237520614}','{\"status\":\"success\",\"user_id\":\"c8c1e9b3-7f90-4e45-9f9f-287fcd313a5c\",\"message\":\"Launch accepted\"}');
-- rewind end page null
