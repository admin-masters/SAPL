-- rewind begin page null
CREATE TABLE `wp_platform_payouts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_item_id` bigint(20) unsigned DEFAULT NULL,
  `amelia_booking_id` bigint(20) unsigned DEFAULT NULL,
  `expert_user_id` bigint(20) unsigned NOT NULL,
  `amount_gross` decimal(10,2) NOT NULL DEFAULT 0.00,
  `fee_platform` decimal(10,2) NOT NULL DEFAULT 0.00,
  `amount_net` decimal(10,2) NOT NULL DEFAULT 0.00,
  `month_key` char(7) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'open',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_expert_month` (`expert_user_id`,`month_key`)
) ENGINE=InnoDB AUTO_INCREMENT=143 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
INSERT INTO `wp_platform_payouts` (`id`,`order_item_id`,`amelia_booking_id`,`expert_user_id`,`amount_gross`,`fee_platform`,`amount_net`,`month_key`,`status`,`created_at`,`updated_at`) VALUES('1',NULL,'61','237520698','1800.00','0.00','1800.00','2025-12','approved','2026-03-09 11:35:59','2026-03-09 11:35:59');
INSERT INTO `wp_platform_payouts` (`id`,`order_item_id`,`amelia_booking_id`,`expert_user_id`,`amount_gross`,`fee_platform`,`amount_net`,`month_key`,`status`,`created_at`,`updated_at`) VALUES('2',NULL,'62','237520698','1800.00','0.00','1800.00','2025-12','approved','2026-03-09 11:35:59','2026-03-09 11:35:59');
INSERT INTO `wp_platform_payouts` (`id`,`order_item_id`,`amelia_booking_id`,`expert_user_id`,`amount_gross`,`fee_platform`,`amount_net`,`month_key`,`status`,`created_at`,`updated_at`) VALUES('3',NULL,'63','237520698','0.00','0.00','0.00','2025-12','approved','2026-03-09 11:35:59','2026-03-09 11:35:59');
INSERT INTO `wp_platform_payouts` (`id`,`order_item_id`,`amelia_booking_id`,`expert_user_id`,`amount_gross`,`fee_platform`,`amount_net`,`month_key`,`status`,`created_at`,`updated_at`) VALUES('4',NULL,'88','237520698','1800.00','0.00','1800.00','2026-01','approved','2026-03-09 11:35:59','2026-03-09 11:35:59');
INSERT INTO `wp_platform_payouts` (`id`,`order_item_id`,`amelia_booking_id`,`expert_user_id`,`amount_gross`,`fee_platform`,`amount_net`,`month_key`,`status`,`created_at`,`updated_at`) VALUES('5',NULL,'89','237520698','500.00','0.00','500.00','2026-01','approved','2026-03-09 11:35:59','2026-03-09 11:35:59');
INSERT INTO `wp_platform_payouts` (`id`,`order_item_id`,`amelia_booking_id`,`expert_user_id`,`amount_gross`,`fee_platform`,`amount_net`,`month_key`,`status`,`created_at`,`updated_at`) VALUES('6',NULL,'90','237520698','1800.00','0.00','1800.00','2026-01','approved','2026-03-09 11:35:59','2026-03-09 11:35:59');
INSERT INTO `wp_platform_payouts` (`id`,`order_item_id`,`amelia_booking_id`,`expert_user_id`,`amount_gross`,`fee_platform`,`amount_net`,`month_key`,`status`,`created_at`,`updated_at`) VALUES('7',NULL,'91','237520698','1800.00','0.00','1800.00','2026-01','approved','2026-03-09 11:35:59','2026-03-09 11:35:59');
INSERT INTO `wp_platform_payouts` (`id`,`order_item_id`,`amelia_booking_id`,`expert_user_id`,`amount_gross`,`fee_platform`,`amount_net`,`month_key`,`status`,`created_at`,`updated_at`) VALUES('8',NULL,'96','237520698','500.00','0.00','500.00','2026-02','pending','2026-03-09 11:35:59','2026-03-09 11:35:59');
INSERT INTO `wp_platform_payouts` (`id`,`order_item_id`,`amelia_booking_id`,`expert_user_id`,`amount_gross`,`fee_platform`,`amount_net`,`month_key`,`status`,`created_at`,`updated_at`) VALUES('9',NULL,'97','237520698','500.00','0.00','500.00','2026-02','approved','2026-03-09 11:35:59','2026-03-09 11:35:59');
INSERT INTO `wp_platform_payouts` (`id`,`order_item_id`,`amelia_booking_id`,`expert_user_id`,`amount_gross`,`fee_platform`,`amount_net`,`month_key`,`status`,`created_at`,`updated_at`) VALUES('10',NULL,'98','237520698','1800.00','0.00','1800.00','2026-02','approved','2026-03-09 11:35:59','2026-03-09 11:35:59');
INSERT INTO `wp_platform_payouts` (`id`,`order_item_id`,`amelia_booking_id`,`expert_user_id`,`amount_gross`,`fee_platform`,`amount_net`,`month_key`,`status`,`created_at`,`updated_at`) VALUES('11',NULL,'99','237520698','1800.00','0.00','1800.00','2026-02','approved','2026-03-09 11:35:59','2026-03-09 11:35:59');
INSERT INTO `wp_platform_payouts` (`id`,`order_item_id`,`amelia_booking_id`,`expert_user_id`,`amount_gross`,`fee_platform`,`amount_net`,`month_key`,`status`,`created_at`,`updated_at`) VALUES('12',NULL,'100','237520698','2500.00','0.00','2500.00','2026-02','approved','2026-03-09 11:35:59','2026-03-09 11:35:59');
INSERT INTO `wp_platform_payouts` (`id`,`order_item_id`,`amelia_booking_id`,`expert_user_id`,`amount_gross`,`fee_platform`,`amount_net`,`month_key`,`status`,`created_at`,`updated_at`) VALUES('13',NULL,'101','237520698','1800.00','0.00','1800.00','2026-02','approved','2026-03-09 11:35:59','2026-03-09 11:35:59');
INSERT INTO `wp_platform_payouts` (`id`,`order_item_id`,`amelia_booking_id`,`expert_user_id`,`amount_gross`,`fee_platform`,`amount_net`,`month_key`,`status`,`created_at`,`updated_at`) VALUES('14',NULL,'102','237520698','1800.00','0.00','1800.00','2026-02','approved','2026-03-09 11:35:59','2026-03-09 11:35:59');
INSERT INTO `wp_platform_payouts` (`id`,`order_item_id`,`amelia_booking_id`,`expert_user_id`,`amount_gross`,`fee_platform`,`amount_net`,`month_key`,`status`,`created_at`,`updated_at`) VALUES('15',NULL,'103','237520698','1000.00','0.00','1000.00','2026-02','approved','2026-03-09 11:35:59','2026-03-09 11:35:59');
INSERT INTO `wp_platform_payouts` (`id`,`order_item_id`,`amelia_booking_id`,`expert_user_id`,`amount_gross`,`fee_platform`,`amount_net`,`month_key`,`status`,`created_at`,`updated_at`) VALUES('16',NULL,'104','237520698','1800.00','0.00','1800.00','2026-02','approved','2026-03-09 11:35:59','2026-03-09 11:35:59');
INSERT INTO `wp_platform_payouts` (`id`,`order_item_id`,`amelia_booking_id`,`expert_user_id`,`amount_gross`,`fee_platform`,`amount_net`,`month_key`,`status`,`created_at`,`updated_at`) VALUES('17',NULL,'105','237520698','1800.00','0.00','1800.00','2026-02','approved','2026-03-09 11:35:59','2026-03-09 11:35:59');
INSERT INTO `wp_platform_payouts` (`id`,`order_item_id`,`amelia_booking_id`,`expert_user_id`,`amount_gross`,`fee_platform`,`amount_net`,`month_key`,`status`,`created_at`,`updated_at`) VALUES('18',NULL,'106','237520698','500.00','0.00','500.00','2026-03','approved','2026-03-09 11:35:59','2026-03-09 11:35:59');
-- rewind end page null
