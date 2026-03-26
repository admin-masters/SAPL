-- rewind begin page null
CREATE TABLE `wp_tutor_quiz_question_answers` (
  `answer_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `belongs_question_id` bigint(20) DEFAULT NULL,
  `belongs_question_type` varchar(250) DEFAULT NULL,
  `answer_title` text DEFAULT NULL,
  `is_correct` tinyint(4) DEFAULT NULL,
  `image_id` bigint(20) DEFAULT NULL,
  `answer_two_gap_match` text DEFAULT NULL,
  `answer_view_format` varchar(250) DEFAULT NULL,
  `answer_settings` text DEFAULT NULL,
  `answer_order` int(11) DEFAULT 0,
  PRIMARY KEY (`answer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
-- rewind end page null
