-- rewind begin page null
CREATE TABLE `wp_webinar_materials` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `event_id` bigint(20) NOT NULL,
  `title` varchar(255) NOT NULL,
  `file_url` varchar(500) NOT NULL,
  `file_type` varchar(50) DEFAULT 'pdf',
  `material_type` varchar(100) NOT NULL DEFAULT 'Handouts / PDF Notes',
  `description` text DEFAULT NULL,
  `uploaded_at` datetime NOT NULL,
  `uploaded_by` bigint(20) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `event_id` (`event_id`),
  KEY `uploaded_at` (`uploaded_at`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
INSERT INTO `wp_webinar_materials` (`id`,`event_id`,`title`,`file_url`,`file_type`,`material_type`,`description`,`uploaded_at`,`uploaded_by`) VALUES('1','14','Calculus.pdf','https://staging-68a5-inditechsites.wpcomstaging.com/wp-content/uploads/tutorial_materials/1773298758_Calculus.pdf','pdf','Presentation Slides','','2026-03-12 12:29:18','237520714');
INSERT INTO `wp_webinar_materials` (`id`,`event_id`,`title`,`file_url`,`file_type`,`material_type`,`description`,`uploaded_at`,`uploaded_by`) VALUES('3','13','Chapter 2.pdf','https://staging-68a5-inditechsites.wpcomstaging.com/wp-content/uploads/tutorial_materials/1773312466_Chapter_2.pdf','pdf','Handouts / PDF Notes','','2026-03-12 16:17:46','237520698');
INSERT INTO `wp_webinar_materials` (`id`,`event_id`,`title`,`file_url`,`file_type`,`material_type`,`description`,`uploaded_at`,`uploaded_by`) VALUES('4','17','Practice-Set_Partial-Derivatuves-1.pdf','https://staging-68a5-inditechsites.wpcomstaging.com/wp-content/uploads/tutorial_materials/1773320352_Practice_Set_Partial_Derivatuves_1.pdf','pdf','Handouts / PDF Notes','','2026-03-12 18:29:12','237520698');
INSERT INTO `wp_webinar_materials` (`id`,`event_id`,`title`,`file_url`,`file_type`,`material_type`,`description`,`uploaded_at`,`uploaded_by`) VALUES('5','1','1. Introduction (2).pptx','https://staging-68a5-inditechsites.wpcomstaging.com/wp-content/uploads/tutorial_materials/1773329720_1._Introduction__2_.pptx','pptx','Presentation Slides','','2026-03-12 21:05:20','237520698');
INSERT INTO `wp_webinar_materials` (`id`,`event_id`,`title`,`file_url`,`file_type`,`material_type`,`description`,`uploaded_at`,`uploaded_by`) VALUES('6','18','TYPHOID-ENTERIC-FEVER-2.pdf','https://staging-68a5-inditechsites.wpcomstaging.com/wp-content/uploads/tutorial_materials/1773332796_TYPHOID_ENTERIC_FEVER_2.pdf','pdf','Handouts / PDF Notes','','2026-03-12 21:56:36','237520698');
INSERT INTO `wp_webinar_materials` (`id`,`event_id`,`title`,`file_url`,`file_type`,`material_type`,`description`,`uploaded_at`,`uploaded_by`) VALUES('7','18','1773320352_Practice_Set_Partial_Derivatuves_1.pdf','https://staging-68a5-inditechsites.wpcomstaging.com/wp-content/uploads/tutorial_materials/1773332796_1773320352_Practice_Set_Partial_Derivatuves_1.pdf','pdf','Handouts / PDF Notes','','2026-03-12 21:56:36','237520698');
INSERT INTO `wp_webinar_materials` (`id`,`event_id`,`title`,`file_url`,`file_type`,`material_type`,`description`,`uploaded_at`,`uploaded_by`) VALUES('8','18','santhosh-ananth-Pediatric-Growth-Assessment-Certification-Exam-Paramedic-Certificate-esapa.one_.pdf','https://staging-68a5-inditechsites.wpcomstaging.com/wp-content/uploads/tutorial_materials/1773332796_santhosh_ananth_Pediatric_Growth_Assessment_Certification_Exam_Paramedic_Certificate_esapa.one_.pdf','pdf','Handouts / PDF Notes','','2026-03-12 21:56:36','237520698');
INSERT INTO `wp_webinar_materials` (`id`,`event_id`,`title`,`file_url`,`file_type`,`material_type`,`description`,`uploaded_at`,`uploaded_by`) VALUES('10','24','1773310139_Chapter_2.pdf','https://staging-68a5-inditechsites.wpcomstaging.com/wp-content/uploads/tutorial_materials/1774001486_1773310139_Chapter_2.pdf','pdf','Handouts / PDF Notes','','2026-03-20 15:41:26','237520698');
INSERT INTO `wp_webinar_materials` (`id`,`event_id`,`title`,`file_url`,`file_type`,`material_type`,`description`,`uploaded_at`,`uploaded_by`) VALUES('12','16','santhosh-ananth-Pediatric-Growth-Assessment-Certification-Exam-Paramedic-Certificate-esapa.one.pdf','https://staging-68a5-inditechsites.wpcomstaging.com/wp-content/uploads/tutorial_materials/1774001680_santhosh_ananth_Pediatric_Growth_Assessment_Certification_Exam_Paramedic_Certificate_esapa.one.pdf','pdf','Assignment / Homework','','2026-03-20 15:44:40','237520698');
-- rewind end page null
