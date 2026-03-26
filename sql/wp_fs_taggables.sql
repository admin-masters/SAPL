-- rewind begin page null
CREATE TABLE `wp_fs_taggables` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tag_type` varchar(192) DEFAULT NULL,
  `title` varchar(192) NOT NULL,
  `slug` varchar(192) NOT NULL,
  `description` longtext DEFAULT NULL,
  `settings` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
INSERT INTO `wp_fs_taggables` (`id`,`tag_type`,`title`,`slug`,`description`,`settings`,`created_by`,`created_at`,`updated_at`) VALUES('1','ticket_tag','Webinars','webinars',NULL,NULL,'237520612','2025-10-23 16:17:50','2025-10-23 16:17:50');
INSERT INTO `wp_fs_taggables` (`id`,`tag_type`,`title`,`slug`,`description`,`settings`,`created_by`,`created_at`,`updated_at`) VALUES('2','ticket_tag','Tutorials','tutorials',NULL,NULL,'237520612','2025-12-10 13:21:02','2025-12-10 13:21:02');
-- rewind end page null
