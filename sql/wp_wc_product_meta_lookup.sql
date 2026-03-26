-- rewind begin page null
CREATE TABLE `wp_wc_product_meta_lookup` (
  `product_id` bigint(20) NOT NULL,
  `sku` varchar(100) DEFAULT '',
  `global_unique_id` varchar(100) DEFAULT '',
  `virtual` tinyint(1) DEFAULT 0,
  `downloadable` tinyint(1) DEFAULT 0,
  `min_price` decimal(19,4) DEFAULT NULL,
  `max_price` decimal(19,4) DEFAULT NULL,
  `onsale` tinyint(1) DEFAULT 0,
  `stock_quantity` double DEFAULT NULL,
  `stock_status` varchar(100) DEFAULT 'instock',
  `rating_count` bigint(20) DEFAULT 0,
  `average_rating` decimal(3,2) DEFAULT 0.00,
  `total_sales` bigint(20) DEFAULT 0,
  `tax_status` varchar(100) DEFAULT 'taxable',
  `tax_class` varchar(100) DEFAULT '',
  PRIMARY KEY (`product_id`),
  KEY `virtual` (`virtual`),
  KEY `downloadable` (`downloadable`),
  KEY `stock_status` (`stock_status`),
  KEY `stock_quantity` (`stock_quantity`),
  KEY `onsale` (`onsale`),
  KEY `min_max_price` (`min_price`,`max_price`),
  KEY `sku` (`sku`(50))
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
INSERT INTO `wp_wc_product_meta_lookup` (`product_id`,`sku`,`global_unique_id`,`virtual`,`downloadable`,`min_price`,`max_price`,`onsale`,`stock_quantity`,`stock_status`,`rating_count`,`average_rating`,`total_sales`,`tax_status`,`tax_class`) VALUES('129','','','1','0','10.0000','10.0000','0',NULL,'instock','0','0.00','2','taxable','');
INSERT INTO `wp_wc_product_meta_lookup` (`product_id`,`sku`,`global_unique_id`,`virtual`,`downloadable`,`min_price`,`max_price`,`onsale`,`stock_quantity`,`stock_status`,`rating_count`,`average_rating`,`total_sales`,`tax_status`,`tax_class`) VALUES('218','','','1','0','200.0000','200.0000','0',NULL,'instock','0','0.00','3','taxable','');
INSERT INTO `wp_wc_product_meta_lookup` (`product_id`,`sku`,`global_unique_id`,`virtual`,`downloadable`,`min_price`,`max_price`,`onsale`,`stock_quantity`,`stock_status`,`rating_count`,`average_rating`,`total_sales`,`tax_status`,`tax_class`) VALUES('234','','','1','0','0.0000','0.0000','0',NULL,'instock','0','0.00','47','','');
INSERT INTO `wp_wc_product_meta_lookup` (`product_id`,`sku`,`global_unique_id`,`virtual`,`downloadable`,`min_price`,`max_price`,`onsale`,`stock_quantity`,`stock_status`,`rating_count`,`average_rating`,`total_sales`,`tax_status`,`tax_class`) VALUES('238','','','1','0','0.0000','0.0000','0',NULL,'instock','0','0.00','1','taxable','');
INSERT INTO `wp_wc_product_meta_lookup` (`product_id`,`sku`,`global_unique_id`,`virtual`,`downloadable`,`min_price`,`max_price`,`onsale`,`stock_quantity`,`stock_status`,`rating_count`,`average_rating`,`total_sales`,`tax_status`,`tax_class`) VALUES('248','','','1','0','200.0000','200.0000','0',NULL,'instock','0','0.00','0','taxable','');
INSERT INTO `wp_wc_product_meta_lookup` (`product_id`,`sku`,`global_unique_id`,`virtual`,`downloadable`,`min_price`,`max_price`,`onsale`,`stock_quantity`,`stock_status`,`rating_count`,`average_rating`,`total_sales`,`tax_status`,`tax_class`) VALUES('1427','','','1','0','450.0000','450.0000','0',NULL,'instock','0','0.00','1','taxable','');
INSERT INTO `wp_wc_product_meta_lookup` (`product_id`,`sku`,`global_unique_id`,`virtual`,`downloadable`,`min_price`,`max_price`,`onsale`,`stock_quantity`,`stock_status`,`rating_count`,`average_rating`,`total_sales`,`tax_status`,`tax_class`) VALUES('1446','','','1','0','100.0000','100.0000','0',NULL,'instock','0','0.00','0','taxable','');
INSERT INTO `wp_wc_product_meta_lookup` (`product_id`,`sku`,`global_unique_id`,`virtual`,`downloadable`,`min_price`,`max_price`,`onsale`,`stock_quantity`,`stock_status`,`rating_count`,`average_rating`,`total_sales`,`tax_status`,`tax_class`) VALUES('1502','','','1','0','499.0000','499.0000','0',NULL,'instock','0','0.00','5','taxable','');
-- rewind end page null
