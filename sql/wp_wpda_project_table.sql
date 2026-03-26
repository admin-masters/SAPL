-- rewind begin page null
CREATE TABLE `wp_wpda_project_table` (
  `wpda_table_name` varchar(64) NOT NULL,
  `wpda_schema_name` varchar(64) NOT NULL DEFAULT '',
  `wpda_table_setname` varchar(100) NOT NULL DEFAULT 'default',
  `wpda_table_design` longtext NOT NULL,
  PRIMARY KEY (`wpda_schema_name`,`wpda_table_name`,`wpda_table_setname`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
-- rewind end page null
