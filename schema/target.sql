-- svp.target definition

CREATE TABLE `target` (
  `target_ID` int(11) NOT NULL AUTO_INCREMENT,
  `weekly_target` decimal(10,0) NOT NULL,
  `work_days` smallint(6) DEFAULT NULL,
  `daily_target` decimal(10,4) DEFAULT NULL,
  PRIMARY KEY (`target_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO target (target_ID, weekly_target, work_days, daily_target) 
VALUES (1, 0, 5, 0);
