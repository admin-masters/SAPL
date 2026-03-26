-- rewind begin page null
CREATE TABLE `wp_wc_product_download_directories` (
  `url_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `url` varchar(256) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`url_id`),
  KEY `url` (`url`(191))
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
INSERT INTO `wp_wc_product_download_directories` (`url_id`,`url`,`enabled`) VALUES('1','file:///srv/htdocs/wp-content/uploads/woocommerce_uploads/','1');
INSERT INTO `wp_wc_product_download_directories` (`url_id`,`url`,`enabled`) VALUES('2','https://staging-68a5-inditechsites.wpcomstaging.com/wp-content/uploads/woocommerce_uploads/','1');
-- rewind end page null
