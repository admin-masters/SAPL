-- rewind begin page null
CREATE TABLE `wp_amelia_galleries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entityId` int(11) NOT NULL,
  `entityType` enum('service','event','package') NOT NULL,
  `pictureFullPath` varchar(767) DEFAULT NULL,
  `pictureThumbPath` varchar(767) DEFAULT NULL,
  `position` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
-- rewind end page null
