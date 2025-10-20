# Mobile Inventory Scanner - Implementation Guide
## Example Retail POS Company Name

---

## 🎯 Overview

This mobile barcode scanner system allows multiple staff members to scan inventory items using their smartphones. Scanned items are recorded in your **MySQL database** while product information is read from your existing **MSSQL POS database** (read-only).

### Architecture:
```
┌─────────────────────────────┐
│   Mobile Phones (PWA)       │ ← Scan barcodes
└──────────┬──────────────────┘
           │
           ↓ HTTPS API
┌──────────────────────────────┐
│   PHP Backend (inventory_api)│
└─────┬──────────────────┬─────┘
      │                  │
      ↓ (Read Only)      ↓ (Read/Write)
┌─────────────┐    ┌──────────────────┐
│   MSSQL     │    │     MySQL        │
│   (POS)     │    │  (Inventory)     │
│  ├─BARCODE  │    │ ├─InventoryScans │
│  ├─STOCKMST │    │ └─AuditLog       │
│  └─LOCATION │    │                  │
└─────────────┘    └──────────────────┘
```

---

## 📋 Prerequisites

- ✅ Web server with PHP 7.4+ 
- ✅ MySQL database with `InventoryScans` table (mysql_scanner.sql)
- ✅ MSSQL database (read-only access) (✓ Existing)
- ✅ HTTPS enabled (required for camera access)
- ✅ Smartphones with cameras and modern browsers

---

## 🗄️ Database Schema Verification

### Your MySQL Tables (mysql_scanner.sql):
```sql
-- ✓ InventoryScans - stores all scanned items
-- ✓ InventoryScans_AuditLog - tracks changes
-- ✓ vw_CurrentFloorInventory - view for floor inventory
-- ✓ vw_DailyScanSummary - view for daily summaries
-- ✓ sp_MarkItemsSold - procedure to mark items sold
-- ✓ sp_AdjustInventory - procedure for adjustments
-- ✓ sp_GetInventorySummary - procedure for reports
```

### Your MSSQL Schema (Read-Only):
```sql
-- Product lookup uses these tables:
svp.dbo.BARCODE (BC_REF, BC_PART)
svp.dbo.STOCKMST (PM_PART, PM_DESC, PM_RRP, PM_SUPP, PM_DEPT)
svp.dbo.LOCATION (LO_PART, LO_ONHAND)

-- Joined by: BC_PART = PM_PART = LO_PART
```

---

## 🚀 Installation Steps

### Step 1: Verify MySQL Tables

Your MySQL setup is already correct! Verify with:
```sql
USE svp;
SHOW TABLES LIKE 'Inventory%';
SELECT * FROM vw_CurrentFloorInventory;
```

### Step 2: Upload Files

Upload these files to your web server:

```
/var/www/html/
├── inventory_scanner.html  (the PWA interface)
├── inventory_api.php       (the backend API - UPDATED)
├── inventory_dashboard.php (admin view)
├── inventory_export.php    (CSV export)
├── manifest.json           (PWA manifest)
├── icon-192.png            (app icon 192x192)
└── icon-512.png            (app icon 512x512)
```

### Step 3: Test Database Connectivity

Create a test file `test_inventory.php`:

```php
<?php
require_once 'config.php';

echo "<h2>Testing Database Connections</h2>";

// Test MySQL
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM InventoryScans");
    $result = $stmt->fetch();
    echo "✓ MySQL Connection OK - {$result['count']} scans in database<br>";
} catch (Exception $e) {
    echo "✗ MySQL Error: " . $e->getMessage() . "<br>";
}

// Test MSSQL Product Lookup
try {
    $stmt = $sqlsrv_pdo->prepare("
        SELECT TOP 1
            b.BC_REF as Barcode,
            s.PM_DESC as ProductName,
            b.BC_PART as SKU,
            s.PM_RRP as Price,
            l.LO_ONHAND as Stock
        FROM dbo.BARCODE b
        INNER JOIN dbo.STOCKMST s ON b.BC_PART = s.PM_# Mobile Inventory Scanner - Implementation Guide
## Example Retail POS Company Name

---

## 🎯 Overview

This mobile barcode scanner system allows multiple staff members to scan inventory items using their smartphones. Scanned items are recorded in your MSSQL database and automatically marked as sold when processed through your POS system.

---

## 📋 Prerequisites

- ✅ Web server with PHP 7.4+ (your existing setup)
- ✅ MSSQL database (already configured)
- ✅ HTTPS enabled (required for camera access)
- ✅ Smartphones with cameras and modern browsers

---

## 🚀 Installation Steps

### Step 1: Database Setup

1. Open SQL Server Management Studio
2. Connect to your MSSQL server (127.0.0.1,1433)
3. Run the `create_inventory_table.sql` script
4. Verify the table was created:
   ```sql
   SELECT * FROM InventoryScans;
   SELECT * FROM vw_CurrentFloorInventory;
   ```

### Step 2: Upload Files

Upload these files to your web server:

```
/var/www/html/
├── inventory_scanner.html  (the PWA interface)
├── inventory_api.php       (the backend API)
├── manifest.json           (PWA manifest - see below)
├── icon-192.png            (app icon 192x192)
└── icon-512.png            (app icon 512x512)
```

### Step 3: Create manifest.json

Create a file called `manifest.json` in your web root:

```json
{
  "name": "Example Retail POS Company Name Scanner",
  "short_name": "Scanner",
  "description": "Mobile inventory scanner for Company Name",
  "start_url": "/inventory_scanner.html",
  "display": "standalone",
  "background_color": "#ffffff",
  "theme_color": "#28a745",
  "orientation": "portrait",
  "icons": [
    {
      "src": "/icon-192.png",
      "sizes": "192x192",
      "type": "image/png"
    },
    {
      "src": "/icon-512.png",
      "sizes": "512x512",
      "type": "image/png"
    }
  ]
}
```

### Step 4: Adjust API for Your Database Schema

Edit `inventory_api.php` and update the SQL queries to match your actual table structure:

```php
// Change this:
$stmt = $pdo->prepare("
    SELECT TOP 1
        Barcode,
        Description as name,
        Code as sku,
        Price1 as price,
        QtyOnHand as stock
    FROM Stock
    WHERE Barcode = ?
");

// To match YOUR actual product table and columns
// Example if your table is called 'Products':
$stmt = $pdo->prepare("
    SELECT TOP 1
        ProductBarcode as Barcode,
        ProductName as name,
        ProductCode as sku,
        RetailPrice as price,
        CurrentStock as stock
    FROM Products
    WHERE ProductBarcode = ?
");
```

---

## 📱 Using the Scanner

### On Desktop/Testing:
1. Open: `https://your-domain.com/inventory_scanner.html`
2. Allow camera access when prompted
3. Click "Start Camera"
4. Point camera at barcode

### On Mobile Phones:

#### Option 1: Direct Browser Use
1. Open Safari (iPhone) or Chrome (Android)
2. Go to: `https://your-domain.com/inventory_scanner.html`
3. Bookmark it for easy access

#### Option 2: Install as App (Recommended)
1. Open the URL in browser
2. **iPhone**: Tap Share → "Add to Home Screen"
3. **Android**: Tap menu (⋮) → "Add to Home Screen"
4. Icon appears on home screen like a native app
5. Tap icon to launch full-screen

### Scanning Process:
1. Tap "Start Camera" button
2. Point at barcode (center it in green box)
3. App automatically detects and records
4. Product info displays immediately
5. Item is added to floor inventory
6. Continue scanning additional items

---

## 🔗 Integration with POS

To automatically mark items as "Sold" when they're sold at the POS:

### Option 1: Add to Existing POS Transaction Code

Find where your POS records sales and add this code:

```php
// After recording the sale in your POS
require_once 'config.php';

function markInventorySold($barcode, $quantity = 1) {
    global $sqlsrv_pdo;
    
    try {
        $stmt = $sqlsrv_pdo->prepare("
            EXEC sp_MarkItemsSold @Barcode = ?, @Quantity = ?, @Notes = ?
        ");
        $stmt->execute([$barcode, $quantity, 'Sold via POS']);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Inventory update failed: " . $e->getMessage());
        return false;
    }
}

// Call this when item is sold
$result = markInventorySold($scanned_barcode, $quantity_sold);
```

### Option 2: Database Trigger (Automatic)

Create a trigger on your sales table:

```sql
CREATE TRIGGER trg_UpdateInventoryOnSale
ON SalesTransactions  -- Your actual sales table name
AFTER INSERT
AS
BEGIN
    DECLARE @Barcode VARCHAR(50);
    DECLARE @Quantity INT;
    
    SELECT @Barcode = Barcode, @Quantity = Quantity
    FROM inserted;
    
    EXEC sp_MarkItemsSold @Barcode = @Barcode, @Quantity = @Quantity, @Notes = 'Auto-sold via POS';
END;
```

---

## 📊 Reports & Monitoring

### Check Current Floor Inventory:
```sql
SELECT * FROM vw_CurrentFloorInventory 
ORDER BY QuantityOnFloor DESC;
```

### Today's Scan Activity:
```sql
SELECT * FROM InventoryScans 
WHERE CAST(ScanDateTime AS DATE) = CAST(GETDATE() AS DATE)
ORDER BY ScanDateTime DESC;
```

### Get Summary Report:
```sql
EXEC sp_GetInventorySummary 
    @StartDate = '2025-10-01', 
    @EndDate = '2025-10-13';
```

### Items Scanned But Not Sold:
```sql
SELECT 
    Barcode,
    ProductName,
    COUNT(*) as Count,
    MIN(ScanDateTime) as FirstScanned,
    DATEDIFF(hour, MIN(ScanDateTime), GETDATE()) as HoursOnFloor
FROM InventoryScans
WHERE Status = 'OnFloor'
GROUP BY Barcode, ProductName
ORDER BY HoursOnFloor DESC;
```

---

## 🔧 Troubleshooting

### Camera Not Working
- **Ensure HTTPS is enabled** (camera only works on https://)
- Check browser permissions (Settings → Site Settings)
- Try a different browser
- Restart phone

### Barcodes Not Scanning
- Ensure good lighting
- Hold phone steady
- Try moving closer/further from barcode
- Use manual entry as backup

### "Product Not Found" Errors
- Verify barcode exists in database
- Check SQL query in `inventory_api.php` matches your schema
- Test with a known good barcode

### Offline Mode
- Scans are saved locally when offline
- Will sync automatically when connection restored
- Check browser console for sync status

---

## 🔐 Security Considerations

1. **HTTPS Required**: Camera access only works over HTTPS
2. **Access Control**: Add login to `inventory_scanner.html` if needed
3. **API Protection**: Consider adding API key authentication
4. **Database Permissions**: Ensure scanner has appropriate permissions

---

## 📈 Advanced Features (Optional)

### Add User Authentication:
```php
// Add to top of inventory_scanner.html
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
```

### Add Location Tracking:
```javascript
// In the scanner JavaScript
navigator.geolocation.getCurrentPosition((position) => {
    const location = {
        lat: position.coords.latitude,
        lng: position.coords.longitude
    };
    // Send with scan data
});
```

### Multiple Locations:
Add dropdown in scanner UI:
```html
<select id="locationSelect">
    <option value="Floor 1">Floor 1</option>
    <option value="Floor 2">Floor 2</option>
    <option value="Warehouse">Warehouse</option>
</select>
```

---

## 📞 Support

For issues or questions:
- Check browser console for errors (F12)
- Review server error logs
- Verify database connectivity
- Test API endpoints directly

---

## ✅ Testing Checklist

- [ ] Database table created successfully
- [ ] Can access scanner URL on desktop
- [ ] Camera permission works
- [ ] Test barcode scans successfully
- [ ] Product info displays correctly
- [ ] Data appears in InventoryScans table
- [ ] PWA installs on mobile device
- [ ] Scanner works on installed app
- [ ] POS integration marks items as sold
- [ ] Reports show accurate data

---

## 🎉 You're Ready!

Your inventory scanner is now operational. Staff can start scanning items immediately using their phones!
