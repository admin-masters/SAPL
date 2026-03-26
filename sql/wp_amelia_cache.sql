-- rewind begin page null
CREATE TABLE `wp_amelia_cache` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `paymentId` int(11) DEFAULT NULL,
  `data` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
INSERT INTO `wp_amelia_cache` (`id`,`name`,`paymentId`,`data`) VALUES('1','dc5380e97e',NULL,'{\"type\":\"event\",\"eventId\":9,\"name\":\"Neuro\",\"couponId\":\"\",\"couponCode\":\"\",\"dateTimeValues\":[{\"start\":\"2025-12-30 11:00:00\",\"end\":\"2025-12-30 12:00:00\"}],\"bookings\":[{\"customerId\":61,\"customer\":{\"email\":\"jain78790@gmail.com\",\"externalId\":null,\"firstName\":\"jain78790@gmail.com\",\"id\":61,\"lastName\":\"Admin\",\"phone\":\"9992431718\",\"countryPhoneIso\":null},\"info\":\"{\\\"firstName\\\":\\\"jain78790@gmail.com\\\",\\\"lastName\\\":\\\"Admin\\\",\\\"phone\\\":\\\"+919992431718\\\",\\\"locale\\\":\\\"en_US\\\",\\\"timeZone\\\":\\\"Asia\\\\\\/Calcutta\\\",\\\"urlParams\\\":null}\",\"persons\":1,\"extras\":[],\"utcOffset\":330,\"customFields\":null,\"deposit\":true,\"ticketsData\":[]}],\"payment\":{\"id\":77,\"customerBookingId\":78,\"packageCustomerId\":null,\"parentId\":null,\"amount\":1000,\"gateway\":\"wc\",\"gatewayTitle\":\"\",\"dateTime\":\"2025-12-29 14:09:21\",\"status\":\"pending\",\"data\":\"\",\"entity\":\"event\",\"created\":null,\"actionsCompleted\":true,\"triggeredActions\":null,\"wcOrderId\":0,\"wcOrderItemId\":0,\"wcOrderUrl\":null,\"wcItemCouponValue\":null,\"wcItemTaxValue\":null,\"transactionId\":null,\"transfers\":null,\"invoiceNumber\":58,\"paymentLinks\":null,\"fromLink\":true,\"fromPanel\":false,\"newPayment\":false},\"locale\":\"en_US\",\"timeZone\":\"Asia\\/Calcutta\",\"recurring\":[],\"package\":[],\"redirectUrl\":\"https:\\/\\/staging-68a5-inditechsites.wpcomstaging.com\",\"wcProductId\":null,\"price\":1000}');
-- rewind end page null
