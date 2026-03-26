-- rewind begin page null
CREATE TABLE `wp_amelia_coupons_to_packages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `couponId` int(11) NOT NULL,
  `packageId` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
-- rewind end page null
