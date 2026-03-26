-- rewind begin page null
CREATE TABLE `wp_amelia_customer_bookings_to_events_tickets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customerBookingId` bigint(20) NOT NULL,
  `eventTicketId` bigint(20) NOT NULL,
  `price` double DEFAULT 0,
  `persons` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
-- rewind end page null
