-- rewind begin page null
CREATE TABLE `wp_wpda_table_design` (
  `wpda_table_name` varchar(64) NOT NULL,
  `wpda_schema_name` varchar(64) NOT NULL DEFAULT '',
  `wpda_table_design` text NOT NULL,
  `wpda_date_created` timestamp NULL DEFAULT current_timestamp(),
  `wpda_last_updated` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`wpda_schema_name`,`wpda_table_name`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
-- rewind end page null
