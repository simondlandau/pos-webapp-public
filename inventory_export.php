<?php
require_once 'config.php';

$type = $_GET['type'] ?? 'all';
$format = $_GET['format'] ?? 'csv';
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');

$filename = "inventory_export_" . date('Y-m-d_His') . ".csv";

try {
    // Prepare query based on type
    switch ($type) {
        case 'floor':
            $query = "
                SELECT 
                    Barcode,
                    ProductName,
                    COUNT(*) as Quantity,
                    MIN(ScanDateTime) as FirstScanned,
                    MAX(ScanDateTime) as LastScanned,
                    TIMESTAMPDIFF(HOUR, MIN(ScanDateTime), NOW()) as HoursOnFloor
                FROM InventoryScans
                WHERE Status = 'OnFloor'
                GROUP BY Barcode, ProductName
                ORDER BY Quantity DESC
            ";
            $filename = "floor_inventory_" . date('Y-m-d_His') . ".csv";
            break;
            
        case 'sold':
            $query = "
                SELECT 
                    ScanID,
                    Barcode,
                    ProductName,
                    ScannedBy,
                    ScanDateTime,
                    UpdatedAt as SoldDateTime,
                    Location,
                    Notes
                FROM InventoryScans
                WHERE Status = 'Sold'
                AND DATE(UpdatedAt) BETWEEN '$startDate' AND '$endDate'
                ORDER BY UpdatedAt DESC
            ";
            $filename = "sold_items_" . date('Y-m-d_His') . ".csv";
            break;
            
        case 'daily':
            $query = "
                SELECT 
                    DATE(ScanDateTime) as ScanDate,
                    COUNT(*) as TotalScans,
                    COUNT(DISTINCT Barcode) as UniqueProducts,
                    SUM(Quantity) as TotalQuantity,
                    SUM(CASE WHEN Status = 'OnFloor' THEN 1 ELSE 0 END) as OnFloor,
                    SUM(CASE WHEN Status = 'Sold' THEN 1 ELSE 0 END) as Sold
                FROM InventoryScans
                WHERE DATE(ScanDateTime) BETWEEN '$startDate' AND '$endDate'
                GROUP BY DATE(ScanDateTime)
                ORDER BY ScanDate DESC
            ";
            $filename = "daily_summary_" . date('Y-m-d_His') . ".csv";
            break;
            
        case 'all':
        default:
            $query = "
                SELECT 
                    ScanID,
                    Barcode,
                    ProductID,
                    ProductName,
                    ScannedBy,
                    DeviceID,
                    ScanDateTime,
                    Location,
                    Quantity,
                    Status,
                    Notes,
                    UpdatedAt
                FROM InventoryScans
                WHERE DATE(ScanDateTime) BETWEEN '$startDate' AND '$endDate'
                ORDER BY ScanDateTime DESC
            ";
            $filename = "all_inventory_" . date('Y-m-d_His') . ".csv";
            break;
    }
    
    // Execute query on MySQL
    $stmt = $pdo->query($query);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($data)) {
        die("No data found for export");
    }
    
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Open output stream
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Write header row
    fputcsv($output, array_keys($data[0]));
    
    // Write data rows
    foreach ($data as $row) {
        // No need to convert DateTime objects in MySQL
        fputcsv($output, $row);
    }
    
    // Add summary footer
    fputcsv($output, []);
    fputcsv($output, ['Export Summary']);
    fputcsv($output, ['Generated', date('Y-m-d H:i:s')]);
    fputcsv($output, ['Total Records', count($data)]);
    fputcsv($output, ['Export Type', ucfirst($type)]);
    if ($type !== 'floor') {
        fputcsv($output, ['Date Range', "$startDate to $endDate"]);
    }
    
    fclose($output);
    exit();
    
} catch (Exception $e) {
    die("Export failed: " . $e->getMessage());
}
?>
