-- rewind begin page null
CREATE TABLE `wp_tutor_email_queue` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `mail_to` varchar(255) NOT NULL,
  `subject` text NOT NULL,
  `message` text NOT NULL,
  `headers` text NOT NULL,
  `batch` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
-- rewind end page null
