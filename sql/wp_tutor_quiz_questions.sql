-- rewind begin page null
CREATE TABLE `wp_tutor_quiz_questions` (
  `question_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `content_id` bigint(20) unsigned DEFAULT NULL,
  `quiz_id` bigint(20) DEFAULT NULL,
  `question_title` text DEFAULT NULL,
  `question_description` longtext DEFAULT NULL,
  `answer_explanation` longtext DEFAULT '',
  `question_type` varchar(50) DEFAULT NULL,
  `question_mark` decimal(9,2) DEFAULT NULL,
  `question_settings` longtext DEFAULT NULL,
  `question_order` int(11) DEFAULT NULL,
  PRIMARY KEY (`question_id`),
  KEY `content_id` (`content_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
-- rewind end page null
