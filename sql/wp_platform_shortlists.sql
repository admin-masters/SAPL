-- rewind begin page null
CREATE TABLE `wp_platform_shortlists` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `college_user_id` bigint(20) NOT NULL,
  `expert_user_id` bigint(20) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_expert` (`college_user_id`,`expert_user_id`),
  UNIQUE KEY `map_idx` (`college_user_id`,`expert_user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
INSERT INTO `wp_platform_shortlists` (`id`,`college_user_id`,`expert_user_id`,`created_at`) VALUES('2','237520618','237520616','2025-12-19 14:06:25');
INSERT INTO `wp_platform_shortlists` (`id`,`college_user_id`,`expert_user_id`,`created_at`) VALUES('3','237520616','237520616','2025-12-19 14:45:23');
INSERT INTO `wp_platform_shortlists` (`id`,`college_user_id`,`expert_user_id`,`created_at`) VALUES('4','237520612','237520616','2025-12-19 17:32:57');
INSERT INTO `wp_platform_shortlists` (`id`,`college_user_id`,`expert_user_id`,`created_at`) VALUES('8','237520613','237520646','2026-01-19 20:24:56');
INSERT INTO `wp_platform_shortlists` (`id`,`college_user_id`,`expert_user_id`,`created_at`) VALUES('10','237520613','237520698','2026-01-19 21:07:01');
INSERT INTO `wp_platform_shortlists` (`id`,`college_user_id`,`expert_user_id`,`created_at`) VALUES('11','237520699','237520698','2026-01-20 01:04:47');
INSERT INTO `wp_platform_shortlists` (`id`,`college_user_id`,`expert_user_id`,`created_at`) VALUES('12','237520613','237520611','2026-01-20 06:01:33');
INSERT INTO `wp_platform_shortlists` (`id`,`college_user_id`,`expert_user_id`,`created_at`) VALUES('14','237520699','237520646','2026-02-20 13:32:02');
INSERT INTO `wp_platform_shortlists` (`id`,`college_user_id`,`expert_user_id`,`created_at`) VALUES('15','237520715','237520611','2026-02-21 16:21:41');
INSERT INTO `wp_platform_shortlists` (`id`,`college_user_id`,`expert_user_id`,`created_at`) VALUES('16','237520715','237520698','2026-02-21 16:21:45');
-- rewind end page null
