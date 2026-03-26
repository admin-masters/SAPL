-- rewind begin page null
CREATE TABLE `wp_wpda_app_container` (
  `cnt_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cnt_dbs` varchar(128) NOT NULL,
  `cnt_tbl` varchar(128) NOT NULL,
  `cnt_cls` longtext NOT NULL,
  `cnt_title` varchar(200) NOT NULL,
  `app_id` bigint(20) unsigned NOT NULL,
  `cnt_seq_nr` smallint(5) unsigned NOT NULL,
  `cnt_table` longtext DEFAULT NULL,
  `cnt_form` longtext DEFAULT NULL,
  `cnt_rform` longtext DEFAULT NULL,
  `cnt_relation` longtext DEFAULT NULL,
  `cnt_chart` longtext DEFAULT NULL,
  `cnt_map` longtext DEFAULT NULL,
  `cnt_query` longtext DEFAULT NULL,
  PRIMARY KEY (`cnt_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
-- rewind end page null
