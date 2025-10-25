-- =====================================================
-- Inventory Scanner Database Setup
-- Example Retail POS Company Name
-- =====================================================

USE svp;
GO
CREATE PROCEDURE sp_MarkItemsSold
    @Barcode VARCHAR(50),
    @Quantity INT = 1,
    @Notes NVARCHAR(MAX) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    
    DECLARE @UpdatedCount INT;
    
    -- Update the oldest OnFloor items to Sold status
    UPDATE TOP (@Quantity) InventoryScans
    SET 
        Status = 'Sold',
        Notes = CONCAT(
            ISNULL(Notes, ''), 
            CHAR(13) + CHAR(10),
            'Sold at POS on ', 
            CONVERT(VARCHAR, GETDATE(), 120),
            ISNULL(' - ' + @Notes, '')
        ),
        UpdatedAt = GETDATE()
    WHERE Barcode = @Barcode
    AND Status = 'OnFloor'
    ORDER BY ScanDateTime ASC;
    
    SET @UpdatedCount = @@ROWCOUNT;
    
    -- Return result
    SELECT 
        @UpdatedCount as ItemsMarkedSold,
        @Barcode as Barcode,
        (SELECT COUNT(*) FROM InventoryScans WHERE Barcode = @Barcode AND Status = 'OnFloor') as RemainingOnFloor;
END;
GO

-- Create stored procedure for inventory adjustment
CREATE PROCEDURE sp_AdjustInventory
    @ScanID INT,
    @NewStatus VARCHAR(20),
    @Notes NVARCHAR(MAX) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    
    UPDATE InventoryScans
    SET 
        Status = @NewStatus,
        Notes = CONCAT(
            ISNULL(Notes, ''), 
            CHAR(13) + CHAR(10),
            'Adjusted on ', 
            CONVERT(VARCHAR, GETDATE(), 120),
            ' - ', 
            @Notes
        ),
        UpdatedAt = GETDATE()
    WHERE ScanID = @ScanID;
    
    SELECT * FROM InventoryScans WHERE ScanID = @ScanID;
END;
GO

-- Create stored procedure to get inventory summary
CREATE PROCEDURE sp_GetInventorySummary
    @StartDate DATE = NULL,
    @EndDate DATE = NULL
AS
BEGIN
    SET NOCOUNT ON;
    
    -- Default to today if no dates provided
    IF @StartDate IS NULL
        SET @StartDate = CAST(GETDATE() AS DATE);
    
    IF @EndDate IS NULL
        SET @EndDate = CAST(GETDATE() AS DATE);
    
    -- Overall summary
    SELECT 
        COUNT(*) as TotalScans,
        COUNT(DISTINCT Barcode) as UniqueProducts,
        SUM(CASE WHEN Status = 'OnFloor' THEN Quantity ELSE 0 END) as ItemsOnFloor,
        SUM(CASE WHEN Status = 'Sold' THEN Quantity ELSE 0 END) as ItemsSold,
        MIN(ScanDateTime) as FirstScan,
        MAX(ScanDateTime) as LastScan
    FROM InventoryScans
    WHERE CAST(ScanDateTime AS DATE) BETWEEN @StartDate AND @EndDate;
    
    -- By product breakdown
    SELECT 
        Barcode,
        ProductName,
        COUNT(*) as ScanCount,
        SUM(Quantity) as TotalQuantity,
        SUM(CASE WHEN Status = 'OnFloor' THEN Quantity ELSE 0 END) as OnFloor,
        SUM(CASE WHEN Status = 'Sold' THEN Quantity ELSE 0 END) as Sold
    FROM InventoryScans
    WHERE CAST(ScanDateTime AS DATE) BETWEEN @StartDate AND @EndDate
    GROUP BY Barcode, ProductName
    ORDER BY ScanCount DESC;
END;
GO

-- Insert some sample data for testing (OPTIONAL - REMOVE IN PRODUCTION)
/*
INSERT INTO InventoryScans (Barcode, ProductID, ProductName, ScannedBy, DeviceID, Location, Status)
VALUES 
    ('5000112637588', 'COKE001', 'Coca-Cola 500ml', 'Test Scanner', 'Mobile-001', 'Floor 1', 'OnFloor'),
    ('5000112637588', 'COKE001', 'Coca-Cola 500ml', 'Test Scanner', 'Mobile-001', 'Floor 1', 'OnFloor'),
    ('5000112637595', 'PEPSI001', 'Pepsi 500ml', 'Test Scanner', 'Mobile-002', 'Floor 1', 'OnFloor');

-- Test the stored procedure
EXEC sp_MarkItemsSold @Barcode = '5000112637588', @Quantity = 1, @Notes = 'Test sale';
*/

-- Query to check current floor inventory
-- SELECT * FROM vw_CurrentFloorInventory ORDER BY QuantityOnFloor DESC;

-- Query to check daily summary
-- SELECT * FROM vw_DailyScanSummary ORDER BY ScanDate DESC;

-- Query to check today's scans
-- SELECT * FROM InventoryScans WHERE CAST(ScanDateTime AS DATE) = CAST(GETDATE() AS DATE) ORDER BY ScanDateTime DESC;

PRINT 'Inventory scanner database setup completed successfully!';
GO
