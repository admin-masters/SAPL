-- rewind begin page null
CREATE TABLE `wp_fs_meta` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `object_type` varchar(192) DEFAULT NULL,
  `object_id` bigint(20) DEFAULT NULL,
  `key` varchar(192) DEFAULT NULL,
  `value` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
INSERT INTO `wp_fs_meta` (`id`,`object_type`,`object_id`,`key`,`value`,`created_at`,`updated_at`) VALUES('1','FluentSupport\\App\\Models\\MailBox','1','_webhook_token','c0f84d8d128f22d2',NULL,NULL);
INSERT INTO `wp_fs_meta` (`id`,`object_type`,`object_id`,`key`,`value`,`created_at`,`updated_at`) VALUES('2','option',NULL,'_email_piping_retry_config','a:2:{s:17:\"last_request_date\";s:19:\"2025-10-14 14:43:01\";s:20:\"last_processed_mimes\";a:0:{}}',NULL,NULL);
INSERT INTO `wp_fs_meta` (`id`,`object_type`,`object_id`,`key`,`value`,`created_at`,`updated_at`) VALUES('3','option',NULL,'global_business_settings','a:11:{s:14:\"portal_page_id\";s:0:\"\";s:13:\"login_message\";s:100:\"<p>Please login or create an account to access the Customer Support Portal</p> [fluent_support_auth]\";s:21:\"disable_public_ticket\";s:2:\"no\";s:19:\"accepted_file_types\";a:5:{i:0;s:6:\"images\";i:1;s:3:\"csv\";i:2;s:9:\"documents\";i:3;s:3:\"zip\";i:4;s:4:\"json\";}s:13:\"max_file_size\";s:1:\"2\";s:15:\"max_file_upload\";s:1:\"3\";s:18:\"del_files_on_close\";s:2:\"no\";s:24:\"enable_admin_bar_summary\";s:2:\"no\";s:17:\"enable_draft_mode\";s:2:\"no\";s:21:\"agent_feedback_rating\";s:2:\"no\";s:18:\"keyboard_shortcuts\";s:2:\"no\";}',NULL,NULL);
INSERT INTO `wp_fs_meta` (`id`,`object_type`,`object_id`,`key`,`value`,`created_at`,`updated_at`) VALUES('5','FluentSupport\\App\\Models\\MailBox','2','_email_ticket_created_email_to_customer','a:7:{s:3:\"key\";s:32:\"ticket_created_email_to_customer\";s:5:\"title\";s:28:\"Ticket Created (To Customer)\";s:13:\"email_subject\";s:35:\"Re: {{ticket.title}} #{{ticket.id}}\";s:10:\"email_body\";s:399:\"<p>Hi <strong><em>{{customer.full_name}}</em>,</strong></p><p>Your request (<a href=\"{{ticket.public_url}}\">#{{ticket.id}}</a>) has been received, and is being reviewed by our support staff.</p><p>To add additional comments, follow the link below:</p><h4><a href=\"{{ticket.public_url}}\">View Ticket</a></h4><p>&nbsp;</p><p>or follow this link: {{ticket.public_url}}</p><hr /><p>{{business.name}}</p>\";s:6:\"status\";s:3:\"yes\";s:16:\"can_edit_subject\";s:3:\"yes\";s:16:\"send_attachments\";s:2:\"no\";}',NULL,NULL);
INSERT INTO `wp_fs_meta` (`id`,`object_type`,`object_id`,`key`,`value`,`created_at`,`updated_at`) VALUES('6','person_meta','1','telegram_chat_id','','2025-10-23 13:43:41','2025-10-23 13:43:41');
INSERT INTO `wp_fs_meta` (`id`,`object_type`,`object_id`,`key`,`value`,`created_at`,`updated_at`) VALUES('7','person_meta','1','slack_user_id','','2025-10-23 13:43:41','2025-10-23 13:43:41');
INSERT INTO `wp_fs_meta` (`id`,`object_type`,`object_id`,`key`,`value`,`created_at`,`updated_at`) VALUES('8','person_meta','1','whatsapp_number','','2025-10-23 13:43:41','2025-10-23 13:43:41');
INSERT INTO `wp_fs_meta` (`id`,`object_type`,`object_id`,`key`,`value`,`created_at`,`updated_at`) VALUES('9','person_meta','1','agent_restrictions','a:2:{s:23:\"restrictedBusinessBoxes\";a:0:{}s:23:\"businessBoxRestrictions\";b:0;}','2025-10-23 13:43:41','2025-10-23 13:43:41');
-- rewind end page null
