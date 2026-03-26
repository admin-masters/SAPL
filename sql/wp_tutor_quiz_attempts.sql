-- rewind begin page null
CREATE TABLE `wp_tutor_quiz_attempts` (
  `attempt_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `course_id` bigint(20) DEFAULT NULL,
  `quiz_id` bigint(20) DEFAULT NULL,
  `user_id` bigint(20) DEFAULT NULL,
  `total_questions` int(11) DEFAULT NULL,
  `total_answered_questions` int(11) DEFAULT NULL,
  `total_marks` decimal(9,2) DEFAULT NULL,
  `earned_marks` decimal(9,2) DEFAULT NULL,
  `attempt_info` text DEFAULT NULL,
  `attempt_status` varchar(50) DEFAULT NULL,
  `attempt_ip` varchar(250) DEFAULT NULL,
  `attempt_started_at` datetime DEFAULT NULL,
  `attempt_ended_at` datetime DEFAULT NULL,
  `is_manually_reviewed` int(1) DEFAULT NULL,
  `manually_reviewed_at` datetime DEFAULT NULL,
  `result` varchar(10) DEFAULT NULL,
  PRIMARY KEY (`attempt_id`),
  KEY `course_id` (`course_id`),
  KEY `quiz_id` (`quiz_id`),
  KEY `user_id` (`user_id`),
  KEY `result` (`result`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
-- rewind end page null
