-- rewind begin page null
CREATE TABLE `wp_wpda_table_settings` (
  `wpda_schema_name` varchar(64) NOT NULL DEFAULT '',
  `wpda_table_name` varchar(64) NOT NULL,
  `wpda_table_settings` text NOT NULL,
  PRIMARY KEY (`wpda_schema_name`,`wpda_table_name`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
-- rewind end page null
