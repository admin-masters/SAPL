-- rewind begin page null
CREATE TABLE `wp_amelia_notifications_sms_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `notificationId` int(11) NOT NULL,
  `userId` int(11) DEFAULT NULL,
  `appointmentId` int(11) DEFAULT NULL,
  `eventId` int(11) DEFAULT NULL,
  `packageCustomerId` int(11) DEFAULT NULL,
  `logId` int(11) DEFAULT NULL,
  `dateTime` datetime DEFAULT NULL,
  `text` varchar(1600) NOT NULL,
  `phone` varchar(63) NOT NULL,
  `alphaSenderId` varchar(11) NOT NULL,
  `status` enum('prepared','accepted','queued','sent','failed','delivered','undelivered') NOT NULL DEFAULT 'prepared',
  `price` double DEFAULT NULL,
  `segments` tinyint(2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
-- rewind end page null
