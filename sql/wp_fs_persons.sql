-- rewind begin page null
CREATE TABLE `wp_fs_persons` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `first_name` varchar(192) DEFAULT NULL,
  `last_name` varchar(192) DEFAULT NULL,
  `email` varchar(192) DEFAULT NULL,
  `title` varchar(192) DEFAULT NULL,
  `avatar` varchar(192) DEFAULT NULL,
  `person_type` varchar(192) DEFAULT 'customer',
  `status` varchar(192) DEFAULT 'active',
  `ip_address` varchar(20) DEFAULT NULL,
  `last_ip_address` varchar(20) DEFAULT NULL,
  `address_line_1` varchar(192) DEFAULT NULL,
  `address_line_2` varchar(192) DEFAULT NULL,
  `city` varchar(192) DEFAULT NULL,
  `zip` varchar(192) DEFAULT NULL,
  `state` varchar(192) DEFAULT NULL,
  `country` varchar(192) DEFAULT NULL,
  `note` longtext DEFAULT NULL,
  `hash` varchar(192) DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `description` mediumtext DEFAULT NULL,
  `remote_uid` bigint(20) unsigned DEFAULT NULL,
  `last_response_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
INSERT INTO `wp_fs_persons` (`id`,`first_name`,`last_name`,`email`,`title`,`avatar`,`person_type`,`status`,`ip_address`,`last_ip_address`,`address_line_1`,`address_line_2`,`city`,`zip`,`state`,`country`,`note`,`hash`,`user_id`,`description`,`remote_uid`,`last_response_at`,`created_at`,`updated_at`) VALUES('1','SANYAM','JAIN','sanyam.jain@inditech.co.in','',NULL,'agent','active',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'c0d340c87bc9b1c811eec837f5b0d2d3','237520611',NULL,NULL,NULL,'2025-10-13 17:10:59','2025-10-23 13:43:41');
INSERT INTO `wp_fs_persons` (`id`,`first_name`,`last_name`,`email`,`title`,`avatar`,`person_type`,`status`,`ip_address`,`last_ip_address`,`address_line_1`,`address_line_2`,`city`,`zip`,`state`,`country`,`note`,`hash`,`user_id`,`description`,`remote_uid`,`last_response_at`,`created_at`,`updated_at`) VALUES('2','SANYAM','JAIN','sanyam.jain@inditech.co.in',NULL,NULL,'customer','active',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'ee2efcb78a36b4fb228ec1d19b294728',NULL,NULL,NULL,'2025-10-23 12:47:15','2025-10-23 12:47:14','2025-10-23 12:47:15');
INSERT INTO `wp_fs_persons` (`id`,`first_name`,`last_name`,`email`,`title`,`avatar`,`person_type`,`status`,`ip_address`,`last_ip_address`,`address_line_1`,`address_line_2`,`city`,`zip`,`state`,`country`,`note`,`hash`,`user_id`,`description`,`remote_uid`,`last_response_at`,`created_at`,`updated_at`) VALUES('3','sanyam','jain','jain78790@gmail.com','Customer',NULL,'customer','active','','103.167.175.139','sds','sdgafd','Rohtak','124001','Haryana','India','','d9ba12802bba9d2cce90666f198daeae','237520612',NULL,'0','2025-12-10 13:21:02','2025-10-23 13:10:53','2025-12-11 13:00:27');
INSERT INTO `wp_fs_persons` (`id`,`first_name`,`last_name`,`email`,`title`,`avatar`,`person_type`,`status`,`ip_address`,`last_ip_address`,`address_line_1`,`address_line_2`,`city`,`zip`,`state`,`country`,`note`,`hash`,`user_id`,`description`,`remote_uid`,`last_response_at`,`created_at`,`updated_at`) VALUES('5','sanyam','jain','23f2002611@ds.study.iitm.ac.in',NULL,NULL,'customer','active','103.226.203.3','103.226.203.3',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'b8ec0b1914d4d6477167e47f5afcffb0','237520616',NULL,NULL,NULL,'2025-12-10 13:22:36','2025-12-19 22:21:56');
INSERT INTO `wp_fs_persons` (`id`,`first_name`,`last_name`,`email`,`title`,`avatar`,`person_type`,`status`,`ip_address`,`last_ip_address`,`address_line_1`,`address_line_2`,`city`,`zip`,`state`,`country`,`note`,`hash`,`user_id`,`description`,`remote_uid`,`last_response_at`,`created_at`,`updated_at`) VALUES('6','Sanyam','Jain','9923103294@mail.jiit.ac.in',NULL,NULL,'customer','active','103.170.45.141','103.170.45.141',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'3a243d3125ffeffd4b73892d00318268','237520670',NULL,NULL,NULL,'2025-12-19 14:06:24','2026-03-16 20:50:46');
-- rewind end page null
