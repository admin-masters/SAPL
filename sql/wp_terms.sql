-- rewind begin page null
CREATE TABLE `wp_terms` (
  `term_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL DEFAULT '',
  `slug` varchar(200) NOT NULL DEFAULT '',
  `term_group` bigint(10) NOT NULL DEFAULT 0,
  PRIMARY KEY (`term_id`),
  KEY `slug` (`slug`(191)),
  KEY `name` (`name`(191))
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
INSERT INTO `wp_terms` (`term_id`,`name`,`slug`,`term_group`) VALUES('1','Uncategorized','uncategorized','0');
INSERT INTO `wp_terms` (`term_id`,`name`,`slug`,`term_group`) VALUES('2','twentytwentytwo','twentytwentytwo','0');
INSERT INTO `wp_terms` (`term_id`,`name`,`slug`,`term_group`) VALUES('3','simple','simple','0');
INSERT INTO `wp_terms` (`term_id`,`name`,`slug`,`term_group`) VALUES('4','grouped','grouped','0');
INSERT INTO `wp_terms` (`term_id`,`name`,`slug`,`term_group`) VALUES('5','variable','variable','0');
INSERT INTO `wp_terms` (`term_id`,`name`,`slug`,`term_group`) VALUES('6','external','external','0');
INSERT INTO `wp_terms` (`term_id`,`name`,`slug`,`term_group`) VALUES('7','exclude-from-search','exclude-from-search','0');
INSERT INTO `wp_terms` (`term_id`,`name`,`slug`,`term_group`) VALUES('8','exclude-from-catalog','exclude-from-catalog','0');
INSERT INTO `wp_terms` (`term_id`,`name`,`slug`,`term_group`) VALUES('9','featured','featured','0');
INSERT INTO `wp_terms` (`term_id`,`name`,`slug`,`term_group`) VALUES('10','outofstock','outofstock','0');
INSERT INTO `wp_terms` (`term_id`,`name`,`slug`,`term_group`) VALUES('11','rated-1','rated-1','0');
INSERT INTO `wp_terms` (`term_id`,`name`,`slug`,`term_group`) VALUES('12','rated-2','rated-2','0');
INSERT INTO `wp_terms` (`term_id`,`name`,`slug`,`term_group`) VALUES('13','rated-3','rated-3','0');
INSERT INTO `wp_terms` (`term_id`,`name`,`slug`,`term_group`) VALUES('14','rated-4','rated-4','0');
INSERT INTO `wp_terms` (`term_id`,`name`,`slug`,`term_group`) VALUES('15','rated-5','rated-5','0');
INSERT INTO `wp_terms` (`term_id`,`name`,`slug`,`term_group`) VALUES('16','Uncategorized','uncategorized','0');
INSERT INTO `wp_terms` (`term_id`,`name`,`slug`,`term_group`) VALUES('17','subscription','subscription','0');
INSERT INTO `wp_terms` (`term_id`,`name`,`slug`,`term_group`) VALUES('18','Variable Subscription','variable-subscription','0');
INSERT INTO `wp_terms` (`term_id`,`name`,`slug`,`term_group`) VALUES('19','twentytwentyfour','twentytwentyfour','0');
INSERT INTO `wp_terms` (`term_id`,`name`,`slug`,`term_group`) VALUES('20','header','header','0');
INSERT INTO `wp_terms` (`term_id`,`name`,`slug`,`term_group`) VALUES('21','Menu','menu','0');
INSERT INTO `wp_terms` (`term_id`,`name`,`slug`,`term_group`) VALUES('22','footer','footer','0');
INSERT INTO `wp_terms` (`term_id`,`name`,`slug`,`term_group`) VALUES('23','woocommerce/woocommerce','woocommerce-woocommerce','0');
INSERT INTO `wp_terms` (`term_id`,`name`,`slug`,`term_group`) VALUES('24','add-to-cart-with-options','add-to-cart-with-options','0');
-- rewind end page null
