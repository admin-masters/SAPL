-- rewind begin page null
CREATE TABLE `wp_tutor_earnings` (
  `earning_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) DEFAULT NULL,
  `course_id` bigint(20) DEFAULT NULL,
  `order_id` bigint(20) DEFAULT NULL,
  `order_status` varchar(50) DEFAULT NULL,
  `course_price_total` decimal(16,2) DEFAULT NULL,
  `course_price_grand_total` decimal(16,2) DEFAULT NULL,
  `instructor_amount` decimal(16,2) DEFAULT NULL,
  `instructor_rate` decimal(16,2) DEFAULT NULL,
  `admin_amount` decimal(16,2) DEFAULT NULL,
  `admin_rate` decimal(16,2) DEFAULT NULL,
  `commission_type` varchar(20) DEFAULT NULL,
  `deduct_fees_amount` decimal(16,2) DEFAULT NULL,
  `deduct_fees_name` varchar(250) DEFAULT NULL,
  `deduct_fees_type` varchar(20) DEFAULT NULL,
  `process_by` varchar(20) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`earning_id`),
  KEY `user_id` (`user_id`),
  KEY `course_id` (`course_id`),
  KEY `order_id` (`order_id`),
  KEY `process_by` (`process_by`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
INSERT INTO `wp_tutor_earnings` (`earning_id`,`user_id`,`course_id`,`order_id`,`order_status`,`course_price_total`,`course_price_grand_total`,`instructor_amount`,`instructor_rate`,`admin_amount`,`admin_rate`,`commission_type`,`deduct_fees_amount`,`deduct_fees_name`,`deduct_fees_type`,`process_by`,`created_at`) VALUES('1','237520615','1426','1428','wc-failed','450.00','450.00','0.00','0.00','450.00','100.00','percent',NULL,NULL,NULL,'woocommerce','2025-11-10 14:48:49');
INSERT INTO `wp_tutor_earnings` (`earning_id`,`user_id`,`course_id`,`order_id`,`order_status`,`course_price_total`,`course_price_grand_total`,`instructor_amount`,`instructor_rate`,`admin_amount`,`admin_rate`,`commission_type`,`deduct_fees_amount`,`deduct_fees_name`,`deduct_fees_type`,`process_by`,`created_at`) VALUES('2','237520615','1426','1480','wc-checkout-draft','450.00','450.00','0.00','0.00','450.00','100.00','percent',NULL,NULL,NULL,'woocommerce','2025-11-23 21:07:37');
INSERT INTO `wp_tutor_earnings` (`earning_id`,`user_id`,`course_id`,`order_id`,`order_status`,`course_price_total`,`course_price_grand_total`,`instructor_amount`,`instructor_rate`,`admin_amount`,`admin_rate`,`commission_type`,`deduct_fees_amount`,`deduct_fees_name`,`deduct_fees_type`,`process_by`,`created_at`) VALUES('3','237520615','1426','1491','wc-checkout-draft','450.00','450.00','0.00','0.00','450.00','100.00','percent',NULL,NULL,NULL,'woocommerce','2025-11-27 16:58:43');
INSERT INTO `wp_tutor_earnings` (`earning_id`,`user_id`,`course_id`,`order_id`,`order_status`,`course_price_total`,`course_price_grand_total`,`instructor_amount`,`instructor_rate`,`admin_amount`,`admin_rate`,`commission_type`,`deduct_fees_amount`,`deduct_fees_name`,`deduct_fees_type`,`process_by`,`created_at`) VALUES('4','237520615','1426','1525','wc-checkout-draft','450.00','450.00','0.00','0.00','450.00','100.00','percent',NULL,NULL,NULL,'woocommerce','2025-12-06 18:39:42');
INSERT INTO `wp_tutor_earnings` (`earning_id`,`user_id`,`course_id`,`order_id`,`order_status`,`course_price_total`,`course_price_grand_total`,`instructor_amount`,`instructor_rate`,`admin_amount`,`admin_rate`,`commission_type`,`deduct_fees_amount`,`deduct_fees_name`,`deduct_fees_type`,`process_by`,`created_at`) VALUES('5','237520615','1426','1836','cancelled','450.00','450.00','0.00','0.00','450.00','100.00','percent',NULL,NULL,NULL,'woocommerce','2025-12-18 12:40:57');
INSERT INTO `wp_tutor_earnings` (`earning_id`,`user_id`,`course_id`,`order_id`,`order_status`,`course_price_total`,`course_price_grand_total`,`instructor_amount`,`instructor_rate`,`admin_amount`,`admin_rate`,`commission_type`,`deduct_fees_amount`,`deduct_fees_name`,`deduct_fees_type`,`process_by`,`created_at`) VALUES('6','237520615','1426','1838','completed','450.00','450.00','0.00','0.00','450.00','100.00','percent',NULL,NULL,NULL,'woocommerce','2025-12-18 12:46:55');
-- rewind end page null
