-- rewind begin page null
CREATE TABLE `wp_wc_customer_lookup` (
  `customer_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `username` varchar(60) NOT NULL DEFAULT '',
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `date_last_active` timestamp NULL DEFAULT NULL,
  `date_registered` timestamp NULL DEFAULT NULL,
  `country` char(2) NOT NULL DEFAULT '',
  `postcode` varchar(20) NOT NULL DEFAULT '',
  `city` varchar(100) NOT NULL DEFAULT '',
  `state` varchar(100) NOT NULL DEFAULT '',
  PRIMARY KEY (`customer_id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
INSERT INTO `wp_wc_customer_lookup` (`customer_id`,`user_id`,`username`,`first_name`,`last_name`,`email`,`date_last_active`,`date_registered`,`country`,`postcode`,`city`,`state`) VALUES('2','237520611','sanyamjain','SANYAM','JAIN','sanyam.jain@inditech.co.in','2025-12-13 13:25:53','2025-10-13 12:18:13','MO','124001','Rohtak','Haryana/India');
INSERT INTO `wp_wc_customer_lookup` (`customer_id`,`user_id`,`username`,`first_name`,`last_name`,`email`,`date_last_active`,`date_registered`,`country`,`postcode`,`city`,`state`) VALUES('4','237520613','Manjeet','Manjeet','Godara','manjeet.godara@inditech.co.in','2025-12-18 12:45:46','2025-11-01 07:18:29','IN','125047','Fatehabad','HR');
INSERT INTO `wp_wc_customer_lookup` (`customer_id`,`user_id`,`username`,`first_name`,`last_name`,`email`,`date_last_active`,`date_registered`,`country`,`postcode`,`city`,`state`) VALUES('5',NULL,'','Manjeet','Godara','Manjeetgodara88020@gmail.com','2025-11-01 07:56:14',NULL,'IN','125047','Fatehabad','HR');
INSERT INTO `wp_wc_customer_lookup` (`customer_id`,`user_id`,`username`,`first_name`,`last_name`,`email`,`date_last_active`,`date_registered`,`country`,`postcode`,`city`,`state`) VALUES('6','237520615','Expert 1','Expert 1','','mypc88020@gmail.com','2026-01-20 17:36:33','2025-11-05 11:15:46','','','','');
INSERT INTO `wp_wc_customer_lookup` (`customer_id`,`user_id`,`username`,`first_name`,`last_name`,`email`,`date_last_active`,`date_registered`,`country`,`postcode`,`city`,`state`) VALUES('8',NULL,'','sanyam','jain','jain78790@gmail.com','2025-11-29 11:42:17',NULL,'IN','124001','Rohtak','HR');
INSERT INTO `wp_wc_customer_lookup` (`customer_id`,`user_id`,`username`,`first_name`,`last_name`,`email`,`date_last_active`,`date_registered`,`country`,`postcode`,`city`,`state`) VALUES('10','237520670','eqfbdsv','Sanyam','Jain','9923103294@mail.jiit.ac.in','2026-03-20 10:04:24','2025-12-19 08:36:07','','','','');
INSERT INTO `wp_wc_customer_lookup` (`customer_id`,`user_id`,`username`,`first_name`,`last_name`,`email`,`date_last_active`,`date_registered`,`country`,`postcode`,`city`,`state`) VALUES('11','237520623','ritika','ritika','dutta','ritika.dutta@inditech.co.in','2025-12-22 13:36:57','2025-12-17 07:17:02','','','','');
INSERT INTO `wp_wc_customer_lookup` (`customer_id`,`user_id`,`username`,`first_name`,`last_name`,`email`,`date_last_active`,`date_registered`,`country`,`postcode`,`city`,`state`) VALUES('12','237520699','jain78790@gmail.com','','','jain78790@gmail.com','2026-03-17 11:24:51','2025-12-22 14:45:03','','','','');
-- rewind end page null
