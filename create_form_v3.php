<?php
// Debug & error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';
header('Content-Type: application/json');

// Helper: convert all keys to strings
function forceStringKeys(array $arr): array {
    return array_combine(array_map('strval', array_keys($arr)), array_values($arr));
}

try {
    $rawInput = file_get_contents('php://input');
    if (!$rawInput) throw new Exception('No data received');

    $data = json_decode($rawInput, true);
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

// --- SQL INSERT / UPDATE (unchanged) ---
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

$stmt = $pdo->prepare($sql);
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
$stmt->execute($params);
    echo json_encode(['success' => true, 'rowsAffected' => $stmt->rowCount()]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

