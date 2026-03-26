-- rewind begin page null
CREATE TABLE `wp_tutorial_uploads` (
  `id` mediumint(9) NOT NULL AUTO_INCREMENT,
  `appointment_id` int(11) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `material_type` varchar(100) NOT NULL,
  `upload_date` datetime NOT NULL DEFAULT current_timestamp(),
  `file_name` varchar(255) NOT NULL,
  `file_url` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
INSERT INTO `wp_tutorial_uploads` (`id`,`appointment_id`,`user_id`,`file_path`,`material_type`,`upload_date`,`file_name`,`file_url`,`description`) VALUES('1','5','237520618','','Presentation Slides','2025-12-26 18:27:52','Screenshot 2025-12-15 163648.png','https://staging-68a5-inditechsites.wpcomstaging.com/wp-content/uploads/tutorial_materials/1766753872_Screenshot_2025_12_15_163648.png','ok');
INSERT INTO `wp_tutorial_uploads` (`id`,`appointment_id`,`user_id`,`file_path`,`material_type`,`upload_date`,`file_name`,`file_url`,`description`) VALUES('2','5','237520618','','Assignment / Homework','2025-12-26 18:29:17','Screenshot 2025-12-15 163648.png','https://staging-68a5-inditechsites.wpcomstaging.com/wp-content/uploads/tutorial_materials/1766753957_Screenshot_2025_12_15_163648.png','pp');
INSERT INTO `wp_tutorial_uploads` (`id`,`appointment_id`,`user_id`,`file_path`,`material_type`,`upload_date`,`file_name`,`file_url`,`description`) VALUES('3','5','237520618','','Assignment / Homework','2025-12-26 18:31:49','Screenshot 2025-12-15 163648.png','https://staging-68a5-inditechsites.wpcomstaging.com/wp-content/uploads/tutorial_materials/1766754109_Screenshot_2025_12_15_163648.png','pp');
INSERT INTO `wp_tutorial_uploads` (`id`,`appointment_id`,`user_id`,`file_path`,`material_type`,`upload_date`,`file_name`,`file_url`,`description`) VALUES('4','5','237520618','','Presentation Slides','2025-12-26 18:32:29','Screenshot 2025-12-15 163648.png','https://staging-68a5-inditechsites.wpcomstaging.com/wp-content/uploads/tutorial_materials/1766754149_Screenshot_2025_12_15_163648.png','oo');
INSERT INTO `wp_tutorial_uploads` (`id`,`appointment_id`,`user_id`,`file_path`,`material_type`,`upload_date`,`file_name`,`file_url`,`description`) VALUES('5','5','237520618','','Handouts / PDF Notes','2025-12-27 13:47:07','Screenshot 2025-12-15 163648.png','https://staging-68a5-inditechsites.wpcomstaging.com/wp-content/uploads/tutorial_materials/1766823427_Screenshot_2025_12_15_163648.png','ok ok');
INSERT INTO `wp_tutorial_uploads` (`id`,`appointment_id`,`user_id`,`file_path`,`material_type`,`upload_date`,`file_name`,`file_url`,`description`) VALUES('6','60','237520698','','Handouts / PDF Notes','2026-03-11 17:11:22','TYPHOID-ENTERIC-FEVER 2.pdf','https://staging-68a5-inditechsites.wpcomstaging.com/wp-content/uploads/tutorial_materials/1773229282_TYPHOID_ENTERIC_FEVER_2.pdf','Test');
-- rewind end page null
