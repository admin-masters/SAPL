-- rewind begin page null
CREATE TABLE `wp_wpmailsmtp_tasks_meta` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `action` varchar(255) NOT NULL,
  `data` longtext NOT NULL,
  `date` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
INSERT INTO `wp_wpmailsmtp_tasks_meta` (`id`,`action`,`data`,`date`) VALUES('1','wp_mail_smtp_admin_notifications_update','W10=','2025-10-13 17:30:14');
-- rewind end page null
