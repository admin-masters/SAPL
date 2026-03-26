-- rewind begin page null
CREATE TABLE `wp_users` (
  `ID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_login` varchar(60) NOT NULL DEFAULT '',
  `user_pass` varchar(255) NOT NULL DEFAULT '',
  `user_nicename` varchar(50) NOT NULL DEFAULT '',
  `user_email` varchar(100) NOT NULL DEFAULT '',
  `user_url` varchar(100) NOT NULL DEFAULT '',
  `user_registered` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `user_activation_key` varchar(255) NOT NULL DEFAULT '',
  `user_status` int(11) NOT NULL DEFAULT 0,
  `display_name` varchar(250) NOT NULL DEFAULT '',
  PRIMARY KEY (`ID`),
  KEY `user_login_key` (`user_login`),
  KEY `user_nicename` (`user_nicename`),
  KEY `user_email` (`user_email`)
) ENGINE=InnoDB AUTO_INCREMENT=237520718 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
INSERT INTO `wp_users` (`ID`,`user_login`,`user_pass`,`user_nicename`,`user_email`,`user_url`,`user_registered`,`user_activation_key`,`user_status`,`display_name`) VALUES('237520608','inditechsites','$P$BL4MQGAck.MgflSmpxtUicwazzIIW4.','inditechsites','wordpress1@inditech.co.in','https://inditech.co.in','2023-07-14 13:32:40','','0','InditechSites');
INSERT INTO `wp_users` (`ID`,`user_login`,`user_pass`,`user_nicename`,`user_email`,`user_url`,`user_registered`,`user_activation_key`,`user_status`,`display_name`) VALUES('237520609','tishadubey','$P$BPDvVcnh1D5hLg9dd3sMcsFaFEl7FN/','tishadubey','tisha.dubey@inditech.co.in','','2025-04-14 13:00:06','','0','tishadubey');
INSERT INTO `wp_users` (`ID`,`user_login`,`user_pass`,`user_nicename`,`user_email`,`user_url`,`user_registered`,`user_activation_key`,`user_status`,`display_name`) VALUES('237520611','sanyamjain','$wp$2y$10$hqYVgIAbLsNxGczzt4jIAejO0.JfqcEJ5oUZQiCzObvRF/1nyVr92','sanyamjain','sanyam.jain@inditech.co.in','','2025-10-13 12:18:13','','0','SANYAM JAIN');
INSERT INTO `wp_users` (`ID`,`user_login`,`user_pass`,`user_nicename`,`user_email`,`user_url`,`user_registered`,`user_activation_key`,`user_status`,`display_name`) VALUES('237520613','Manjeet','$wp$2y$10$mlsvXzT.7SfOkghcq2IX3OaEcSiwPCuHaAw7bU39Oh0OE/GKDU6QS','manjeet','manjeet.godara@inditech.co.in','','2025-11-01 07:18:29','','0','Manjeet Godara');
INSERT INTO `wp_users` (`ID`,`user_login`,`user_pass`,`user_nicename`,`user_email`,`user_url`,`user_registered`,`user_activation_key`,`user_status`,`display_name`) VALUES('237520615','Expert 1','$wp$2y$10$D1kAH0xa4qxIdYanpTI0EenE/EI5/7KqG88Y2/lG3GEUrWW4khRbO','expert-1','mypc88020@gmail.com','','2025-11-05 11:15:46','','0','Expert 1');
INSERT INTO `wp_users` (`ID`,`user_login`,`user_pass`,`user_nicename`,`user_email`,`user_url`,`user_registered`,`user_activation_key`,`user_status`,`display_name`) VALUES('237520618','ritika123','$wp$2y$10$9EfFxAqFj0SApalkJoXtW.WuSkkLVgJjIcypAUK28dsUw32jtK18O','ritika123','ritikadutta0901@gmail.com','','2025-12-15 09:09:24','','0','ritika dutta');
INSERT INTO `wp_users` (`ID`,`user_login`,`user_pass`,`user_nicename`,`user_email`,`user_url`,`user_registered`,`user_activation_key`,`user_status`,`display_name`) VALUES('237520623','ritika','$wp$2y$10$U.oJtM0Lsrk5n6dTu6KA.Oe96RVwvszYTOM5/x/kQ4jf1QEECxVMG','ritika','ritika.dutta@inditech.co.in','','2025-12-17 07:17:02','','0','ritika dutta');
INSERT INTO `wp_users` (`ID`,`user_login`,`user_pass`,`user_nicename`,`user_email`,`user_url`,`user_registered`,`user_activation_key`,`user_status`,`display_name`) VALUES('237520625','admincc3b866fdb','$wp$2y$10$sP/BpDE96LAPce6McLhZ3uD7TmcybZdbXVtPKBFH/x/30qS8kxYHy','admincc3b866fdb','admin@inditech.co.in','','2025-12-17 14:49:43','','0','admincc3b866fdb');
INSERT INTO `wp_users` (`ID`,`user_login`,`user_pass`,`user_nicename`,`user_email`,`user_url`,`user_registered`,`user_activation_key`,`user_status`,`display_name`) VALUES('237520646','effqe','$wp$2y$10$TiJW.3paG65Ug9OBb3MB8.9MccP/bnl6gg1GiYHQd.Zj83f4bhIU6','effqe','b23083@students.iitmandi.ac.in','','2025-12-18 10:07:18','','0','rtnef dfaegr');
INSERT INTO `wp_users` (`ID`,`user_login`,`user_pass`,`user_nicename`,`user_email`,`user_url`,`user_registered`,`user_activation_key`,`user_status`,`display_name`) VALUES('237520670','eqfbdsv','$wp$2y$10$7FRLwAznmYzJqQKK7arylO5fG0UQXkroL5bM2o5Rx8.GjyQ1gK5iC','eqfbdsv','9923103294@mail.jiit.ac.in','','2025-12-19 08:36:07','','0','Sanyam Jain');
INSERT INTO `wp_users` (`ID`,`user_login`,`user_pass`,`user_nicename`,`user_email`,`user_url`,`user_registered`,`user_activation_key`,`user_status`,`display_name`) VALUES('237520691','ritika111@email.com','$wp$2y$10$9k8zzUNNc/rF.Sg58qd2uOyj6woQ1ptKLLaxePXeuyVNAe3K.yUS2','ritika111email-com','ritika111@email.com','','2025-12-20 11:21:12','','0','ritika111@email.com');
INSERT INTO `wp_users` (`ID`,`user_login`,`user_pass`,`user_nicename`,`user_email`,`user_url`,`user_registered`,`user_activation_key`,`user_status`,`display_name`) VALUES('237520698','sanyamjain121','$wp$2y$10$fFZrSeh4A7ifDiH6qG4Swebw.LyDp1S5aEWa68wftUr41DAGwhH/G','sanyamjain121','23f2002611@ds.study.iitm.ac.in','','2025-12-22 14:37:06','','0','sanyam jain');
INSERT INTO `wp_users` (`ID`,`user_login`,`user_pass`,`user_nicename`,`user_email`,`user_url`,`user_registered`,`user_activation_key`,`user_status`,`display_name`) VALUES('237520699','jain78790@gmail.com','$wp$2y$10$uUioNWLEot7VvTXTyErNYeFd1gQkWYi.fcKJ62mHTe3A4cnwjDPC6','jain78790gmail-com','jain78790@gmail.com','','2025-12-22 14:45:03','','0','jain78790@gmail.com');
INSERT INTO `wp_users` (`ID`,`user_login`,`user_pass`,`user_nicename`,`user_email`,`user_url`,`user_registered`,`user_activation_key`,`user_status`,`display_name`) VALUES('237520700','mukund12','$wp$2y$10$6iByNnjmiuGk0KjCuwUocuGYGs0lh9JpEdVoaZ1Fi8GLOol5I4lW.','mukund12','mukundmangal2173@gmail.com','','2026-01-08 12:13:10','','0','mukund mangal');
INSERT INTO `wp_users` (`ID`,`user_login`,`user_pass`,`user_nicename`,`user_email`,`user_url`,`user_registered`,`user_activation_key`,`user_status`,`display_name`) VALUES('237520714','santhosh','$wp$2y$10$JebIhwfNjRDZraOYXdJE4OcpMwW1XVPyvfI17/FkR47/VTH6fKg/a','santhosh','santhosh.a@inditech.co.in','','2026-02-14 12:52:43','','0','santhosh');
INSERT INTO `wp_users` (`ID`,`user_login`,`user_pass`,`user_nicename`,`user_email`,`user_url`,`user_registered`,`user_activation_key`,`user_status`,`display_name`) VALUES('237520716','santhosh1212','$wp$2y$10$zEObVMzL74BdJ26/Z0udGe/j22D5eHWSyJVzrrg0KOxTwqKzsa9WS','santhosh1212','santhosh@gmail.com','','2026-02-23 10:49:05','','0','santhosh ananth');
INSERT INTO `wp_users` (`ID`,`user_login`,`user_pass`,`user_nicename`,`user_email`,`user_url`,`user_registered`,`user_activation_key`,`user_status`,`display_name`) VALUES('237520717','testsanthosh','$wp$2y$10$aVHBsK4gCy1uIKXWNegmYuQ/o4f2Fy25ywlFlHN0zR1IG5BfmXEZm','testsanthosh','santhosha@mail.com','','2026-02-23 13:11:25','','0','Santhosh Test');
-- rewind end page null
