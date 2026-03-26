-- rewind begin page null
CREATE TABLE `wp_term_relationships` (
  `object_id` bigint(20) unsigned NOT NULL DEFAULT 0,
  `term_taxonomy_id` bigint(20) unsigned NOT NULL DEFAULT 0,
  `term_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`object_id`,`term_taxonomy_id`),
  KEY `term_taxonomy_id` (`term_taxonomy_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
INSERT INTO `wp_term_relationships` (`object_id`,`term_taxonomy_id`,`term_order`) VALUES('1','1','0');
INSERT INTO `wp_term_relationships` (`object_id`,`term_taxonomy_id`,`term_order`) VALUES('4','2','0');
INSERT INTO `wp_term_relationships` (`object_id`,`term_taxonomy_id`,`term_order`) VALUES('129','3','0');
INSERT INTO `wp_term_relationships` (`object_id`,`term_taxonomy_id`,`term_order`) VALUES('129','16','0');
INSERT INTO `wp_term_relationships` (`object_id`,`term_taxonomy_id`,`term_order`) VALUES('171','19','0');
INSERT INTO `wp_term_relationships` (`object_id`,`term_taxonomy_id`,`term_order`) VALUES('174','19','0');
INSERT INTO `wp_term_relationships` (`object_id`,`term_taxonomy_id`,`term_order`) VALUES('174','20','0');
INSERT INTO `wp_term_relationships` (`object_id`,`term_taxonomy_id`,`term_order`) VALUES('181','19','0');
INSERT INTO `wp_term_relationships` (`object_id`,`term_taxonomy_id`,`term_order`) VALUES('196','19','0');
INSERT INTO `wp_term_relationships` (`object_id`,`term_taxonomy_id`,`term_order`) VALUES('199','23','0');
INSERT INTO `wp_term_relationships` (`object_id`,`term_taxonomy_id`,`term_order`) VALUES('199','24','0');
INSERT INTO `wp_term_relationships` (`object_id`,`term_taxonomy_id`,`term_order`) VALUES('200','19','0');
INSERT INTO `wp_term_relationships` (`object_id`,`term_taxonomy_id`,`term_order`) VALUES('202','19','0');
INSERT INTO `wp_term_relationships` (`object_id`,`term_taxonomy_id`,`term_order`) VALUES('204','19','0');
INSERT INTO `wp_term_relationships` (`object_id`,`term_taxonomy_id`,`term_order`) VALUES('205','19','0');
INSERT INTO `wp_term_relationships` (`object_id`,`term_taxonomy_id`,`term_order`) VALUES('218','3','0');
INSERT INTO `wp_term_relationships` (`object_id`,`term_taxonomy_id`,`term_order`) VALUES('218','16','0');
INSERT INTO `wp_term_relationships` (`object_id`,`term_taxonomy_id`,`term_order`) VALUES('234','3','0');
INSERT INTO `wp_term_relationships` (`object_id`,`term_taxonomy_id`,`term_order`) VALUES('234','7','0');
INSERT INTO `wp_term_relationships` (`object_id`,`term_taxonomy_id`,`term_order`) VALUES('234','8','0');
INSERT INTO `wp_term_relationships` (`object_id`,`term_taxonomy_id`,`term_order`) VALUES('238','3','0');
INSERT INTO `wp_term_relationships` (`object_id`,`term_taxonomy_id`,`term_order`) VALUES('238','16','0');
INSERT INTO `wp_term_relationships` (`object_id`,`term_taxonomy_id`,`term_order`) VALUES('248','3','0');
INSERT INTO `wp_term_relationships` (`object_id`,`term_taxonomy_id`,`term_order`) VALUES('248','7','0');
INSERT INTO `wp_term_relationships` (`object_id`,`term_taxonomy_id`,`term_order`) VALUES('248','16','0');
INSERT INTO `wp_term_relationships` (`object_id`,`term_taxonomy_id`,`term_order`) VALUES('1427','3','0');
INSERT INTO `wp_term_relationships` (`object_id`,`term_taxonomy_id`,`term_order`) VALUES('1427','16','0');
INSERT INTO `wp_term_relationships` (`object_id`,`term_taxonomy_id`,`term_order`) VALUES('1446','3','0');
INSERT INTO `wp_term_relationships` (`object_id`,`term_taxonomy_id`,`term_order`) VALUES('1446','7','0');
INSERT INTO `wp_term_relationships` (`object_id`,`term_taxonomy_id`,`term_order`) VALUES('1446','16','0');
INSERT INTO `wp_term_relationships` (`object_id`,`term_taxonomy_id`,`term_order`) VALUES('1502','3','0');
INSERT INTO `wp_term_relationships` (`object_id`,`term_taxonomy_id`,`term_order`) VALUES('1502','16','0');
INSERT INTO `wp_term_relationships` (`object_id`,`term_taxonomy_id`,`term_order`) VALUES('1588','19','0');
INSERT INTO `wp_term_relationships` (`object_id`,`term_taxonomy_id`,`term_order`) VALUES('1588','22','0');
INSERT INTO `wp_term_relationships` (`object_id`,`term_taxonomy_id`,`term_order`) VALUES('2369','1','0');
INSERT INTO `wp_term_relationships` (`object_id`,`term_taxonomy_id`,`term_order`) VALUES('2496','19','0');
-- rewind end page null
