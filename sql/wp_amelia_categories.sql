-- rewind begin page null
CREATE TABLE `wp_amelia_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `status` enum('hidden','visible','disabled') NOT NULL DEFAULT 'visible',
  `name` varchar(255) NOT NULL DEFAULT '',
  `position` int(11) NOT NULL,
  `translations` text DEFAULT NULL,
  `color` varchar(255) NOT NULL DEFAULT '#1788FB',
  `pictureFullPath` varchar(767) DEFAULT NULL,
  `pictureThumbPath` varchar(767) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
INSERT INTO `wp_amelia_categories` (`id`,`status`,`name`,`position`,`translations`,`color`,`pictureFullPath`,`pictureThumbPath`) VALUES('1','visible','Services','1',NULL,'#1A84EE',NULL,NULL);
-- rewind end page null
