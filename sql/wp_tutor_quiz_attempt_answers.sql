-- rewind begin page null
CREATE TABLE `wp_tutor_quiz_attempt_answers` (
  `attempt_answer_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) DEFAULT NULL,
  `quiz_id` bigint(20) DEFAULT NULL,
  `question_id` bigint(20) DEFAULT NULL,
  `quiz_attempt_id` bigint(20) DEFAULT NULL,
  `given_answer` longtext DEFAULT NULL,
  `question_mark` decimal(8,2) DEFAULT NULL,
  `achieved_mark` decimal(8,2) DEFAULT NULL,
  `minus_mark` decimal(8,2) DEFAULT NULL,
  `is_correct` tinyint(4) DEFAULT NULL,
  PRIMARY KEY (`attempt_answer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
-- rewind end page null
