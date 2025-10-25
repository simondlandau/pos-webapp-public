<?php
/ Remove BOM if present
if (ob_get_level() === 0) ob_start();
/**
 * POS Sales Sync Script
 * Monitors MSSQL POS sales and updates MySQL inventory
 * Run via cron/Task Scheduler or triggered by dashboard
 */

require_once 'config.php';

// Lock mechanism to prevent concurrent runs
$lock_file = __DIR__ . '/tmp/sync.lock';
$lock_dir = dirname($lock_file);

// Create tmp directory if it doesn't exist
if (!is_dir($lock_dir)) {
    mkdir($lock_dir, 0755, true);
}

// Check if sync is already running
if (file_exists($lock_file)) {
    $lock_age = time() - filemtime($lock_file);
    if ($lock_age < 300) { // 5 minutes max lock
        echo "Sync already running (locked for {$lock_age}s)\n";
        exit(0);
    } else {
        // Stale lock, remove it
        unlink($lock_file);
        echo "Removed stale lock\n";
    }
}

// Create lock
file_put_contents($lock_file, date('Y-m-d H:i:s'));

// Track last sync time
$sync_file = __DIR__ . '/tmp/last_pos_sync.txt';
$last_sync = file_exists($sync_file) ? file_get_contents($sync_file) : date('Y-m-d H:i:s', strtotime('-1 hour'));

$log_output = "=== POS Sales Sync Started ===\n";
$log_output .= "Time: " . date('Y-m-d H:i:s') . "\n";
$log_output .= "Last sync: $last_sync\n\n";

try {
    // Get new sales from MSSQL since last sync
    $sql = "
        SELECT 
            SN_ITEM as ProductID,
            SN_QTY as Quantity,
            dtTimeStamp as SaleTime
        FROM SALES
        WHERE dtTimeStamp > ?
        ORDER BY dtTimeStamp ASC
    ";
    
    $stmt = $sqlsrv_pdo->prepare($sql);
    $stmt->execute([$last_sync]);
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $log_output .= "Found " . count($sales) . " new sales\n\n";
    
    if (count($sales) === 0) {
        $log_output .= "No new sales to process\n";
        file_put_contents($sync_file, date('Y-m-d H:i:s'));
        echo $log_output;
        unlink($lock_file);
        exit(0);
    }
    
    $processed = 0;
    $skipped = 0;
    
    foreach ($sales as $sale) {
        $productID = trim($sale['ProductID']);
        $qty_sold = (int)$sale['Quantity'];
        $sale_time = $sale['SaleTime'];
        
        $log_output .= "Processing: $productID (qty: $qty_sold) sold at $sale_time\n";
        
        // Check available inventory
        $checkStmt = $pdo->prepare("
            SELECT SUM(Quantity) as total_available
            FROM InventoryScans
            WHERE ProductID = ? AND Status = 'OnFloor'
        ");
        $checkStmt->execute([$productID]);
        $inventory = $checkStmt->fetch(PDO::FETCH_ASSOC);
        $available = (int)$inventory['total_available'];
        
        if ($available === 0) {
            $log_output .= "  ⚠️  No inventory - ignoring\n\n";
            $skipped++;
            continue;
        }
        
        // Mark items as sold
        $remaining = $qty_sold;
        $marked = 0;
        
        while ($remaining > 0) {
            // Get next OnFloor item
            $getStmt = $pdo->prepare("
                SELECT ScanID, Quantity
                FROM InventoryScans
                WHERE ProductID = ? AND Status = 'OnFloor'
                ORDER BY ScanID ASC
                LIMIT 1
            ");
            $getStmt->execute([$productID]);
            $item = $getStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$item) {
                $log_output .= "  ⚠️  Only marked $marked of $qty_sold\n";
                break;
            }
            
            $item_qty = (int)$item['Quantity'];
            $scan_id = $item['ScanID'];
            
            if ($item_qty <= $remaining) {
                // Mark entire item as sold
                $updateStmt = $pdo->prepare("
                    UPDATE InventoryScans
                    SET 
                        Status = 'Sold',
                        Notes = CONCAT(
                            IFNULL(Notes, ''),
                            '\nSold via POS on $sale_time'
                        )
                    WHERE ScanID = ?
                ");
                $updateStmt->execute([$scan_id]);
                
                $remaining -= $item_qty;
                $marked += $item_qty;
                $log_output .= "  ✓ ScanID $scan_id sold (qty: $item_qty)\n";
                
            } else {
                // Partial: Split the item
                $sold_portion = $remaining;
                $remaining_portion = $item_qty - $remaining;
                
                // Update existing to reduce quantity
                $updateStmt = $pdo->prepare("
                    UPDATE InventoryScans
                    SET Quantity = ?
                    WHERE ScanID = ?
                ");
                $updateStmt->execute([$remaining_portion, $scan_id]);
                
                // Create new record for sold portion
                $insertStmt = $pdo->prepare("
                    INSERT INTO InventoryScans 
                    (Barcode, ProductID, ProductName, Quantity, Status, ScanDateTime, ScannedBy, DeviceID, Notes)
                    SELECT Barcode, ProductID, ProductName, ?, 'Sold', ScanDateTime, ScannedBy, DeviceID,
                           CONCAT(IFNULL(Notes, ''), '\nSold via POS on $sale_time')
                    FROM InventoryScans
                    WHERE ScanID = ?
                ");
                $insertStmt->execute([$sold_portion, $scan_id]);
                
                $marked += $sold_portion;
                $remaining = 0;
                $log_output .= "  ✓ Split ScanID $scan_id: $remaining_portion OnFloor, $sold_portion Sold\n";
            }
        }
        
        $log_output .= "  Total marked: $marked items\n\n";
        $processed++;
    }
    
    // Update last sync time
    if (count($sales) > 0) {
        $latest_sale = end($sales);
        file_put_contents($sync_file, $latest_sale['SaleTime']);
    }
    
    $log_output .= "=== Sync Complete ===\n";
    $log_output .= "Processed: $processed sales\n";
    $log_output .= "Skipped: $skipped\n";
    
} catch (Exception $e) {
    $log_output .= "FATAL ERROR: " . $e->getMessage() . "\n";
}

// Remove lock
unlink($lock_file);

// Output to console and log
echo $log_output;

// Append to log file
$log_file = __DIR__ . '/logs/pos_sync.log';
$log_dir = dirname($log_file);
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}
file_put_contents($log_file, date('[Y-m-d H:i:s] ') . $log_output . "\n", FILE_APPEND);

// Keep log file manageable (last 1000 lines)
$lines = file($log_file);
if (count($lines) > 1000) {
    file_put_contents($log_file, implode('', array_slice($lines, -1000)));
}
?>
