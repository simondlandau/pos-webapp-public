<?php
require_once 'config.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'scan':
            handleScan($pdo, $sqlsrv_pdo, $input);
            break;
            
        case 'lookup':
            handleLookup($sqlsrv_pdo, $input);
            break;
            
        case 'stats':
            handleStats($pdo);
            break;
            
        case 'history':
            handleHistory($pdo, $input);
            break;
            
        case 'sync':
            handleSync($pdo, $sqlsrv_pdo, $input);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Handle barcode scan and record to MySQL inventory
 */
function handleScan($mysql_pdo, $mssql_pdo, $input) {
    $barcode = $input['barcode'] ?? '';
    $device = $input['device'] ?? 'Unknown';
    $timestamp = $input['timestamp'] ?? date('Y-m-d H:i:s');
    $location = $input['location'] ?? null;
    $quantity = $input['quantity'] ?? 1; // Support bulk scanning
    
    if (empty($barcode)) {
        throw new Exception('Barcode is required');
    }
    
    // Clean barcode - remove any whitespace
    $barcode = trim($barcode);
    
    // Lookup product in MSSQL (read-only)
    $stmt = $mssql_pdo->prepare("
        SELECT TOP 1
            b.BC_REF as Barcode,
            s.PM_DESC as name,
            b.BC_PART as sku,
            s.PM_RRP as price,
            ISNULL(l.LO_ONHAND, 0) as stock
        FROM dbo.BARCODES b
        INNER JOIN dbo.STOCKMST s ON b.BC_PART = s.PM_PART
        LEFT JOIN dbo.LOCATION l ON b.BC_PART = l.LO_PART
        WHERE b.BC_REF = ?
    ");
    
    $stmt->execute([$barcode]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($product) {
        // Record the scan in MySQL InventoryScans table
        $insertStmt = $mysql_pdo->prepare("
            INSERT INTO InventoryScans 
            (Barcode, ProductID, ProductName, ScannedBy, DeviceID, ScanDateTime, Location, Status, Quantity)
            VALUES (?, ?, ?, ?, ?, NOW(), ?, 'OnFloor', ?)
        ");
        
        $insertStmt->execute([
            $barcode,
            $product['sku'],
            $product['name'],
            'Mobile Scanner',
            substr($device, 0, 100),
            $location,
            $quantity
        ]);
        
        echo json_encode([
            'success' => true,
            'product' => [
                'barcode' => $barcode,
                'name' => $product['name'],
                'sku' => $product['sku'],
                'price' => number_format((float)$product['price'], 2),
                'stock' => (int)$product['stock']
            ],
            'quantity' => $quantity,
            'scan_id' => $mysql_pdo->lastInsertId()
        ]);
    } else {
        // Product not found in MSSQL
        echo json_encode([
            'success' => false,
            'error' => 'Product not found in database',
            'barcode' => $barcode
        ]);
    }
}

/**
 * Lookup product without recording scan
 */
function handleLookup($mssql_pdo, $input) {
    $barcode = $input['barcode'] ?? $_GET['barcode'] ?? '';
    
    if (empty($barcode)) {
        throw new Exception('Barcode is required');
    }
    
    // Clean barcode
    $barcode = trim($barcode);
    
    $stmt = $mssql_pdo->prepare("
        SELECT TOP 1
            b.BC_REF as Barcode,
            s.PM_DESC as name,
            b.BC_PART as sku,
            s.PM_RRP as price,
            ISNULL(l.LO_ONHAND, 0) as stock
        FROM dbo.BARCODES b
        INNER JOIN dbo.STOCKMST s ON b.BC_PART = s.PM_PART
        LEFT JOIN dbo.LOCATION l ON b.BC_PART = l.LO_PART
        WHERE b.BC_REF = ?
    ");
    
    $stmt->execute([$barcode]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($product) {
        echo json_encode([
            'success' => true,
            'product' => [
                'barcode' => $product['Barcode'],
                'name' => $product['name'],
                'sku' => $product['sku'],
                'price' => number_format((float)$product['price'], 2),
                'stock' => (int)$product['stock']
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Product not found'
        ]);
    }
}

/**
 * Get statistics from MySQL
 */
function handleStats($mysql_pdo) {
    // Today's scans
    $stmt = $mysql_pdo->query("
        SELECT 
            COUNT(*) as today_scans,
            COUNT(DISTINCT Barcode) as unique_products
        FROM InventoryScans
        WHERE DATE(ScanDateTime) = CURDATE()
        AND Status = 'OnFloor'
    ");
    $today = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Total inventory on floor
    $stmt = $mysql_pdo->query("
        SELECT 
            SUM(Quantity) as total_items,
            COUNT(DISTINCT Barcode) as unique_items
        FROM InventoryScans
        WHERE Status = 'OnFloor'
    ");
    $total = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // This week's activity
    $stmt = $mysql_pdo->query("
        SELECT 
            COUNT(*) as week_scans
        FROM InventoryScans
        WHERE ScanDateTime >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $week = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'stats' => [
            'today_scans' => (int)$today['today_scans'],
            'total_items' => (int)$total['total_items'],
            'unique_products' => (int)$today['unique_products'],
            'unique_items' => (int)$total['unique_items'],
            'week_scans' => (int)$week['week_scans']
        ]
    ]);
}

/**
 * Get scan history from MySQL
 */
function handleHistory($mysql_pdo, $input) {
    $limit = $input['limit'] ?? 50;
    $offset = $input['offset'] ?? 0;
    $barcode = $input['barcode'] ?? $_GET['barcode'] ?? null;
    
    if ($barcode) {
        // Get history for specific barcode
        $stmt = $mysql_pdo->prepare("
            SELECT 
                ScanID,
                Barcode,
                ProductName,
                ScannedBy,
                ScanDateTime,
                Quantity,
                Status,
                Location,
                Notes
            FROM InventoryScans
            WHERE Barcode = ?
            ORDER BY ScanDateTime DESC
        ");
        $stmt->execute([$barcode]);
    } else {
        // Get all history with pagination
        $stmt = $mysql_pdo->prepare("
            SELECT 
                ScanID,
                Barcode,
                ProductName,
                ScannedBy,
                ScanDateTime,
                Quantity,
                Status,
                Location
            FROM InventoryScans
            ORDER BY ScanDateTime DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$limit, $offset]);
    }
    
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'history' => $history
    ]);
}

/**
 * Sync offline scans
 */
function handleSync($mysql_pdo, $mssql_pdo, $input) {
    $scans = $input['scans'] ?? [];
    $synced = 0;
    $errors = [];
    
    foreach ($scans as $scan) {
        try {
            $barcode = trim($scan['barcode'] ?? '');
            
            if (empty($barcode)) continue;
            
            // Lookup product in MSSQL
            $stmt = $mssql_pdo->prepare("
                SELECT TOP 1
                    b.BC_REF as Barcode,
                    s.PM_DESC as name,
                    b.BC_PART as sku
                FROM dbo.BARCODES b
                INNER JOIN dbo.STOCKMST s ON b.BC_PART = s.PM_PART
                WHERE b.BC_REF = ?
            ");
            
            $stmt->execute([$barcode]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($product) {
                // Insert scan record into MySQL
                $insertStmt = $mysql_pdo->prepare("
                    INSERT INTO InventoryScans 
                    (Barcode, ProductID, ProductName, ScannedBy, DeviceID, ScanDateTime, Status, Quantity)
                    VALUES (?, ?, ?, ?, ?, ?, 'OnFloor', 1)
                ");
                
                $insertStmt->execute([
                    $barcode,
                    $product['sku'],
                    $product['name'],
                    'Mobile Scanner (Synced)',
                    $scan['device'] ?? 'Unknown',
                    $scan['timestamp'] ?? date('Y-m-d H:i:s')
                ]);
                
                $synced++;
            } else {
                $errors[] = [
                    'barcode' => $barcode,
                    'error' => 'Product not found'
                ];
            }
        } catch (Exception $e) {
            $errors[] = [
                'barcode' => $scan['barcode'] ?? 'unknown',
                'error' => $e->getMessage()
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'synced' => $synced,
        'errors' => $errors
    ]);
}

/**
 * Mark items as sold (called from POS or manually)
 * This updates the MySQL InventoryScans table
 */
function markAsSold($mysql_pdo, $barcode, $quantity = 1, $notes = null) {
    try {
        $stmt = $mysql_pdo->prepare("
            CALL sp_MarkItemsSold(?, ?, ?)
        ");
        
        $stmt->execute([$barcode, $quantity, $notes]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error marking items as sold: " . $e->getMessage());
        return false;
    }
}
?>
