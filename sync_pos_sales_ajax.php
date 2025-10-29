<?php
/**
 * AJAX wrapper - standalone version without include
 */
header('Content-Type: application/json; charset=UTF-8');

// Prevent any output before JSON
ob_clean();

require_once __DIR__ . '/config.php';

// Lock mechanism
$lock_file = __DIR__ . '/tmp/sync.lock';
$lock_dir = dirname($lock_file);

if (!is_dir($lock_dir)) {
    mkdir($lock_dir, 0755, true);
}

// Check if already running
if (file_exists($lock_file)) {
    $lock_age = time() - filemtime($lock_file);
    if ($lock_age < 300) {
        echo json_encode([
            'success' => false,
            'message' => 'Sync already running (locked for ' . $lock_age . 's)'
        ]);
        exit;
    } else {
        unlink($lock_file);
    }
}

// Create lock
file_put_contents($lock_file, date('Y-m-d H:i:s'));

try {
// Track last sync
$sync_file = __DIR__ . '/tmp/last_pos_sync.txt';
if (file_exists($sync_file)) {
    $last_sync_raw = trim(file_get_contents($sync_file));
    // Validate and sanitize the date
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $last_sync_raw);
    if ($dt && $dt->format('Y-m-d H:i:s') === $last_sync_raw) {
        $last_sync = $last_sync_raw;
    } else {
        // Invalid date in file, use 1 hour ago
        $last_sync = date('Y-m-d H:i:s', strtotime('-1 hour'));
        error_log("Invalid date in sync file: $last_sync_raw");
    }
} else {
    $last_sync = date('Y-m-d H:i:s', strtotime('-1 hour'));
}

// Additional safety: ensure date is within smalldatetime range
$dt_check = new DateTime($last_sync);
if ($dt_check->format('Y') < 1900 || $dt_check->format('Y') > 2079) {
    $last_sync = date('Y-m-d H:i:s', strtotime('-1 hour'));
    error_log("Date out of smalldatetime range, using fallback");
}
    
    // Get new sales
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
    
    if (count($sales) === 0) {
        file_put_contents($sync_file, date('Y-m-d H:i:s'));
        unlink($lock_file);
        echo json_encode([
            'success' => true,
            'message' => 'No new sales to process',
            'processed' => 0,
            'skipped' => 0
        ]);
        exit;
    }
    
    $processed = 0;
    $skipped = 0;
    
    foreach ($sales as $sale) {
        $productID = trim($sale['ProductID']);
        $qty_sold = (int)$sale['Quantity'];
        $sale_time = $sale['SaleTime'];
        
        // Check inventory
        $checkStmt = $pdo->prepare("
            SELECT SUM(Quantity) as total_available
            FROM InventoryScans
            WHERE ProductID = ? AND Status = 'OnFloor'
        ");
        $checkStmt->execute([$productID]);
        $inventory = $checkStmt->fetch(PDO::FETCH_ASSOC);
        $available = (int)$inventory['total_available'];
        
        if ($available === 0) {
            $skipped++;
            continue;
        }
        
        // Mark as sold
        $remaining = $qty_sold;
        
        while ($remaining > 0) {
            $getStmt = $pdo->prepare("
                SELECT ScanID, Quantity
                FROM InventoryScans
                WHERE ProductID = ? AND Status = 'OnFloor'
                ORDER BY ScanID ASC
                LIMIT 1
            ");
            $getStmt->execute([$productID]);
            $item = $getStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$item) break;
            
            $item_qty = (int)$item['Quantity'];
            $scan_id = $item['ScanID'];
            
            if ($item_qty <= $remaining) {
                // Mark entire item
                $updateStmt = $pdo->prepare("
                    UPDATE InventoryScans
                    SET Status = 'Sold',
                        Notes = CONCAT(IFNULL(Notes, ''), '\nSold via POS on $sale_time')
                    WHERE ScanID = ?
                ");
                $updateStmt->execute([$scan_id]);
                $remaining -= $item_qty;
            } else {
                // Split item
                $sold_portion = $remaining;
                $remaining_portion = $item_qty - $remaining;
                
                $updateStmt = $pdo->prepare("
                    UPDATE InventoryScans SET Quantity = ? WHERE ScanID = ?
                ");
                $updateStmt->execute([$remaining_portion, $scan_id]);
                
                $insertStmt = $pdo->prepare("
                    INSERT INTO InventoryScans 
                    (Barcode, ProductID, ProductName, Quantity, Status, ScanDateTime, ScannedBy, DeviceID, Notes)
                    SELECT Barcode, ProductID, ProductName, ?, 'Sold', ScanDateTime, ScannedBy, DeviceID,
                           CONCAT(IFNULL(Notes, ''), '\nSold via POS on $sale_time')
                    FROM InventoryScans WHERE ScanID = ?
                ");
                $insertStmt->execute([$sold_portion, $scan_id]);
                $remaining = 0;
            }
        }
        
        $processed++;
    }
    
    // Update last sync
    if (count($sales) > 0) {
        $latest = end($sales);
        file_put_contents($sync_file, $latest['SaleTime']);
    }
    
    // Write to log
    $log_msg = date('[Y-m-d H:i:s] ') . "Manual sync: Processed $processed, Skipped $skipped\n";
    file_put_contents(__DIR__ . '/logs/pos_sync.log', $log_msg, FILE_APPEND);
    
    unlink($lock_file);
    
    echo json_encode([
        'success' => true,
        'message' => "Processed $processed sales" . ($skipped > 0 ? ", skipped $skipped" : ""),
        'processed' => $processed,
        'skipped' => $skipped
    ]);
    
} catch (Exception $e) {
    if (file_exists($lock_file)) unlink($lock_file);
    
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
