<?php
require_once 'config.php';
header('Content-Type: application/json');

try {
    // Get the last record from the database
    $stmt = $pdo->prepare("
        SELECT cash_drawer 
        FROM cash_reconciliation 
        WHERE DATE(date_recorded) < CURDATE()
        ORDER BY date_recorded DESC 
        LIMIT 1
    ");
    $stmt->execute();
    $lastRecord = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Extract small denominations (0.05 to 2.00)
    $floatDenoms = ["0.05", "0.10", "0.20", "0.50", "1.00", "2.00"];
    $carryForwardValues = [];
    
    if ($lastRecord && !empty($lastRecord['cash_drawer'])) {
        $cashDrawerData = json_decode($lastRecord['cash_drawer'], true);
        
        if (is_array($cashDrawerData)) {
            foreach ($floatDenoms as $denom) {
                $carryForwardValues[$denom] = isset($cashDrawerData[$denom]) ? (int)$cashDrawerData[$denom] : 0;
            }
        }
    }
    
    // If no previous data, initialize with zeros
    if (empty($carryForwardValues)) {
        foreach ($floatDenoms as $denom) {
            $carryForwardValues[$denom] = 0;
        }
    }
    
    // Create initial cash_drawer structure for new day
    $newCashDrawer = $carryForwardValues;
    
    // Add zeros for larger denominations
    $largerDenoms = ["5.00", "10.00", "20.00", "50.00", "100.00"];
    foreach ($largerDenoms as $denom) {
        $newCashDrawer[$denom] = 0;
    }
    
    // Initialize empty change bags
    $newChangeBags = [
        "5.00" => 0,
        "10.00" => 0,
        "20.00" => 0,
        "50.00" => 0,
        "100.00" => 0
    ];
    
    // Calculate initial float current from carried forward values
    $initialFloatCurrent = 0;
    foreach ($floatDenoms as $denom) {
        $initialFloatCurrent += $carryForwardValues[$denom] * floatval($denom);
    }
    
    // Get prevFloatHeld from MSSQL
    $prevFloatHeld = 0.0;
    try {
        $stmt = $sqlsrv_pdo->query("
            SELECT TOP 1 SUM(cdl.FloatHeld) AS PrevFloatHeld
            FROM CashDecLines cdl
            INNER JOIN CashDecHeader cdh ON cdl.CashDecRef = cdh.CashDecRef
            WHERE CAST(cdh.dtTimeStamp AS DATE) < CAST(GETDATE() AS DATE)
              AND cdl.PaymentNo = '01'
            GROUP BY CAST(cdh.dtTimeStamp AS DATE)
            ORDER BY CAST(cdh.dtTimeStamp AS DATE) DESC
        ");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $prevFloatHeld = $row ? (float)($row['PrevFloatHeld'] ?? 0) : 0;
    } catch (PDOException $e) {
        error_log("Previous Float query failed: " . $e->getMessage());
    }
    
  // Create new record for today or update if exists
    // Note: recon_day is a GENERATED column, so we don't include it
    // It will be automatically calculated from date_recorded
    $insertStmt = $pdo->prepare("
        INSERT INTO cash_reconciliation (
            date_recorded,
            cash_drawer,
            change_bags,
            system_count,
            system_value,
            float_current,
            float_balance,
            lodge,
            sales,
            z_count,
            cash_sales,
            all_sales,
            yesterday_sales
        ) VALUES (
            NOW(),
            :cash_drawer,
            :change_bags,
            :system_count,
            :system_value,
            :float_current,
            :float_balance,
            0,
            0,
            0,
            0,
            0,
            0
        )
        ON DUPLICATE KEY UPDATE
            date_recorded = NOW(),
            cash_drawer = :cash_drawer,
            change_bags = :change_bags,
            system_count = :system_count,
            system_value = :system_value,
            float_current = :float_current,
            float_balance = :float_balance,
            lodge = 0,
            sales = 0,
            z_count = 0,
            cash_sales = 0,
            all_sales = 0,
            yesterday_sales = 0
    ");
    
    // Initialize system_count and system_value with the carried forward values
    $systemCount = $carryForwardValues;
    $systemValue = [];
    foreach ($floatDenoms as $denom) {
        $systemValue[$denom] = $carryForwardValues[$denom] * floatval($denom);
    }
    foreach ($largerDenoms as $denom) {
        $systemCount[$denom] = 0;
        $systemValue[$denom] = 0;
    }
    
    $floatBalance = $initialFloatCurrent - $prevFloatHeld;
    
    $insertStmt->execute([
        ':cash_drawer' => json_encode($newCashDrawer),
        ':change_bags' => json_encode($newChangeBags),
        ':system_count' => json_encode($systemCount),
        ':system_value' => json_encode($systemValue),
        ':float_current' => $initialFloatCurrent,
        ':float_balance' => $floatBalance
    ]);
    
    echo json_encode([
        'success' => true,
        'carryForward' => $carryForwardValues,
        'floatCurrent' => $initialFloatCurrent,
        'floatBalance' => $floatBalance,
        'prevFloatHeld' => $prevFloatHeld
    ]);
    
} catch (Exception $e) {
    error_log("Start of Day error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
