-- rewind begin page null
CREATE TABLE `wp_wpda_app` (
  `app_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `app_name` varchar(30) NOT NULL,
  `app_title` varchar(100) NOT NULL,
  `app_type` tinyint(4) NOT NULL,
  `app_settings` longtext DEFAULT NULL,
  `app_theme` longtext DEFAULT NULL,
  `app_add_to_menu` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`app_id`),
  UNIQUE KEY `app_name` (`app_name`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
-- rewind end page null
