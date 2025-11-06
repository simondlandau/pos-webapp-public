<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once "config.php"; // contains $sqlsrv_pdo + mailer config

ob_start();
include "header.php";
$headerHtml = ob_get_clean();

ob_start();
include "footer.php";
$footerHtml = ob_get_clean();

// ------------------ Step 1: Check MSSQL CashDecHeader has records today ------------------
try {
    $stmt = $sqlsrv_pdo->query("
        SELECT TOP 1 1 
        FROM CashDecHeader 
        WHERE CAST(dtTimeStamp AS DATE) = CAST(GETDATE() AS DATE)
    ");
    $hasTender = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$hasTender) {
   echo $headerHtml; // Include header for consistent styling
    ?>
    <div class="modal fade" id="eodErrorModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header bg-warning">
            <h5 class="modal-title">
              <i class="bi bi-exclamation-triangle"></i> EOD Not Run
            </h5>
          </div>
          <div class="modal-body text-center py-4">
            <p class="mb-3">EOD has not been run yet today.</p>
            <p class="mb-0">Please try again later or register for email updates.</p>
          </div>
          <div class="modal-footer justify-content-center">
            <a href="reports.php" class="btn btn-primary">Return to Menu</a>
          </div>
        </div>
      </div>
    </div>
    
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        var modal = new bootstrap.Modal(document.getElementById('eodErrorModal'));
        modal.show();
      });
    </script>
    <?php
    echo $footerHtml;
    exit;
}
} catch (PDOException $e) {
    echo "<p style='color:red;'>MSSQL check failed: {$e->getMessage()}</p>";
    exit;
}

// ------------------ Step 1.5: MSSQL Aggregates ------------------
$Loyalty = $Donations = $zCount = $CP = $cashSales = $allOtherSales = $currentFloat = $Lodge = $prevFloatHeld = $Difference = 0.0;

function euro($value) {
    return "€" . number_format($value, 2);
}

// Loyalty
try {
    $stmt = $sqlsrv_pdo->query("
        SELECT SUM(cdl.TillTotal) AS Loyalty
        FROM CashDecLines cdl
        INNER JOIN CashDecHeader cdh ON cdl.CashDecRef = cdh.CashDecRef
        WHERE CAST(cdh.dtTimeStamp AS DATE) = CAST(GETDATE() AS DATE)
          AND cdl.PaymentNo = '10'
    ");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $loyalty = (float)($row['Loyalty'] ?? 0);
} catch (PDOException $e) {
    echo "<p style='color:red;'>Loyalty query failed: {$e->getMessage()}</p>";
}

// Donations
try {
    $stmt = $sqlsrv_pdo->query("
        SELECT SUM(SN_Actual) AS Donations 
        FROM SALES 
        WHERE PostedDate = CONCAT(CAST(GETDATE() AS DATE),' 00:00:00') 
          AND SN_ITEM = 'DONAT01'
        GROUP BY SN_ITEM
    ");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $donations = (float)($row['Donations'] ?? 0);
} catch (PDOException $e) {
    echo "<p style='color:red;'>Donations query failed: {$e->getMessage()}</p>";
}

// Previous Float
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
    echo "<p style='color:red;'>Previous Float query failed: {$e->getMessage()}</p>";
}

// All Other Sales
try {
    $stmt = $sqlsrv_pdo->query("
        SELECT SUM(cdl.TillTotal) AS AE
        FROM CashDecLines cdl
        INNER JOIN CashDecHeader cdh ON cdl.CashDecRef = cdh.CashDecRef
        WHERE CAST(cdh.dtTimeStamp AS DATE) = CAST(GETDATE() AS DATE)
          AND cdl.PaymentNo = '04'
    ");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $allOtherSales = (float)($row['AE'] ?? 0);
} catch (PDOException $e) {
    echo "<p style='color:red;'>All Other Sales query failed: {$e->getMessage()}</p>";
}

// Cash Payments (CP) - PN_RECTYPE = '13'
$CP = 0.0;
try {
    $stmt = $sqlsrv_pdo->query("
         SELECT SUM(t.PN_CURR) AS CP
        FROM svp.dbo.TENDER t
        WHERE CAST(t.dtTimeStamp AS DATE) = CAST(GETDATE() AS DATE)
          AND t.PN_RECTYPE  ='13'

    ");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $CP = (float)($row['CP'] ?? 0);
} catch (PDOException $e) {
    error_log("Cash Payments query failed: " . $e->getMessage());
}

// Cash Sales
try {
    $stmt = $sqlsrv_pdo->query("
        SELECT SUM(t.PN_CURR) AS CashSales
        FROM svp.dbo.TENDER t
        WHERE CAST(t.dtTimeStamp AS DATE) = CAST(GETDATE() AS DATE)
          AND t.PN_TYPE  = '01'
    ");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $cashSales = (float)($row['CashSales'] ?? 0);
} catch (PDOException $e) {
    echo "<p style='color:red;'>Cash Sales query failed: {$e->getMessage()}</p>";
}

// Current Float
try {
    $stmt = $sqlsrv_pdo->query("
        SELECT SUM(cdl.FloatHeld) AS FloatHeld
        FROM CashDecLines cdl
        INNER JOIN CashDecHeader cdh ON cdl.CashDecRef = cdh.CashDecRef
        WHERE CAST(cdh.dtTimeStamp AS DATE) = CAST(GETDATE() AS DATE)
          AND cdl.PaymentNo = '01'
    ");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $currentFloat = (float)($row['FloatHeld'] ?? 0);
} catch (PDOException $e) {
    echo "<p style='color:red;'>Current Float query failed: {$e->getMessage()}</p>";
}

// Lodge
try {
    $stmt = $sqlsrv_pdo->query("
        SELECT SUM(cdl.Lodged) AS Lodge
        FROM CashDecLines cdl
        INNER JOIN CashDecHeader cdh ON cdl.CashDecRef = cdh.CashDecRef
        WHERE CAST(cdh.dtTimeStamp AS DATE) = CAST(GETDATE() AS DATE)
          AND cdl.PaymentNo = '01'
    ");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $lodge = (float)($row['Lodge'] ?? 0);
} catch (PDOException $e) {
    echo "<p style='color:red;'>Lodge query failed: {$e->getMessage()}</p>";
}

try {
    // Difference from CashDecLines (PaymentNo = '01')
    $stmt = $sqlsrv_pdo->query("
        SELECT SUM(cdl.Difference) AS Difference
        FROM CashDecLines cdl
        INNER JOIN CashDecHeader cdh ON cdl.CashDecRef = cdh.CashDecRef
        WHERE CAST(cdh.dtTimeStamp AS DATE) = CAST(GETDATE() AS DATE)
          AND cdl.PaymentNo = '01'
    ");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $Difference = (float)($row['Difference'] ?? 0);
} catch (PDOException $e) {
    error_log("Difference query failed: " . $e->getMessage());
}

try {
    // Difference Reason from CashDecHeader)
    $stmt = $sqlsrv_pdo->query("
SELECT cdh.ReasonText AS Reason
        FROM svp.dbo.CashDecHeader cdh
        WHERE CAST(cdh.dtTimeStamp AS DATE) = CAST(GETDATE() AS DATE)
    ");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $Reason = ($row['Reason'] ?? 0);
} catch (PDOException $e) {
    error_log("Reason query failed: " . $e->getMessage());
}

// Z Count Calculation
$zCount = (($currentFloat - $prevFloatHeld) + $allOtherSales + $lodge);

// All Sales Calculation
$allSales = ($cashSales + $allOtherSales + $CP);

// ------------------ Step 2: Get list of subscribed users from MySQL ------------------
try {
    $stmt = $pdo->prepare("SELECT forename, email FROM svp.users WHERE receive = 1");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    echo "<p style='color:red;'>Failed to fetch users: {$e->getMessage()}</p>";
}

// ------------------ Output Results ------------------
echo $headerHtml;

echo "<div class='container mt-4'>
<h3>Daily Financial Data Summary</h3>
<table border='1' cellpadding='8' cellspacing='0' style='border-collapse: collapse; width:60%;'>
<thead style='background:#efefef;'>
<tr><th>Metric</th><th>Value</th></tr>
</thead>
<tbody>
<tr><td><b>Current Float</b></td><td>" . euro($currentFloat) . "</td></tr>
<tr><td><b>Z Count</b></td><td>" . euro($zCount) . "</td></tr>
<tr><td><b>Cash Sales</b></td><td>" . euro($cashSales) . "</td></tr>
<tr><td><b>All Other Sales</b></td><td>" . euro($allOtherSales) . "</td></tr>
<tr><td><b>Donations</b></td><td>" . euro($Donations) . "</td></tr>
<tr><td><b>Loyalty</b></td><td>" . euro($Loyalty) . "</td></tr>
<tr><td><b>Cash Payments</b></td><td>" . euro($CP) . "</td></tr>
<tr><td><b>All Sales</b></td><td>" . euro($allSales) . "</td></tr>
<tr><td><b>Difference</b></td><td>" . euro($Difference) . "</td></tr>
<tr><td><b>Reason</b></td><td>" . ($Reason) . "</td></tr>
<tr><td><b>Lodge</b></td><td>" . euro($lodge) . "</td></tr>
<tr><td><b>Previous Day Float</b></td><td>" . euro($prevFloatHeld) . "</td></tr>
</tbody>
</table>
<br><h4>Subscribed Users (" . count($users) . ")</h4>
<ul>";
foreach ($users as $u) {
    echo "<li>{$u['forename']} ({$u['email']})</li>";
}
echo "</ul></div>";
echo "
  <div class='text-center mt-5'>
    <a href='reports.php' class='btn btn-secondary btn-lg'>
      Return to Menu
    </a>
  </div>
";

echo $footerHtml;
?>

