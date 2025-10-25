<?php
/**
 * Get dashboard data via AJAX (no full page reload)
 */
require_once 'config.php';
header('Content-Type: application/json');

try {
    // Sync status
    $sync_file = __DIR__ . '/tmp/last_pos_sync.txt';
    $last_sync = file_exists($sync_file) ? file_get_contents($sync_file) : 'Never';
    $last_sync_time = strtotime($last_sync);
    $minutes_ago = $last_sync_time ? round((time() - $last_sync_time) / 60) : 0;
    
    $sync_status = 'Unknown';
    if ($minutes_ago <= 20) $sync_status = 'Current';
    elseif ($minutes_ago <= 60) $sync_status = 'Recent';
    else $sync_status = 'Stale';
    
    // Recent sales (last hour)
    $recent_sales = $pdo->query("
        SELECT 
            ProductID,
            ProductName,
            SUM(Quantity) as total_qty,
            COUNT(*) as transactions,
            MAX(SUBSTRING_INDEX(Notes, 'Sold via POS on ', -1)) as last_sale
        FROM InventoryScans
        WHERE Status = 'Sold'
        AND Notes LIKE '%Sold via POS%'
        AND SUBSTRING_INDEX(Notes, 'Sold via POS on ', -1) >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        GROUP BY ProductID, ProductName
        ORDER BY last_sale DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Today's stats
    $today_stats = $pdo->query("
        SELECT 
            COUNT(DISTINCT ProductID) as unique_products,
            SUM(Quantity) as total_items,
            COUNT(*) as transactions
        FROM InventoryScans
        WHERE Status = 'Sold'
        AND Notes LIKE '%Sold via POS%'
        AND DATE(SUBSTRING_INDEX(Notes, 'Sold via POS on ', -1)) = CURDATE()
    ")->fetch(PDO::FETCH_ASSOC);
    
    // Current inventory count
    $inventory_count = $pdo->query("
        SELECT 
            COUNT(DISTINCT ProductID) as unique_products,
            SUM(Quantity) as total_items
        FROM InventoryScans
        WHERE Status = 'OnFloor'
    ")->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'sync_status' => $sync_status,
        'last_sync' => $last_sync,
        'minutes_ago' => $minutes_ago,
        'recent_sales' => $recent_sales,
        'today_stats' => $today_stats,
        'inventory_count' => $inventory_count,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
