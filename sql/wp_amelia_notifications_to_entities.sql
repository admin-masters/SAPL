-- rewind begin page null
CREATE TABLE `wp_amelia_notifications_to_entities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `notificationId` int(11) NOT NULL,
  `entityId` int(11) NOT NULL,
  `entity` enum('appointment','event') NOT NULL DEFAULT 'appointment',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
-- rewind end page null
