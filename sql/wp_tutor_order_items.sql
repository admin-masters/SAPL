-- rewind begin page null
CREATE TABLE `wp_tutor_order_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned NOT NULL,
  `item_id` bigint(20) unsigned NOT NULL,
  `regular_price` decimal(13,2) NOT NULL,
  `sale_price` varchar(13) DEFAULT NULL,
  `discount_price` varchar(13) DEFAULT NULL,
  `coupon_code` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `item_id` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
-- rewind end page null
