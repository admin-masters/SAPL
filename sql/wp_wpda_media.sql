-- rewind begin page null
CREATE TABLE `wp_wpda_media` (
  `media_schema_name` varchar(64) NOT NULL DEFAULT '',
  `media_table_name` varchar(64) NOT NULL,
  `media_column_name` varchar(64) NOT NULL,
  `media_type` enum('Image','ImageURL','Attachment','Hyperlink','Audio','Video') DEFAULT NULL,
  `media_activated` enum('Yes','No') DEFAULT NULL,
  PRIMARY KEY (`media_schema_name`,`media_table_name`,`media_column_name`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
-- rewind end page null
