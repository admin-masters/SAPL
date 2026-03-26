-- rewind begin page null
CREATE TABLE `wp_wpforms_logs` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `message` longtext NOT NULL,
  `types` varchar(255) NOT NULL,
  `create_at` datetime NOT NULL,
  `form_id` bigint(20) DEFAULT NULL,
  `entry_id` bigint(20) DEFAULT NULL,
  `user_id` bigint(20) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
INSERT INTO `wp_wpforms_logs` (`id`,`title`,`message`,`types`,`create_at`,`form_id`,`entry_id`,`user_id`) VALUES('1','Migration','Migration of WPForms to 1.9.8.2 is fully completed.','log','2025-10-23 09:54:50','0','0','0');
INSERT INTO `wp_wpforms_logs` (`id`,`title`,`message`,`types`,`create_at`,`form_id`,`entry_id`,`user_id`) VALUES('2','Migration','Migration of WPForms to 1.9.8.4 is fully completed.','log','2025-11-07 04:48:23','0','0','0');
INSERT INTO `wp_wpforms_logs` (`id`,`title`,`message`,`types`,`create_at`,`form_id`,`entry_id`,`user_id`) VALUES('3','Migration','Migration of WPForms to 1.9.8.6 started.','log','2025-12-12 18:43:49','0','0','0');
INSERT INTO `wp_wpforms_logs` (`id`,`title`,`message`,`types`,`create_at`,`form_id`,`entry_id`,`user_id`) VALUES('4','Migration','Migration of WPForms to 1.9.8.6 completed.','log','2025-12-12 18:43:49','0','0','0');
INSERT INTO `wp_wpforms_logs` (`id`,`title`,`message`,`types`,`create_at`,`form_id`,`entry_id`,`user_id`) VALUES('5','Migration','Migration of WPForms to 1.9.8.7 is fully completed.','log','2025-12-12 18:43:49','0','0','0');
INSERT INTO `wp_wpforms_logs` (`id`,`title`,`message`,`types`,`create_at`,`form_id`,`entry_id`,`user_id`) VALUES('6','Migration','Migration of WPForms to 1.9.9.2 is fully completed.','log','2026-01-29 19:09:27','0','0','0');
INSERT INTO `wp_wpforms_logs` (`id`,`title`,`message`,`types`,`create_at`,`form_id`,`entry_id`,`user_id`) VALUES('7','Migration','Migration of WPForms to 1.9.9.3 is fully completed.','log','2026-02-24 14:00:55','0','0','0');
INSERT INTO `wp_wpforms_logs` (`id`,`title`,`message`,`types`,`create_at`,`form_id`,`entry_id`,`user_id`) VALUES('8','Migration','Migration of WPForms to 1.9.9.4 is fully completed.','log','2026-03-03 20:14:08','0','0','0');
INSERT INTO `wp_wpforms_logs` (`id`,`title`,`message`,`types`,`create_at`,`form_id`,`entry_id`,`user_id`) VALUES('9','Migration','Migration of WPForms to 1.10.0.1 is fully completed.','log','2026-03-19 14:44:49','0','0','0');
-- rewind end page null
