/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.11.13-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: svp
-- ------------------------------------------------------
-- Server version	10.11.13-MariaDB-0ubuntu0.24.04.1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `InventoryScans`
--

DROP TABLE IF EXISTS `InventoryScans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `InventoryScans` (
  `ScanID` int(11) NOT NULL AUTO_INCREMENT,
  `Barcode` varchar(50) NOT NULL,
  `ProductID` varchar(50) DEFAULT NULL,
  `ProductName` varchar(255) DEFAULT NULL,
  `ScannedBy` varchar(100) DEFAULT NULL,
  `DeviceID` varchar(100) DEFAULT NULL,
  `ScanDateTime` datetime NOT NULL DEFAULT current_timestamp(),
  `Location` varchar(100) DEFAULT NULL,
  `Quantity` int(11) NOT NULL DEFAULT 1,
  `Status` varchar(20) NOT NULL DEFAULT 'OnFloor',
  `Notes` text DEFAULT NULL,
  `CreatedAt` datetime DEFAULT current_timestamp(),
  `UpdatedAt` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`ScanID`),
  KEY `idx_barcode` (`Barcode`),
  KEY `idx_status` (`Status`),
  KEY `idx_datetime` (`ScanDateTime`),
  KEY `idx_barcode_status` (`Barcode`,`Status`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`svp`@`localhost`*/ /*!50003 TRIGGER trg_InventoryScans_UpdatedAt
BEFORE UPDATE ON InventoryScans
FOR EACH ROW
BEGIN
    SET NEW.UpdatedAt = CURRENT_TIMESTAMP;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`svp`@`localhost`*/ /*!50003 TRIGGER trg_InventoryScans_Audit_Update
AFTER UPDATE ON InventoryScans
FOR EACH ROW
BEGIN
    -- Only log if something meaningful changed
    IF (OLD.Status <> NEW.Status) OR (OLD.Notes <> NEW.Notes) THEN
        INSERT INTO InventoryScans_AuditLog (
            ScanID, ActionType, OldStatus, NewStatus, ChangedBy, ChangeNotes
        )
        VALUES (
            NEW.ScanID,
            'UPDATE',
            OLD.Status,
            NEW.Status,
            NEW.ScannedBy,
            CONCAT(
                'Status changed on ', DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i:%s'),
                '\nOld: ', OLD.Status,
                '\nNew: ', NEW.Status,
                IF(NEW.Notes <> OLD.Notes, CONCAT('\nNotes updated.'), '')
            )
        );
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`svp`@`localhost`*/ /*!50003 TRIGGER trg_InventoryScans_Audit_Delete
BEFORE DELETE ON InventoryScans
FOR EACH ROW
BEGIN
    INSERT INTO InventoryScans_AuditLog (
        ScanID, ActionType, OldStatus, NewStatus, ChangedBy, ChangeNotes
    )
    VALUES (
        OLD.ScanID,
        'DELETE',
        OLD.Status,
        NULL,
        OLD.ScannedBy,
        CONCAT(
            'Record deleted on ', DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i:%s'),
            '\nProduct: ', IFNULL(OLD.ProductName, 'Unknown'),
            '\nBarcode: ', OLD.Barcode
        )
    );
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `InventoryScans_AuditLog`
--

DROP TABLE IF EXISTS `InventoryScans_AuditLog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `InventoryScans_AuditLog` (
  `LogID` int(11) NOT NULL AUTO_INCREMENT,
  `ScanID` int(11) DEFAULT NULL,
  `ActionType` varchar(50) NOT NULL,
  `OldStatus` varchar(20) DEFAULT NULL,
  `NewStatus` varchar(20) DEFAULT NULL,
  `ChangedBy` varchar(100) DEFAULT NULL,
  `ChangeDate` datetime DEFAULT current_timestamp(),
  `ChangeNotes` text DEFAULT NULL,
  PRIMARY KEY (`LogID`),
  KEY `ScanID` (`ScanID`),
  CONSTRAINT `InventoryScans_AuditLog_ibfk_1` FOREIGN KEY (`ScanID`) REFERENCES `InventoryScans` (`ScanID`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cash_reconciliation`
--

DROP TABLE IF EXISTS `cash_reconciliation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cash_reconciliation` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `date_recorded` datetime NOT NULL,
  `cash_drawer` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`cash_drawer`)),
  `change_bags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`change_bags`)),
  `system_count` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`system_count`)),
  `system_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`system_value`)),
  `float_current` decimal(10,2) DEFAULT NULL,
  `float_previous` decimal(10,2) DEFAULT NULL,
  `float_balance` decimal(10,2) DEFAULT NULL,
  `lodge` decimal(10,2) DEFAULT NULL,
  `sales` decimal(10,2) DEFAULT NULL,
  `z_count` decimal(10,2) DEFAULT NULL,
  `cash_sales` decimal(10,2) DEFAULT NULL,
  `all_sales` decimal(10,2) DEFAULT NULL,
  `yesterday_sales` decimal(10,2) DEFAULT NULL,
  `recon_day` date GENERATED ALWAYS AS (cast(`date_recorded` as date)) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_recon_day` (`recon_day`)
) ENGINE=InnoDB AUTO_INCREMENT=476 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `target`
--

DROP TABLE IF EXISTS `target`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `target` (
  `target_ID` int(11) NOT NULL AUTO_INCREMENT,
  `weekly_target` decimal(10,0) NOT NULL,
  `work_days` smallint(6) DEFAULT NULL,
  `daily_target` decimal(10,4) DEFAULT NULL,
  PRIMARY KEY (`target_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `forename` varchar(100) DEFAULT NULL,
  `surname` varchar(250) DEFAULT NULL,
  `email` varchar(250) DEFAULT NULL,
  `phone` varchar(100) DEFAULT NULL,
  `receive` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Temporary table structure for view `vw_CurrentFloorInventory_nw`
--

DROP TABLE IF EXISTS `vw_CurrentFloorInventory_nw`;
/*!50001 DROP VIEW IF EXISTS `vw_CurrentFloorInventory_nw`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `vw_CurrentFloorInventory_nw` AS SELECT
 1 AS `Barcode`,
  1 AS `ProductID`,
  1 AS `ProductName`,
  1 AS `QuantityOnFloor`,
  1 AS `FirstScanned`,
  1 AS `LastScanned` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `vw_DailyScanSummary`
--

DROP TABLE IF EXISTS `vw_DailyScanSummary`;
/*!50001 DROP VIEW IF EXISTS `vw_DailyScanSummary`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `vw_DailyScanSummary` AS SELECT
 1 AS `ScanDate`,
  1 AS `TotalScans`,
  1 AS `UniqueProducts`,
  1 AS `TotalQuantity`,
  1 AS `OnFloor`,
  1 AS `Sold` */;
SET character_set_client = @saved_cs_client;

--
-- Dumping events for database 'svp'
--

--
-- Dumping routines for database 'svp'
--
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `sp_AdjustInventory` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`svp`@`localhost` PROCEDURE `sp_AdjustInventory`(
    IN p_ScanID INT,
    IN p_NewStatus VARCHAR(20),
    IN p_Notes TEXT
)
BEGIN
    UPDATE InventoryScans
    SET 
        Status = p_NewStatus,
        Notes = CONCAT(
            IFNULL(Notes, ''),
            '\nAdjusted on ', DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i:%s'),
            IF(p_Notes IS NOT NULL, CONCAT(' - ', p_Notes), '')
        )
    WHERE ScanID = p_ScanID;

    SELECT * FROM InventoryScans WHERE ScanID = p_ScanID;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `sp_GetInventorySummary` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`svp`@`localhost` PROCEDURE `sp_GetInventorySummary`(
    IN p_StartDate DATE,
    IN p_EndDate DATE
)
BEGIN
    IF p_StartDate IS NULL THEN
        SET p_StartDate = CURDATE();
    END IF;
    IF p_EndDate IS NULL THEN
        SET p_EndDate = CURDATE();
    END IF;

    SELECT 
        COUNT(*) AS TotalScans,
        COUNT(DISTINCT Barcode) AS UniqueProducts,
        SUM(CASE WHEN Status = 'OnFloor' THEN Quantity ELSE 0 END) AS ItemsOnFloor,
        SUM(CASE WHEN Status = 'Sold' THEN Quantity ELSE 0 END) AS ItemsSold,
        MIN(ScanDateTime) AS FirstScan,
        MAX(ScanDateTime) AS LastScan
    FROM InventoryScans
    WHERE DATE(ScanDateTime) BETWEEN p_StartDate AND p_EndDate;

    SELECT 
        Barcode,
        ProductName,
        COUNT(*) AS ScanCount,
        SUM(Quantity) AS TotalQuantity,
        SUM(CASE WHEN Status = 'OnFloor' THEN Quantity ELSE 0 END) AS OnFloor,
        SUM(CASE WHEN Status = 'Sold' THEN Quantity ELSE 0 END) AS Sold
    FROM InventoryScans
    WHERE DATE(ScanDateTime) BETWEEN p_StartDate AND p_EndDate
    GROUP BY Barcode, ProductName
    ORDER BY ScanCount DESC;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `sp_MarkItemsSold` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`svp`@`localhost` PROCEDURE `sp_MarkItemsSold`(
    IN p_Barcode VARCHAR(50),
    IN p_Quantity INT,
    IN p_Notes TEXT
)
BEGIN
    DECLARE v_UpdatedCount INT DEFAULT 0;

    UPDATE InventoryScans
    SET 
        Status = 'Sold',
        Notes = CONCAT(
            IFNULL(Notes, ''),
            '\nSold at POS on ', DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i:%s'),
            IF(p_Notes IS NOT NULL, CONCAT(' - ', p_Notes), '')
        )
    WHERE ScanID IN (
        SELECT ScanID FROM (
            SELECT ScanID
            FROM InventoryScans
            WHERE Barcode = p_Barcode AND Status = 'OnFloor'
            ORDER BY ScanDateTime ASC
            LIMIT p_Quantity
        ) AS tmp
    );

    SET v_UpdatedCount = ROW_COUNT();

    SELECT 
        v_UpdatedCount AS ItemsMarkedSold,
        p_Barcode AS Barcode,
        (SELECT COUNT(*) FROM InventoryScans WHERE Barcode = p_Barcode AND Status = 'OnFloor') AS RemainingOnFloor;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Final view structure for view `vw_CurrentFloorInventory_nw`
--

/*!50001 DROP VIEW IF EXISTS `vw_CurrentFloorInventory_nw`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`itmedia`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_CurrentFloorInventory_nw` AS select `InventoryScans`.`Barcode` AS `Barcode`,`InventoryScans`.`ProductID` AS `ProductID`,`InventoryScans`.`ProductName` AS `ProductName`,sum(`InventoryScans`.`Quantity`) AS `QuantityOnFloor`,min(`InventoryScans`.`ScanDateTime`) AS `FirstScanned`,max(`InventoryScans`.`ScanDateTime`) AS `LastScanned` from `InventoryScans` where `InventoryScans`.`Status` = 'OnFloor' group by `InventoryScans`.`Barcode`,`InventoryScans`.`ProductID`,`InventoryScans`.`ProductName` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vw_DailyScanSummary`
--

/*!50001 DROP VIEW IF EXISTS `vw_DailyScanSummary`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`svp`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_DailyScanSummary` AS select cast(`InventoryScans`.`ScanDateTime` as date) AS `ScanDate`,count(0) AS `TotalScans`,count(distinct `InventoryScans`.`Barcode`) AS `UniqueProducts`,sum(`InventoryScans`.`Quantity`) AS `TotalQuantity`,sum(case when `InventoryScans`.`Status` = 'OnFloor' then 1 else 0 end) AS `OnFloor`,sum(case when `InventoryScans`.`Status` = 'Sold' then 1 else 0 end) AS `Sold` from `InventoryScans` group by cast(`InventoryScans`.`ScanDateTime` as date) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-01-06 15:09:56
