-- rewind begin page null
CREATE TABLE `wp_wpda_menus` (
  `menu_id` mediumint(9) NOT NULL AUTO_INCREMENT,
  `menu_schema_name` varchar(64) NOT NULL DEFAULT '',
  `menu_table_name` varchar(64) NOT NULL,
  `menu_name` varchar(100) NOT NULL,
  `menu_slug` varchar(100) NOT NULL,
  `menu_role` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`menu_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
-- rewind end page null
