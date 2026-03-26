-- rewind begin page null
CREATE TABLE `wp_amelia_customer_bookings_to_events_periods` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customerBookingId` bigint(20) NOT NULL,
  `eventPeriodId` bigint(20) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bookingEventPeriod` (`customerBookingId`,`eventPeriodId`)
) ENGINE=InnoDB AUTO_INCREMENT=58 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
INSERT INTO `wp_amelia_customer_bookings_to_events_periods` (`id`,`customerBookingId`,`eventPeriodId`) VALUES('1','8','1');
INSERT INTO `wp_amelia_customer_bookings_to_events_periods` (`id`,`customerBookingId`,`eventPeriodId`) VALUES('2','9','1');
INSERT INTO `wp_amelia_customer_bookings_to_events_periods` (`id`,`customerBookingId`,`eventPeriodId`) VALUES('5','37','6');
INSERT INTO `wp_amelia_customer_bookings_to_events_periods` (`id`,`customerBookingId`,`eventPeriodId`) VALUES('6','64','8');
INSERT INTO `wp_amelia_customer_bookings_to_events_periods` (`id`,`customerBookingId`,`eventPeriodId`) VALUES('7','65','8');
INSERT INTO `wp_amelia_customer_bookings_to_events_periods` (`id`,`customerBookingId`,`eventPeriodId`) VALUES('8','66','8');
INSERT INTO `wp_amelia_customer_bookings_to_events_periods` (`id`,`customerBookingId`,`eventPeriodId`) VALUES('9','67','8');
INSERT INTO `wp_amelia_customer_bookings_to_events_periods` (`id`,`customerBookingId`,`eventPeriodId`) VALUES('10','68','8');
INSERT INTO `wp_amelia_customer_bookings_to_events_periods` (`id`,`customerBookingId`,`eventPeriodId`) VALUES('11','69','8');
INSERT INTO `wp_amelia_customer_bookings_to_events_periods` (`id`,`customerBookingId`,`eventPeriodId`) VALUES('12','70','8');
INSERT INTO `wp_amelia_customer_bookings_to_events_periods` (`id`,`customerBookingId`,`eventPeriodId`) VALUES('13','71','8');
INSERT INTO `wp_amelia_customer_bookings_to_events_periods` (`id`,`customerBookingId`,`eventPeriodId`) VALUES('14','72','8');
INSERT INTO `wp_amelia_customer_bookings_to_events_periods` (`id`,`customerBookingId`,`eventPeriodId`) VALUES('15','73','8');
INSERT INTO `wp_amelia_customer_bookings_to_events_periods` (`id`,`customerBookingId`,`eventPeriodId`) VALUES('16','74','8');
INSERT INTO `wp_amelia_customer_bookings_to_events_periods` (`id`,`customerBookingId`,`eventPeriodId`) VALUES('17','75','8');
INSERT INTO `wp_amelia_customer_bookings_to_events_periods` (`id`,`customerBookingId`,`eventPeriodId`) VALUES('18','76','8');
INSERT INTO `wp_amelia_customer_bookings_to_events_periods` (`id`,`customerBookingId`,`eventPeriodId`) VALUES('19','77','9');
INSERT INTO `wp_amelia_customer_bookings_to_events_periods` (`id`,`customerBookingId`,`eventPeriodId`) VALUES('20','78','9');
INSERT INTO `wp_amelia_customer_bookings_to_events_periods` (`id`,`customerBookingId`,`eventPeriodId`) VALUES('21','79','9');
INSERT INTO `wp_amelia_customer_bookings_to_events_periods` (`id`,`customerBookingId`,`eventPeriodId`) VALUES('22','80','9');
INSERT INTO `wp_amelia_customer_bookings_to_events_periods` (`id`,`customerBookingId`,`eventPeriodId`) VALUES('23','81','9');
INSERT INTO `wp_amelia_customer_bookings_to_events_periods` (`id`,`customerBookingId`,`eventPeriodId`) VALUES('24','82','9');
INSERT INTO `wp_amelia_customer_bookings_to_events_periods` (`id`,`customerBookingId`,`eventPeriodId`) VALUES('25','83','9');
INSERT INTO `wp_amelia_customer_bookings_to_events_periods` (`id`,`customerBookingId`,`eventPeriodId`) VALUES('26','84','9');
INSERT INTO `wp_amelia_customer_bookings_to_events_periods` (`id`,`customerBookingId`,`eventPeriodId`) VALUES('27','85','9');
INSERT INTO `wp_amelia_customer_bookings_to_events_periods` (`id`,`customerBookingId`,`eventPeriodId`) VALUES('28','86','9');
INSERT INTO `wp_amelia_customer_bookings_to_events_periods` (`id`,`customerBookingId`,`eventPeriodId`) VALUES('29','87','9');
INSERT INTO `wp_amelia_customer_bookings_to_events_periods` (`id`,`customerBookingId`,`eventPeriodId`) VALUES('30','95','11');
INSERT INTO `wp_amelia_customer_bookings_to_events_periods` (`id`,`customerBookingId`,`eventPeriodId`) VALUES('31','107','12');
INSERT INTO `wp_amelia_customer_bookings_to_events_periods` (`id`,`customerBookingId`,`eventPeriodId`) VALUES('32','108','13');
INSERT INTO `wp_amelia_customer_bookings_to_events_periods` (`id`,`customerBookingId`,`eventPeriodId`) VALUES('33','109','14');
INSERT INTO `wp_amelia_customer_bookings_to_events_periods` (`id`,`customerBookingId`,`eventPeriodId`) VALUES('34','110','16');
INSERT INTO `wp_amelia_customer_bookings_to_events_periods` (`id`,`customerBookingId`,`eventPeriodId`) VALUES('35','111','18');
INSERT INTO `wp_amelia_customer_bookings_to_events_periods` (`id`,`customerBookingId`,`eventPeriodId`) VALUES('36','112','17');
INSERT INTO `wp_amelia_customer_bookings_to_events_periods` (`id`,`customerBookingId`,`eventPeriodId`) VALUES('37','113','15');
INSERT INTO `wp_amelia_customer_bookings_to_events_periods` (`id`,`customerBookingId`,`eventPeriodId`) VALUES('41','117','19');
INSERT INTO `wp_amelia_customer_bookings_to_events_periods` (`id`,`customerBookingId`,`eventPeriodId`) VALUES('42','119','15');
INSERT INTO `wp_amelia_customer_bookings_to_events_periods` (`id`,`customerBookingId`,`eventPeriodId`) VALUES('43','120','17');
INSERT INTO `wp_amelia_customer_bookings_to_events_periods` (`id`,`customerBookingId`,`eventPeriodId`) VALUES('44','121','19');
INSERT INTO `wp_amelia_customer_bookings_to_events_periods` (`id`,`customerBookingId`,`eventPeriodId`) VALUES('45','122','13');
INSERT INTO `wp_amelia_customer_bookings_to_events_periods` (`id`,`customerBookingId`,`eventPeriodId`) VALUES('46','123','13');
INSERT INTO `wp_amelia_customer_bookings_to_events_periods` (`id`,`customerBookingId`,`eventPeriodId`) VALUES('47','124','16');
INSERT INTO `wp_amelia_customer_bookings_to_events_periods` (`id`,`customerBookingId`,`eventPeriodId`) VALUES('48','125','20');
INSERT INTO `wp_amelia_customer_bookings_to_events_periods` (`id`,`customerBookingId`,`eventPeriodId`) VALUES('51','128','22');
INSERT INTO `wp_amelia_customer_bookings_to_events_periods` (`id`,`customerBookingId`,`eventPeriodId`) VALUES('53','130','23');
INSERT INTO `wp_amelia_customer_bookings_to_events_periods` (`id`,`customerBookingId`,`eventPeriodId`) VALUES('54','131','18');
INSERT INTO `wp_amelia_customer_bookings_to_events_periods` (`id`,`customerBookingId`,`eventPeriodId`) VALUES('55','132','16');
INSERT INTO `wp_amelia_customer_bookings_to_events_periods` (`id`,`customerBookingId`,`eventPeriodId`) VALUES('56','133','21');
INSERT INTO `wp_amelia_customer_bookings_to_events_periods` (`id`,`customerBookingId`,`eventPeriodId`) VALUES('57','134','21');
-- rewind end page null
