-- rewind begin page null
CREATE TABLE `wp_wpda_app_apps` (
  `app_id` bigint(20) unsigned NOT NULL,
  `app_id_detail` bigint(20) unsigned NOT NULL,
  `seq_nr` smallint(5) unsigned NOT NULL,
  `app_settings` longtext DEFAULT NULL,
  PRIMARY KEY (`app_id`,`app_id_detail`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
-- rewind end page null
