-- rewind begin page null
CREATE TABLE `wp_ai_cme_credit_ledger` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `delta` int(11) NOT NULL,
  `old_balance` int(11) NOT NULL,
  `new_balance` int(11) NOT NULL,
  `reason` varchar(64) NOT NULL,
  `ref` varchar(191) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_reason` (`reason`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
INSERT INTO `wp_ai_cme_credit_ledger` (`id`,`user_id`,`delta`,`old_balance`,`new_balance`,`reason`,`ref`,`created_at`) VALUES('1','237520614','10','0','10','woo_purchase','order#1505/product#1502','2025-11-27 17:11:58');
INSERT INTO `wp_ai_cme_credit_ledger` (`id`,`user_id`,`delta`,`old_balance`,`new_balance`,`reason`,`ref`,`created_at`) VALUES('2','237520611','10','0','10','woo_purchase','order#1494/product#1502','2025-11-27 17:54:10');
INSERT INTO `wp_ai_cme_credit_ledger` (`id`,`user_id`,`delta`,`old_balance`,`new_balance`,`reason`,`ref`,`created_at`) VALUES('3','237520612','10','0','10','woo_purchase','order#1507/product#1502','2025-11-29 11:48:21');
INSERT INTO `wp_ai_cme_credit_ledger` (`id`,`user_id`,`delta`,`old_balance`,`new_balance`,`reason`,`ref`,`created_at`) VALUES('4','237520611','-1','10','9','ai_return','16df41b0-bf38-456d-893b-4356168bf1ef','2025-12-07 11:12:02');
INSERT INTO `wp_ai_cme_credit_ledger` (`id`,`user_id`,`delta`,`old_balance`,`new_balance`,`reason`,`ref`,`created_at`) VALUES('5','237520611','0','9','9','ai_return','16df41b0-bf38-456d-893b-4356168bf1ef','2025-12-07 11:17:08');
INSERT INTO `wp_ai_cme_credit_ledger` (`id`,`user_id`,`delta`,`old_balance`,`new_balance`,`reason`,`ref`,`created_at`) VALUES('6','237520611','-1','9','8','ai_return','16df41b0-bf38-456d-893b-4356168bf1ef','2025-12-07 11:29:42');
-- rewind end page null
