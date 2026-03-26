-- rewind begin page null
CREATE TABLE `wp_fs_tickets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` bigint(20) unsigned DEFAULT NULL,
  `agent_id` bigint(20) unsigned DEFAULT NULL,
  `mailbox_id` bigint(20) unsigned DEFAULT NULL,
  `product_id` bigint(20) unsigned DEFAULT NULL,
  `product_source` varchar(192) DEFAULT NULL,
  `privacy` varchar(100) DEFAULT 'private',
  `priority` varchar(100) DEFAULT 'normal',
  `client_priority` varchar(100) DEFAULT 'normal',
  `status` varchar(100) DEFAULT 'new',
  `title` varchar(192) DEFAULT NULL,
  `slug` varchar(192) DEFAULT NULL,
  `hash` varchar(192) DEFAULT NULL,
  `content_hash` varchar(192) DEFAULT NULL,
  `message_id` varchar(192) DEFAULT NULL,
  `source` varchar(192) DEFAULT NULL,
  `content` longtext DEFAULT NULL,
  `secret_content` longtext DEFAULT NULL,
  `last_agent_response` timestamp NULL DEFAULT NULL,
  `last_customer_response` timestamp NULL DEFAULT NULL,
  `waiting_since` timestamp NULL DEFAULT NULL,
  `response_count` int(11) DEFAULT 0,
  `first_response_time` int(11) DEFAULT NULL,
  `total_close_time` int(11) DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `closed_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
INSERT INTO `wp_fs_tickets` (`id`,`customer_id`,`agent_id`,`mailbox_id`,`product_id`,`product_source`,`privacy`,`priority`,`client_priority`,`status`,`title`,`slug`,`hash`,`content_hash`,`message_id`,`source`,`content`,`secret_content`,`last_agent_response`,`last_customer_response`,`waiting_since`,`response_count`,`first_response_time`,`total_close_time`,`resolved_at`,`closed_by`,`created_at`,`updated_at`) VALUES('1','2',NULL,'2','0',NULL,'private','normal','normal','new','Hello','hello','837e9cb691','789396a04e11c8a713d910b665280614',NULL,NULL,'<p>Please check</p>',NULL,NULL,'2025-10-23 12:47:14','2025-10-23 12:47:14','0',NULL,NULL,NULL,NULL,'2025-10-23 12:47:14','2025-10-23 12:47:14');
INSERT INTO `wp_fs_tickets` (`id`,`customer_id`,`agent_id`,`mailbox_id`,`product_id`,`product_source`,`privacy`,`priority`,`client_priority`,`status`,`title`,`slug`,`hash`,`content_hash`,`message_id`,`source`,`content`,`secret_content`,`last_agent_response`,`last_customer_response`,`waiting_since`,`response_count`,`first_response_time`,`total_close_time`,`resolved_at`,`closed_by`,`created_at`,`updated_at`) VALUES('2','3',NULL,'2','0','local','private','normal','normal','new','I need help','i-need-help','0c8afee982','a8cc688792b203d53fce9e8d22f94fa8','<t4ktem.2b0e2mx63874w@gmail.com>','web','<p>My account is not working</p>',NULL,NULL,'2025-10-23 14:09:58','2025-10-23 14:09:58','0',NULL,NULL,NULL,NULL,'2025-10-23 14:09:58','2025-10-23 14:09:58');
INSERT INTO `wp_fs_tickets` (`id`,`customer_id`,`agent_id`,`mailbox_id`,`product_id`,`product_source`,`privacy`,`priority`,`client_priority`,`status`,`title`,`slug`,`hash`,`content_hash`,`message_id`,`source`,`content`,`secret_content`,`last_agent_response`,`last_customer_response`,`waiting_since`,`response_count`,`first_response_time`,`total_close_time`,`resolved_at`,`closed_by`,`created_at`,`updated_at`) VALUES('3','3','1','2',NULL,NULL,'private','normal','normal','new','[WEBINARS] Help needed','webinars-help-needed','199fee1511','184f4c0d95adb4ce58790ca4de78bff3',NULL,'web','<p>hj,gfdsfhzxcvgcd</p>\n<h4>Guided Help Transcript</h4>\n<pre>User selected topic: webinars\r\nUser clicked escalate\r\nUser submitted contact form</pre>\n<hr/><pre>Topic: webinars\nSite: My WordPress Site\nURL: https://staging-68a5-inditechsites.wpcomstaging.com/webinars/\nUser Logged In: yes\nUA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36\nTime: 2025-10-23 16:17:49</pre>',NULL,NULL,'2025-10-23 16:17:49','2025-10-23 16:17:49','0',NULL,NULL,NULL,NULL,'2025-10-23 16:17:49','2025-10-23 16:20:15');
INSERT INTO `wp_fs_tickets` (`id`,`customer_id`,`agent_id`,`mailbox_id`,`product_id`,`product_source`,`privacy`,`priority`,`client_priority`,`status`,`title`,`slug`,`hash`,`content_hash`,`message_id`,`source`,`content`,`secret_content`,`last_agent_response`,`last_customer_response`,`waiting_since`,`response_count`,`first_response_time`,`total_close_time`,`resolved_at`,`closed_by`,`created_at`,`updated_at`) VALUES('4','3',NULL,'2',NULL,NULL,'private','normal','normal','new','[TUTORIALS] Help needed','tutorials-help-needed','ceab9db240','22713e524d6a3e0f7af3386f664b9816',NULL,'web','<p>gfhds</p>\n<h4>Guided Help Transcript</h4>\n<pre>User selected topic: tutorials\r\nUser clicked escalate\r\nUser submitted contact form</pre>\n<hr/><pre>Topic: tutorials\nSite: My WordPress Site\nURL: https://staging-68a5-inditechsites.wpcomstaging.com/my-events/\nUser Logged In: yes\nUA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36\nTime: 2025-12-10 13:21:01</pre>',NULL,NULL,'2025-12-10 13:21:01','2025-12-10 13:21:01','0',NULL,NULL,NULL,NULL,'2025-12-10 13:21:01','2025-12-10 13:21:01');
-- rewind end page null
