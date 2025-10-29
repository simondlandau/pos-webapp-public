-- =====================================================
-- Inventory Scanner Database Setup (MySQL version + Audit Log)
-- St. Vincents Point of Sale - Letterkenny
-- =====================================================

USE svp;

-- Drop if re-creating fresh
DROP TABLE IF EXISTS InventoryScans_AuditLog;
DROP TABLE IF EXISTS InventoryScans;

-- =====================================================
-- Main Inventory Table
-- =====================================================
CREATE TABLE InventoryScans (
    ScanID INT AUTO_INCREMENT PRIMARY KEY,
    Barcode VARCHAR(50) NOT NULL,
    ProductID VARCHAR(50),
    ProductName VARCHAR(255),
    ScannedBy VARCHAR(100),
    DeviceID VARCHAR(100),
    ScanDateTime DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    Location VARCHAR(100),
    Quantity INT NOT NULL DEFAULT 1,
    Status VARCHAR(20) NOT NULL DEFAULT 'OnFloor', -- OnFloor, Sold, Adjusted, Removed
    Notes TEXT,
    CreatedAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_barcode (Barcode),
    INDEX idx_status (Status),
    INDEX idx_datetime (ScanDateTime),
    INDEX idx_barcode_status (Barcode, Status)
);

-- =====================================================
-- Audit Log Table
-- =====================================================
CREATE TABLE InventoryScans_AuditLog (
    LogID INT AUTO_INCREMENT PRIMARY KEY,
    ScanID INT,
    ActionType VARCHAR(50) NOT NULL,       -- e.g., UPDATE, DELETE
    OldStatus VARCHAR(20),
    NewStatus VARCHAR(20),
    ChangedBy VARCHAR(100),
    ChangeDate DATETIME DEFAULT CURRENT_TIMESTAMP,
    ChangeNotes TEXT,
    FOREIGN KEY (ScanID) REFERENCES InventoryScans(ScanID)
        ON DELETE SET NULL
        ON UPDATE CASCADE
);

-- =====================================================
-- Trigger: Record Updates (Status changes)
-- =====================================================
DELIMITER //
CREATE TRIGGER trg_InventoryScans_Audit_Update
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
END;
//
DELIMITER ;

-- =====================================================
-- Trigger: Record Deletes
-- =====================================================
DELIMITER //
CREATE TRIGGER trg_InventoryScans_Audit_Delete
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
END;
//
DELIMITER ;

-- =====================================================
-- Trigger: Maintain UpdatedAt timestamp (redundant safety)
-- =====================================================
DELIMITER //
CREATE TRIGGER trg_InventoryScans_UpdatedAt
BEFORE UPDATE ON InventoryScans
FOR EACH ROW
BEGIN
    SET NEW.UpdatedAt = CURRENT_TIMESTAMP;
END;
//
DELIMITER ;

-- =====================================================
-- Views
-- =====================================================
CREATE OR REPLACE VIEW vw_CurrentFloorInventory AS
SELECT 
    Barcode,
    ProductName,
    COUNT(*) AS QuantityOnFloor,
    MIN(ScanDateTime) AS FirstScanned,
    MAX(ScanDateTime) AS LastScanned,
    GROUP_CONCAT(ScanID ORDER BY ScanID ASC SEPARATOR ',') AS ScanIDs
FROM InventoryScans
WHERE Status = 'OnFloor'
GROUP BY Barcode, ProductName;

CREATE OR REPLACE VIEW vw_DailyScanSummary AS
SELECT 
    DATE(ScanDateTime) AS ScanDate,
    COUNT(*) AS TotalScans,
    COUNT(DISTINCT Barcode) AS UniqueProducts,
    SUM(Quantity) AS TotalQuantity,
    SUM(CASE WHEN Status = 'OnFloor' THEN 1 ELSE 0 END) AS OnFloor,
    SUM(CASE WHEN Status = 'Sold' THEN 1 ELSE 0 END) AS Sold
FROM InventoryScans
GROUP BY DATE(ScanDateTime);

-- =====================================================
-- Stored Procedures
-- =====================================================

DELIMITER //

-- Mark items as sold
CREATE PROCEDURE sp_MarkItemsSold(
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
END;
//

-- Adjust inventory
CREATE PROCEDURE sp_AdjustInventory(
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
END;
//

-- Get inventory summary
CREATE PROCEDURE sp_GetInventorySummary(
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
END;
//
DELIMITER ;

-- =====================================================
-- (Optional) Test Data
-- =====================================================
-- INSERT INTO InventoryScans (Barcode, ProductID, ProductName, ScannedBy, DeviceID, Location, Status)
-- VALUES
--     ('5000112637588', 'COKE001', 'Coca-Cola 500ml', 'Test Scanner', 'Mobile-001', 'Floor 1', 'OnFloor'),
--     ('5000112637588', 'COKE001', 'Coca-Cola 500ml', 'Test Scanner', 'Mobile-001', 'Floor 1', 'OnFloor'),
--     ('5000112637595', 'PEPSI001', 'Pepsi 500ml', 'Test Scanner', 'Mobile-002', 'Floor 1', 'OnFloor');

-- SELECT * FROM InventoryScans_AuditLog ORDER BY ChangeDate DESC;

SELECT '✅ Inventory scanner + audit log setup completed successfully!' AS Message;

