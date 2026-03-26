-- rewind begin page null
CREATE TABLE `wp_fs_mail_boxes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(192) NOT NULL,
  `slug` varchar(192) NOT NULL,
  `box_type` varchar(50) DEFAULT 'web',
  `email` varchar(192) NOT NULL,
  `mapped_email` varchar(192) DEFAULT NULL,
  `email_footer` longtext DEFAULT NULL,
  `settings` longtext DEFAULT NULL,
  `avatar` varchar(192) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `is_default` enum('yes','no') DEFAULT 'no',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
INSERT INTO `wp_fs_mail_boxes` (`id`,`name`,`slug`,`box_type`,`email`,`mapped_email`,`email_footer`,`settings`,`avatar`,`created_by`,`is_default`,`created_at`,`updated_at`) VALUES('2','Inditech','inditech-1760453052','web','admin@inditech.co.in','','','a:2:{s:19:\"admin_email_address\";s:20:\"admin@inditech.co.in\";s:5:\"color\";s:7:\"#0CBE7E\";}','','0','yes','2025-10-14 20:14:12','2025-10-14 20:14:49');
-- rewind end page null
