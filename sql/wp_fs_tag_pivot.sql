-- rewind begin page null
CREATE TABLE `wp_fs_tag_pivot` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tag_id` bigint(20) unsigned NOT NULL,
  `source_id` bigint(20) unsigned NOT NULL,
  `source_type` varchar(192) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
INSERT INTO `wp_fs_tag_pivot` (`id`,`tag_id`,`source_id`,`source_type`,`created_at`,`updated_at`) VALUES('1','1','3','',NULL,NULL);
INSERT INTO `wp_fs_tag_pivot` (`id`,`tag_id`,`source_id`,`source_type`,`created_at`,`updated_at`) VALUES('2','2','4','',NULL,NULL);
-- rewind end page null
