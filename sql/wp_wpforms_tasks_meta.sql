-- rewind begin page null
CREATE TABLE `wp_wpforms_tasks_meta` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `action` varchar(255) NOT NULL,
  `data` longtext NOT NULL,
  `date` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=107 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
INSERT INTO `wp_wpforms_tasks_meta` (`id`,`action`,`data`,`date`) VALUES('1','wpforms_process_forms_locator_scan','W10=','2025-10-13 17:37:41');
INSERT INTO `wp_wpforms_tasks_meta` (`id`,`action`,`data`,`date`) VALUES('2','wpforms_process_purge_spam','W10=','2025-10-13 17:37:41');
INSERT INTO `wp_wpforms_tasks_meta` (`id`,`action`,`data`,`date`) VALUES('62','wpforms_admin_addons_cache_update','W10=','2025-12-27 12:29:42');
-- rewind end page null
