-- rewind begin page null
CREATE TABLE `wp_term_taxonomy` (
  `term_taxonomy_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `term_id` bigint(20) unsigned NOT NULL DEFAULT 0,
  `taxonomy` varchar(32) NOT NULL DEFAULT '',
  `description` longtext NOT NULL,
  `parent` bigint(20) unsigned NOT NULL DEFAULT 0,
  `count` bigint(20) NOT NULL DEFAULT 0,
  PRIMARY KEY (`term_taxonomy_id`),
  UNIQUE KEY `term_id_taxonomy` (`term_id`,`taxonomy`),
  KEY `taxonomy` (`taxonomy`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
INSERT INTO `wp_term_taxonomy` (`term_taxonomy_id`,`term_id`,`taxonomy`,`description`,`parent`,`count`) VALUES('1','1','category','','0','2');
INSERT INTO `wp_term_taxonomy` (`term_taxonomy_id`,`term_id`,`taxonomy`,`description`,`parent`,`count`) VALUES('2','2','wp_theme','','0','1');
INSERT INTO `wp_term_taxonomy` (`term_taxonomy_id`,`term_id`,`taxonomy`,`description`,`parent`,`count`) VALUES('3','3','product_type','','0','8');
INSERT INTO `wp_term_taxonomy` (`term_taxonomy_id`,`term_id`,`taxonomy`,`description`,`parent`,`count`) VALUES('4','4','product_type','','0','0');
INSERT INTO `wp_term_taxonomy` (`term_taxonomy_id`,`term_id`,`taxonomy`,`description`,`parent`,`count`) VALUES('5','5','product_type','','0','0');
INSERT INTO `wp_term_taxonomy` (`term_taxonomy_id`,`term_id`,`taxonomy`,`description`,`parent`,`count`) VALUES('6','6','product_type','','0','0');
INSERT INTO `wp_term_taxonomy` (`term_taxonomy_id`,`term_id`,`taxonomy`,`description`,`parent`,`count`) VALUES('7','7','product_visibility','','0','3');
INSERT INTO `wp_term_taxonomy` (`term_taxonomy_id`,`term_id`,`taxonomy`,`description`,`parent`,`count`) VALUES('8','8','product_visibility','','0','1');
INSERT INTO `wp_term_taxonomy` (`term_taxonomy_id`,`term_id`,`taxonomy`,`description`,`parent`,`count`) VALUES('9','9','product_visibility','','0','0');
INSERT INTO `wp_term_taxonomy` (`term_taxonomy_id`,`term_id`,`taxonomy`,`description`,`parent`,`count`) VALUES('10','10','product_visibility','','0','0');
INSERT INTO `wp_term_taxonomy` (`term_taxonomy_id`,`term_id`,`taxonomy`,`description`,`parent`,`count`) VALUES('11','11','product_visibility','','0','0');
INSERT INTO `wp_term_taxonomy` (`term_taxonomy_id`,`term_id`,`taxonomy`,`description`,`parent`,`count`) VALUES('12','12','product_visibility','','0','0');
INSERT INTO `wp_term_taxonomy` (`term_taxonomy_id`,`term_id`,`taxonomy`,`description`,`parent`,`count`) VALUES('13','13','product_visibility','','0','0');
INSERT INTO `wp_term_taxonomy` (`term_taxonomy_id`,`term_id`,`taxonomy`,`description`,`parent`,`count`) VALUES('14','14','product_visibility','','0','0');
INSERT INTO `wp_term_taxonomy` (`term_taxonomy_id`,`term_id`,`taxonomy`,`description`,`parent`,`count`) VALUES('15','15','product_visibility','','0','0');
INSERT INTO `wp_term_taxonomy` (`term_taxonomy_id`,`term_id`,`taxonomy`,`description`,`parent`,`count`) VALUES('16','16','product_cat','','0','7');
INSERT INTO `wp_term_taxonomy` (`term_taxonomy_id`,`term_id`,`taxonomy`,`description`,`parent`,`count`) VALUES('17','17','product_type','','0','0');
INSERT INTO `wp_term_taxonomy` (`term_taxonomy_id`,`term_id`,`taxonomy`,`description`,`parent`,`count`) VALUES('18','18','product_type','','0','0');
INSERT INTO `wp_term_taxonomy` (`term_taxonomy_id`,`term_id`,`taxonomy`,`description`,`parent`,`count`) VALUES('19','19','wp_theme','','0','10');
INSERT INTO `wp_term_taxonomy` (`term_taxonomy_id`,`term_id`,`taxonomy`,`description`,`parent`,`count`) VALUES('20','20','wp_template_part_area','','0','1');
INSERT INTO `wp_term_taxonomy` (`term_taxonomy_id`,`term_id`,`taxonomy`,`description`,`parent`,`count`) VALUES('21','21','wp_pattern_category','','0','0');
INSERT INTO `wp_term_taxonomy` (`term_taxonomy_id`,`term_id`,`taxonomy`,`description`,`parent`,`count`) VALUES('22','22','wp_template_part_area','','0','1');
INSERT INTO `wp_term_taxonomy` (`term_taxonomy_id`,`term_id`,`taxonomy`,`description`,`parent`,`count`) VALUES('23','23','wp_theme','','0','1');
INSERT INTO `wp_term_taxonomy` (`term_taxonomy_id`,`term_id`,`taxonomy`,`description`,`parent`,`count`) VALUES('24','24','wp_template_part_area','','0','1');
-- rewind end page null
