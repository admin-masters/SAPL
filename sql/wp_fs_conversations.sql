-- rewind begin page null
CREATE TABLE `wp_fs_conversations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `serial` int(11) unsigned DEFAULT 1,
  `ticket_id` bigint(20) unsigned NOT NULL,
  `person_id` bigint(20) unsigned NOT NULL,
  `conversation_type` varchar(100) DEFAULT 'response',
  `content` longtext DEFAULT NULL,
  `source` varchar(100) DEFAULT 'web',
  `content_hash` varchar(192) DEFAULT NULL,
  `message_id` varchar(192) DEFAULT NULL,
  `is_important` enum('yes','no') DEFAULT 'no',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
INSERT INTO `wp_fs_conversations` (`id`,`serial`,`ticket_id`,`person_id`,`conversation_type`,`content`,`source`,`content_hash`,`message_id`,`is_important`,`created_at`,`updated_at`) VALUES('1','1','3','1','internal_info','SANYAM JAIN assign this ticket to self','web','36daa8013c8befb01552e38761343189',NULL,'no','2025-10-23 16:20:15','2025-10-23 16:20:15');
-- rewind end page null
