-- rewind begin page null
CREATE TABLE `wp_amelia_providers_views` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `userId` int(11) NOT NULL,
  `date` date NOT NULL,
  `views` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
INSERT INTO `wp_amelia_providers_views` (`id`,`userId`,`date`,`views`) VALUES('1','1','2025-10-18','1');
INSERT INTO `wp_amelia_providers_views` (`id`,`userId`,`date`,`views`) VALUES('2','1','2025-10-22','1');
INSERT INTO `wp_amelia_providers_views` (`id`,`userId`,`date`,`views`) VALUES('3','1','2025-10-31','1');
INSERT INTO `wp_amelia_providers_views` (`id`,`userId`,`date`,`views`) VALUES('4','1','2025-11-01','5');
INSERT INTO `wp_amelia_providers_views` (`id`,`userId`,`date`,`views`) VALUES('5','1','2025-11-06','1');
INSERT INTO `wp_amelia_providers_views` (`id`,`userId`,`date`,`views`) VALUES('6','1','2025-11-07','2');
INSERT INTO `wp_amelia_providers_views` (`id`,`userId`,`date`,`views`) VALUES('7','6','2025-12-09','2');
INSERT INTO `wp_amelia_providers_views` (`id`,`userId`,`date`,`views`) VALUES('8','1','2025-12-09','3');
INSERT INTO `wp_amelia_providers_views` (`id`,`userId`,`date`,`views`) VALUES('11','60','2025-12-26','1');
INSERT INTO `wp_amelia_providers_views` (`id`,`userId`,`date`,`views`) VALUES('12','60','2026-01-07','2');
INSERT INTO `wp_amelia_providers_views` (`id`,`userId`,`date`,`views`) VALUES('13','60','2026-02-14','2');
INSERT INTO `wp_amelia_providers_views` (`id`,`userId`,`date`,`views`) VALUES('14','1','2026-03-05','2');
INSERT INTO `wp_amelia_providers_views` (`id`,`userId`,`date`,`views`) VALUES('15','60','2026-03-07','1');
-- rewind end page null
