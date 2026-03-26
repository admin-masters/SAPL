-- rewind begin page null
CREATE TABLE `wp_wpda_logging` (
  `log_time` datetime NOT NULL,
  `log_id` varchar(50) NOT NULL,
  `log_type` enum('FATAL','ERROR','WARN','INFO','DEBUG','TRACE') DEFAULT NULL,
  `log_msg` varchar(4096) DEFAULT NULL,
  PRIMARY KEY (`log_time`,`log_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
-- rewind end page null
