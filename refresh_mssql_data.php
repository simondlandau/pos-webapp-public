<?php
// Debug version to troubleshoot MSSQL refresh issues
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';

header('Content-Type: application/json');

// Log requests
$logData = [
    'timestamp' => date('Y-m-d H:i:s'),
    'request' => 'MSSQL refresh'
];
file_put_contents('debug_mssql.log', json_encode($logData) . "\n", FILE_APPEND);

try {
    // Test MSSQL connection first
    if (!$sqlsrv_pdo) {
        throw new Exception('MSSQL connection failed');
    }

    file_put_contents('debug_mssql.log', "MSSQL connection OK\n", FILE_APPEND);

    // Previous Day's Float (fallback to earlier days if needed)
    $PrevFloatHeld = 0.0;
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
        if ($row) $PrevFloatHeld = (float)($row['PrevFloatHeld'] ?? 0);
        file_put_contents('debug_mssql.log', "PrevFloatHeld Query OK: $PrevFloatHeld\n", FILE_APPEND);
    } catch (Exception $e) {
        file_put_contents('debug_mssql.log', "PrevFloatHeld Query failed: " . $e->getMessage() . "\n", FILE_APPEND);
        $PrevFloatHeld = 0.0;
    }

    // All Other Sales (AE) - PaymentNo != '01'
    $AE = 0.0;
    try {
        $stmt = $sqlsrv_pdo->query("
            SELECT SUM(t.PN_CURR) AS AE
            FROM svp.dbo.TENDER t
            WHERE CAST(t.dtTimeStamp AS DATE) = CAST(GETDATE() AS DATE)
              AND t.PN_TYPE <> '01'
        ");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) $AE = (float)($row['AE'] ?? 0);
        file_put_contents('debug_mssql.log', "AE Query OK: $AE\n", FILE_APPEND);
    } catch (Exception $e) {
        file_put_contents('debug_mssql.log', "AE Query failed: " . $e->getMessage() . "\n", FILE_APPEND);
        $AE = 0.0;
    }

    // Cash Payments (CP) - PN_RECTYPE = '13'
    $CP = 0.0;
    try {
        $stmt = $sqlsrv_pdo->query("
            SELECT SUM(t.PN_CURR) AS CP
            FROM svp.dbo.TENDER t
            WHERE CAST(t.dtTimeStamp AS DATE) = CAST(GETDATE() AS DATE)
              AND t.PN_RECTYPE = '13'
        ");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) $CP = (float)($row['CP'] ?? 0);
        file_put_contents('debug_mssql.log', "CP Query OK: $CP\n", FILE_APPEND);
    } catch (Exception $e) {
        file_put_contents('debug_mssql.log', "CP Query failed: " . $e->getMessage() . "\n", FILE_APPEND);
        $CP = 0.0;
    }

    // Cash Sales (CS) - PaymentNo = '01'
    $CS = 0.0;
    try {
        $stmt = $sqlsrv_pdo->query("
            SELECT SUM(t.PN_CURR) AS CashSales
            FROM svp.dbo.TENDER t
            WHERE CAST(t.dtTimeStamp AS DATE) = CAST(GETDATE() AS DATE)
              AND t.PN_TYPE = '01'
        ");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) $CS = (float)($row['CashSales'] ?? 0);
        file_put_contents('debug_mssql.log', "CS Query OK: $CS\n", FILE_APPEND);
    } catch (Exception $e) {
        file_put_contents('debug_mssql.log', "CS Query failed: " . $e->getMessage() . "\n", FILE_APPEND);
        $CS = 0.0;
    }

    // All Sales
    $allSales = 0.0;
    try {
        $stmt = $sqlsrv_pdo->query("
            SELECT SUM(s.SN_Actual) AS Z_Sales
            FROM svp.dbo.SALES s
            WHERE dtTimeStamp BETWEEN CONCAT(CAST(GETDATE() AS DATE),' 09:00:00') 
                                AND CONCAT(CAST(GETDATE() AS DATE),' 17:30:00')
        ");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) $allSales = (float)($row['Z_Sales'] ?? 0);
        file_put_contents('debug_mssql.log', "All Sales Query OK: $allSales\n", FILE_APPEND);
    } catch (Exception $e) {
        file_put_contents('debug_mssql.log', "All Sales Query failed: " . $e->getMessage() . "\n", FILE_APPEND);
        throw $e;
    }

    // Loyalty - PaymentNo = '10'
    $loyalty = 0.0;
    try {
        $stmt = $sqlsrv_pdo->query("
            SELECT SUM(t.PN_CURR) AS Loyalty
            FROM svp.dbo.TENDER t
            WHERE CAST(t.dtTimeStamp AS DATE) = CAST(GETDATE() AS DATE)
              AND t.PN_TYPE = '10'
        ");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) $loyalty = (float)($row['Loyalty'] ?? 0);
        file_put_contents('debug_mssql.log', "Loyalty Query OK: $loyalty\n", FILE_APPEND);
    } catch (Exception $e) {
        file_put_contents('debug_mssql.log', "Loyalty Query failed: " . $e->getMessage() . "\n", FILE_APPEND);
        $loyalty = 0.0;
    }

    // Donations
    $donations = 0.0;
    try {
        $stmt = $sqlsrv_pdo->query("
            SELECT SUM(s.SN_Actual) AS Donations 
            FROM svp.dbo.SALES s
            WHERE dtTimeStamp BETWEEN CONCAT(CAST(GETDATE() AS DATE),' 09:00:00') 
                                AND CONCAT(CAST(GETDATE() AS DATE),' 17:30:00')
              AND SN_ITEM = 'DONAT01' 
            GROUP BY SN_ITEM
        ");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) $donations = (float)($row['Donations'] ?? 0);
        file_put_contents('debug_mssql.log', "Donations Query OK: $donations\n", FILE_APPEND);
    } catch (Exception $e) {
        file_put_contents('debug_mssql.log', "Donations Query failed: " . $e->getMessage() . "\n", FILE_APPEND);
        $donations = 0.0;
    }

    // Current Running Total (All Tender + Donations)
    $currentRunningTotal = 0.0;
    try {
        $stmt = $sqlsrv_pdo->query("
            SELECT SUM(t.PN_CURR) AS AllTender
            FROM svp.dbo.TENDER t
            WHERE CAST(t.dtTimeStamp AS DATE) = CAST(GETDATE() AS DATE)
        ");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $allTender = $row ? (float)($row['AllTender'] ?? 0) : 0;
        $currentRunningTotal = $allTender + $donations;
        file_put_contents('debug_mssql.log', "Current Running Total OK: $currentRunningTotal (AllTender: $allTender + Donations: $donations)\n", FILE_APPEND);
    } catch (Exception $e) {
        file_put_contents('debug_mssql.log', "Current Running Total Query failed: " . $e->getMessage() . "\n", FILE_APPEND);
        $currentRunningTotal = 0.0;
    }

    // Yesterday's Sales
    $yesterdaySales = 0.0;
    try {
        $stmt = $sqlsrv_pdo->query("
            SELECT 
                CAST(t.dtTimeStamp AS DATE) AS SalesDate,
                SUM(t.PN_CURR) AS YesterdaySales
            FROM svp.dbo.TENDER t
            WHERE CAST(t.dtTimeStamp AS DATE) < CAST(GETDATE() AS DATE)
              AND CAST(t.dtTimeStamp AS TIME) BETWEEN '09:00:00' AND CAST(GETDATE() AS TIME)
              AND CAST(t.dtTimeStamp AS DATE) = (
                  SELECT MAX(CAST(dtTimeStamp AS DATE))
                  FROM svp.dbo.TENDER
                  WHERE CAST(dtTimeStamp AS DATE) < CAST(GETDATE() AS DATE)
                    AND PN_TYPE IN ('01', '04', '10')
              )
            GROUP BY CAST(t.dtTimeStamp AS DATE)
        ");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) $yesterdaySales = (float)($row['YesterdaySales'] ?? 0);
        file_put_contents('debug_mssql.log', "Yesterday Sales Query OK: $yesterdaySales\n", FILE_APPEND);
    } catch (Exception $e) {
        file_put_contents('debug_mssql.log', "Yesterday Sales Query failed: " . $e->getMessage() . "\n", FILE_APPEND);
        $yesterdaySales = 0.0;
    }

    // Return all data
    $response = [
        'success' => true,
        'PrevFloatHeld' => $PrevFloatHeld,
        'AE' => $AE,
        'CP' => $CP,
        'CS' => $CS,
        'allSales' => $allSales,
        'loyalty' => $loyalty,
        'donations' => $donations,
        'currentRunningTotal' => $currentRunningTotal,
        'yesterdaySales' => $yesterdaySales,
        'timestamp' => date('Y-m-d H:i:s')
    ];

    file_put_contents('debug_mssql.log', "Response: " . json_encode($response) . "\n", FILE_APPEND);
    echo json_encode($response);

} catch (Exception $e) {
    $error = $e->getMessage();
    file_put_contents('debug_mssql.log', "ERROR: " . $error . "\n", FILE_APPEND);
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $error,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>
