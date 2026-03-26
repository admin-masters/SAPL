-- rewind begin page null
CREATE TABLE `wp_actionscheduler_groups` (
  `group_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(255) NOT NULL,
  PRIMARY KEY (`group_id`),
  KEY `slug` (`slug`(191))
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
INSERT INTO `wp_actionscheduler_groups` (`group_id`,`slug`) VALUES('1','ActionScheduler');
INSERT INTO `wp_actionscheduler_groups` (`group_id`,`slug`) VALUES('2','');
INSERT INTO `wp_actionscheduler_groups` (`group_id`,`slug`) VALUES('3','action-scheduler-migration');
INSERT INTO `wp_actionscheduler_groups` (`group_id`,`slug`) VALUES('4','woocommerce');
INSERT INTO `wp_actionscheduler_groups` (`group_id`,`slug`) VALUES('5','count');
INSERT INTO `wp_actionscheduler_groups` (`group_id`,`slug`) VALUES('6','fluent-support');
INSERT INTO `wp_actionscheduler_groups` (`group_id`,`slug`) VALUES('7','wp_mail_smtp');
INSERT INTO `wp_actionscheduler_groups` (`group_id`,`slug`) VALUES('8','wpforms');
INSERT INTO `wp_actionscheduler_groups` (`group_id`,`slug`) VALUES('9','woocommerce-db-updates');
INSERT INTO `wp_actionscheduler_groups` (`group_id`,`slug`) VALUES('10','wc-admin-data');
INSERT INTO `wp_actionscheduler_groups` (`group_id`,`slug`) VALUES('11','woocommerce-remote-inbox-engine');
INSERT INTO `wp_actionscheduler_groups` (`group_id`,`slug`) VALUES('12','wc_batch_processes');
-- rewind end page null
