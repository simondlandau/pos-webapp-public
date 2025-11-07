<?php
// Debug & error reporting - but don't display to output
ini_set('display_errors', 0);  // ✅ Changed to 0 to prevent output corruption
error_reporting(E_ALL);
ini_set('log_errors', 1);  // ✅ Log errors to PHP error log instead

require_once 'config.php';

// ✅ Start output buffering to prevent any accidental output
ob_start();

header('Content-Type: application/json');

// ✅ Enable debug logging
$logFile = __DIR__ . '/logs/debug_save.log';

// Helper: convert all keys to strings
function forceStringKeys(array $arr): array {
    return array_combine(array_map('strval', array_keys($arr)), array_values($arr));
}

try {
    $rawInput = file_get_contents('php://input');
    
    // ✅ Log the incoming request (suppress any output with @)
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'method' => $_SERVER['REQUEST_METHOD'],
        'input' => $rawInput
    ];
    @file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND);
    
    if (!$rawInput) throw new Exception('No data received');
    
    $data = json_decode($rawInput, true);
    
    // ✅ Log decoded data
    @file_put_contents($logFile, "Decoded data: " . print_r($data, true) . "\n", FILE_APPEND);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON decode error: ' . json_last_error_msg());
    }

    // Convert arrays to JSON strings for storage, forcing string keys
    $cashDrawer = json_encode(
        array_combine(
            array_map('strval', array_keys($data['cashDrawer'] ?? [])),
            array_values($data['cashDrawer'] ?? [])
        )
    );
    $changeBags   = json_encode($data['changeBags']   ?? []);
    $systemCount  = json_encode($data['systemCount']  ?? []);
    $systemValue  = json_encode($data['systemValue']  ?? []);

    // Other numeric fields
    $floatCurrent   = $data['floatCurrent']   ?? 0;
    $floatPrevious  = $data['floatPrevious']  ?? 0;
    $floatBalance   = $data['floatBalance']   ?? 0;
    $lodge          = $data['lodge']          ?? 0;
    $sales          = $data['sales']          ?? 0;
    $zCount         = $data['zCount']         ?? 0;
    $cashSales      = $data['cashSales']      ?? 0;
    $allSales       = $data['allSales']       ?? 0;
    $yesterdaySales = $data['yesterdaySales'] ?? 0;

    $sql = "
    INSERT INTO cash_reconciliation
        (date_recorded, 
         cash_drawer, change_bags, system_count, system_value,
         float_current, float_previous, float_balance,
         lodge, sales, z_count, cash_sales, all_sales, yesterday_sales)
    VALUES
        (NOW(),
         :cash_drawer, :change_bags, :system_count, :system_value,
         :float_current, :float_previous, :float_balance,
         :lodge, :sales, :z_count, :cash_sales, :all_sales, :yesterday_sales)
    ON DUPLICATE KEY UPDATE
        cash_drawer = VALUES(cash_drawer),
        change_bags = VALUES(change_bags),
        system_count = VALUES(system_count),
        system_value = VALUES(system_value),
        float_current = VALUES(float_current),
        float_previous = VALUES(float_previous),
        float_balance = VALUES(float_balance),
        lodge = VALUES(lodge),
        sales = VALUES(sales),
        z_count = VALUES(z_count),
        cash_sales = VALUES(cash_sales),
        all_sales = VALUES(all_sales),
        yesterday_sales = VALUES(yesterday_sales)
    ";

    $params = [
        ':cash_drawer'   => $cashDrawer,
        ':change_bags'   => $changeBags,
        ':system_count'  => $systemCount,
        ':system_value'  => $systemValue,
        ':float_current' => $floatCurrent,
        ':float_previous'=> $floatPrevious,
        ':float_balance' => $floatBalance,
        ':lodge'         => $lodge,
        ':sales'         => $sales,
        ':z_count'       => $zCount,
        ':cash_sales'    => $cashSales,
        ':all_sales'     => $allSales,
        ':yesterday_sales'=> $yesterdaySales,
    ];
    
    // ✅ Log SQL params
    @file_put_contents($logFile, "SQL Params: " . print_r($params, true) . "\n", FILE_APPEND);
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    $rowCount = $stmt->rowCount();
    
    // ✅ Log result
    @file_put_contents($logFile, "Rows affected: $rowCount\n\n", FILE_APPEND);
    
    // ✅ Clean output buffer and send JSON
    ob_clean();
    echo json_encode(['success' => true, 'rowsAffected' => $rowCount]);
    
} catch (Exception $e) {
    // ✅ Log errors
    @file_put_contents($logFile, "ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n", FILE_APPEND);
    
    // ✅ Clean output buffer and send error JSON
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

// ✅ End output buffering
ob_end_flush();
