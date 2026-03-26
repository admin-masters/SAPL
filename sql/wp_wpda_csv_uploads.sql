-- rewind begin page null
CREATE TABLE `wp_wpda_csv_uploads` (
  `csv_id` mediumint(9) NOT NULL AUTO_INCREMENT,
  `csv_name` varchar(100) NOT NULL,
  `csv_real_file_name` varchar(4096) NOT NULL,
  `csv_orig_file_name` varchar(4096) NOT NULL,
  `csv_timestamp` datetime DEFAULT NULL,
  `csv_mapping` text DEFAULT NULL,
  `csv_encoding` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`csv_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
-- rewind end page null
