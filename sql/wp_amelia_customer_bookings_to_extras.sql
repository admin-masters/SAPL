-- rewind begin page null
CREATE TABLE `wp_amelia_customer_bookings_to_extras` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customerBookingId` int(11) NOT NULL,
  `extraId` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` double NOT NULL,
  `tax` varchar(255) DEFAULT NULL,
  `aggregatedPrice` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bookingExtra` (`customerBookingId`,`extraId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
-- rewind end page null
