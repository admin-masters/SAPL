-- rewind begin page null
CREATE TABLE `wp_amelia_providers_to_services` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `userId` int(11) NOT NULL,
  `serviceId` int(11) NOT NULL,
  `price` double NOT NULL,
  `minCapacity` int(11) NOT NULL,
  `maxCapacity` int(11) NOT NULL,
  `customPricing` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
INSERT INTO `wp_amelia_providers_to_services` (`id`,`userId`,`serviceId`,`price`,`minCapacity`,`maxCapacity`,`customPricing`) VALUES('8','6','6','1800','1','100','{\"enabled\":\"duration\",\"durations\":{\"1800\":{\"price\":1000,\"rules\":[]},\"5400\":{\"price\":5300,\"rules\":[]}},\"persons\":{},\"periods\":{\"default\":[],\"custom\":[]}}');
INSERT INTO `wp_amelia_providers_to_services` (`id`,`userId`,`serviceId`,`price`,`minCapacity`,`maxCapacity`,`customPricing`) VALUES('24','1','6','1800','1','100','{\"enabled\":\"duration\",\"durations\":{\"1800\":{\"price\":1000,\"rules\":[]},\"5400\":{\"price\":2500,\"rules\":[]}},\"persons\":[],\"periods\":{\"default\":[],\"custom\":[]}}');
INSERT INTO `wp_amelia_providers_to_services` (`id`,`userId`,`serviceId`,`price`,`minCapacity`,`maxCapacity`,`customPricing`) VALUES('49','60','6','1800','1','100','{\"enabled\":\"duration\",\"durations\":{\"1800\":{\"price\":1000,\"rules\":[]},\"5400\":{\"price\":2800,\"rules\":[]}},\"persons\":{},\"periods\":{\"default\":[],\"custom\":[]}}');
INSERT INTO `wp_amelia_providers_to_services` (`id`,`userId`,`serviceId`,`price`,`minCapacity`,`maxCapacity`,`customPricing`) VALUES('50','60','1','500','1','1','{\"enabled\":null,\"durations\":{},\"persons\":{},\"periods\":{\"default\":[],\"custom\":[]}}');
-- rewind end page null
