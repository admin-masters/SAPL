-- rewind begin page null
CREATE TABLE `wp_wc_tax_rate_classes` (
  `tax_rate_class_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL DEFAULT '',
  `slug` varchar(200) NOT NULL DEFAULT '',
  PRIMARY KEY (`tax_rate_class_id`),
  UNIQUE KEY `slug` (`slug`(191))
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
INSERT INTO `wp_wc_tax_rate_classes` (`tax_rate_class_id`,`name`,`slug`) VALUES('1','Reduced rate','reduced-rate');
INSERT INTO `wp_wc_tax_rate_classes` (`tax_rate_class_id`,`name`,`slug`) VALUES('2','Zero rate','zero-rate');
-- rewind end page null
