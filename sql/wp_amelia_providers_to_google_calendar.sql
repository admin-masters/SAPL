-- rewind begin page null
CREATE TABLE `wp_amelia_providers_to_google_calendar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `userId` int(11) NOT NULL,
  `token` text NOT NULL,
  `calendarId` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
INSERT INTO `wp_amelia_providers_to_google_calendar` (`id`,`userId`,`token`,`calendarId`) VALUES('6','1','{\"access_token\":\"ya29.a0Aa7MYioFp38uBieNEIlCB2I8B36ZxWAJUaKkQFNS8dv1QRZaE24oeoPL9jrI5S5midptIm7k2L0Rr8xGS8U_wrUZGKC1SQIAXD3f2-ewae9Ta0jY-oiLpZL1V00XOjFm1RNnbH0xYUgNMlnpaJAkBuAo4itR53APWvCi7bKFsP6q9oTtcdt4NzrOQWhXbVfYuVwnh7d_JQaCgYKAZwSARUSFQHGX2Mi8LV58AOlUGWMRKPLcETAPw0209\",\"expires_in\":3599,\"scope\":\"https:\\/\\/www.googleapis.com\\/auth\\/calendar\",\"token_type\":\"Bearer\",\"created\":1774263780,\"refresh_token\":\"1\\/\\/0fi8rxD6TPOgwCgYIARAAGA8SNwF-L9Ir4VaP81KHEKHrXnBUtBG6chDCixszC4Xo4l7poYzhjXHIHHaWKSGygEpLGyk3PBC9jfg\"}','sanyam.jain@inditech.co.in');
-- rewind end page null
