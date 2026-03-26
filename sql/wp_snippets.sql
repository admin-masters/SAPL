-- rewind begin page null
CREATE TABLE `wp_snippets` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `name` tinytext NOT NULL,
  `description` text NOT NULL,
  `code` longtext NOT NULL,
  `tags` longtext NOT NULL,
  `scope` varchar(15) NOT NULL DEFAULT 'global',
  `condition_id` bigint(20) NOT NULL DEFAULT 0,
  `priority` smallint(6) NOT NULL DEFAULT 10,
  `active` tinyint(1) NOT NULL DEFAULT 0,
  `modified` datetime NOT NULL DEFAULT current_timestamp(),
  `revision` bigint(20) NOT NULL DEFAULT 1,
  `cloud_id` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `scope` (`scope`),
  KEY `active` (`active`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
INSERT INTO `wp_snippets` (`id`,`name`,`description`,`code`,`tags`,`scope`,`condition_id`,`priority`,`active`,`modified`,`revision`,`cloud_id`) VALUES('1','Make upload filenames lowercase','Makes sure that image and file uploads have lowercase filenames.\n\nThis is a sample snippet. Feel free to use it, edit it, or remove it.','add_filter( \'sanitize_file_name\', \'mb_strtolower\' );','sample, media','global','0','10','0','2025-11-22 11:31:42','1',NULL);
INSERT INTO `wp_snippets` (`id`,`name`,`description`,`code`,`tags`,`scope`,`condition_id`,`priority`,`active`,`modified`,`revision`,`cloud_id`) VALUES('2','Disable admin bar','Turns off the WordPress admin bar for everyone except administrators.\n\nThis is a sample snippet. Feel free to use it, edit it, or remove it.','add_action( \'wp\', function () {\n\tif ( ! current_user_can( \'manage_options\' ) ) {\n\t\tshow_admin_bar( false );\n\t}\n} );','sample, admin-bar','front-end','0','10','0','2025-11-22 11:31:42','1',NULL);
INSERT INTO `wp_snippets` (`id`,`name`,`description`,`code`,`tags`,`scope`,`condition_id`,`priority`,`active`,`modified`,`revision`,`cloud_id`) VALUES('3','Allow smilies','Allows smiley conversion in obscure places.\n\nThis is a sample snippet. Feel free to use it, edit it, or remove it.','add_filter( \'widget_text\', \'convert_smilies\' );\nadd_filter( \'the_title\', \'convert_smilies\' );\nadd_filter( \'wp_title\', \'convert_smilies\' );\nadd_filter( \'get_bloginfo\', \'convert_smilies\' );','sample','global','0','10','0','2025-11-22 11:31:42','1',NULL);
INSERT INTO `wp_snippets` (`id`,`name`,`description`,`code`,`tags`,`scope`,`condition_id`,`priority`,`active`,`modified`,`revision`,`cloud_id`) VALUES('4','Current year','Shortcode for inserting the current year into a post or page..\n\nThis is a sample snippet. Feel free to use it, edit it, or remove it.','<?php echo date( \'Y\' ); ?>','sample, dates','content','0','10','0','2025-11-22 11:31:42','1',NULL);
INSERT INTO `wp_snippets` (`id`,`name`,`description`,`code`,`tags`,`scope`,`condition_id`,`priority`,`active`,`modified`,`revision`,`cloud_id`) VALUES('5','Show Expert Mapping','','// ADMIN NOTICE: show current mapping for a specific expert (replace 99)\nadd_action(\'admin_notices\', function () {\n  if (!current_user_can(\'manage_options\')) return;\n  $user_id = 237520617; // <-- expert\'s WP user ID\n  $a = get_user_meta($user_id, \'amelia_employee_id\', true);\n  $p = get_user_meta($user_id, \'platform_amelia_employee_id\', true);\n  echo \'<div class=\"notice notice-info\"><p>\'.\n       \'Expert user #\'.$user_id.\' → \'.\n       \'amelia_employee_id=<code>\'.esc_html($a).\'</code> · \'.\n       \'platform_amelia_employee_id=<code>\'.esc_html($p).\'</code>\'.\n       \'</p></div>\';\n});\n','','admin','0','10','1','2025-11-22 11:36:18','1',NULL);
-- rewind end page null
