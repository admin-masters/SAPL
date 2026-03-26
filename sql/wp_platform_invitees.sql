-- rewind begin page null
CREATE TABLE `wp_platform_invitees` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` bigint(20) unsigned NOT NULL,
  `email` varchar(191) NOT NULL,
  `name` varchar(191) DEFAULT NULL,
  `invited_via` varchar(20) NOT NULL DEFAULT 'calendar',
  `token_hash` varchar(64) DEFAULT NULL,
  `mailed_at` datetime DEFAULT NULL,
  `opened_at` datetime DEFAULT NULL,
  `request_id` bigint(20) unsigned NOT NULL DEFAULT 0,
  `token_expires` datetime DEFAULT NULL,
  `open_count` int(10) unsigned NOT NULL DEFAULT 0,
  `last_open_ip` varchar(64) DEFAULT NULL,
  `last_open_ua` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_booking` (`booking_id`),
  KEY `idx_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
INSERT INTO `wp_platform_invitees` (`id`,`booking_id`,`email`,`name`,`invited_via`,`token_hash`,`mailed_at`,`opened_at`,`request_id`,`token_expires`,`open_count`,`last_open_ip`,`last_open_ua`,`created_at`,`updated_at`) VALUES('1','0','9923103294@mail.jiit.ac.in','','calendar','15fc318a0698128f71183f08b704c5e3a330d195c99fcab0758ea5448023f3fe',NULL,NULL,'78','2026-02-24 11:50:02','0',NULL,NULL,'2025-12-26 17:20:02',NULL);
-- rewind end page null
