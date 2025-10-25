<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'config.php';

// Set page variables for header
$pageTitle = 'Cash Reconciliation v3';
$headerTitle = 'Cash Reconciliation - St. Vincents, Main Street, Letterkenny';
$additionalCSS = '
<style>
/* Table and input sizing */
table { width: 100%; }
#cash-drawer, #change-bags, #system-count { width: 75%; }
.cash-input, .bag-input { width: 50%; }
#cash-drawer td, #system-count td, #change-bags td { height:36px; vertical-align:middle; }
.float-input {
    max-width: 100px;
    display: inline-block;
}
.float-previous {
    background-color: #e0e0e0; /* light grey */
}
/* Card visuals */
.card-header { font-weight:bold; }
.float-card { max-width: 260px; }
.recon-zero { background:#d4f7d4 !important; } /* light green */
.recon-pos { background:#E4A11B !important; }  /* salmon */
.recon-neg { background:red !important; }  /* red-ish */
.sales-zero { background:#d4f7d4; } 
.sales-pos { background:#E4A11B; } 
.sales-neg { background:red; } 
/* Auto-save indicator */
.save-status {
    position: fixed;
    top: 70px;
    right: 20px;
    z-index: 1000;
    transition: all 0.3s ease;
}
.fade-out {
    opacity: 0;
}
</style>';

// ------------------ Load existing data for today ------------------
$existingData = [];
$cashDrawerData = [];

try {
    $stmt = $pdo->prepare("
        SELECT * FROM cash_reconciliation
        WHERE DATE(date_recorded) = CURDATE()
        ORDER BY date_recorded DESC
        LIMIT 1
    ");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        // Decode JSON fields safely; if already array, leave as-is
        $jsonFields = ['cash_drawer', 'change_bags', 'system_count', 'system_value'];
        foreach ($jsonFields as $field) {
            if (!empty($row[$field])) {
                if (is_string($row[$field])) {
                    $decoded = json_decode($row[$field], true);
                    $row[$field] = is_array($decoded) ? $decoded : [];
                } elseif (is_array($row[$field])) {
                    // Already decoded, leave it
                } else {
                    $row[$field] = [];
                }
            } else {
                $row[$field] = [];
            }
        }

        $existingData = $row;

        // ✅ Handle cash_drawer safely (works for JSON string or array)
        $cashDrawerRaw = $existingData['cash_drawer'] ?? '[]';
        $cashDrawerData = is_array($cashDrawerRaw) ? $cashDrawerRaw : json_decode($cashDrawerRaw, true);

        // Keep keys as formatted strings (0.10 stays as "0.10")
        if (is_array($cashDrawerData)) {
            $normalizedData = [];
            foreach ($cashDrawerData as $key => $value) {
                $normalizedData[(string)$key] = $value;
            }
            $cashDrawerData = $normalizedData;
        } else {
            $existingData = [];
            $cashDrawerData = [];
        }
    }
} catch (PDOException $e) {
    error_log("❌ Database error in cash_count_v3.php: " . $e->getMessage());
    $existingData = [];
    $cashDrawerData = [];
}

// ------------------ Extract other safe variables for front-end ------------------
$changeBagsData = $existingData['change_bags'] ?? [];
$systemCountData = $existingData['system_count'] ?? [];
$systemValueData = $existingData['system_value'] ?? [];
$floatCurrent    = $existingData['float_current'] ?? 0;
$floatBalance    = $existingData['float_balance'] ?? 0;
$lodge           = $existingData['lodge'] ?? 0;
$sales           = $existingData['sales'] ?? 0;
$zCount          = $existingData['z_count'] ?? 0;
$cashSales       = $existingData['cash_sales'] ?? 0;
$allSales        = $existingData['all_sales'] ?? 0;
$yesterdaySales  = $existingData['yesterday_sales'] ?? 0;

// ------------------ Fetch MSSQL data ------------------

// Get previous day's float (fallback to earlier days if needed)
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
    $prevFloatHeld = 0;
}

// All Other Sales (AE) - PaymentNo = '04'
$AE = 0.0;
try {
    $stmt = $sqlsrv_pdo->query("
         SELECT SUM(t.PN_CURR) AS AE
        FROM svp.dbo.TENDER t
        WHERE CAST(t.dtTimeStamp AS DATE) = CAST(GETDATE() AS DATE)
          AND t.PN_TYPE  <>'01'

    ");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $AE = (float)($row['AE'] ?? 0);
} catch (PDOException $e) {
    error_log("All Other Sales query failed: " . $e->getMessage());
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

// Cash Sales (CS) - PaymentNo = '01'
$currentCashSales = 0.0;
try {
    $stmt = $sqlsrv_pdo->query("
         SELECT SUM(t.PN_CURR) AS CashSales
        FROM svp.dbo.TENDER t
        WHERE CAST(t.dtTimeStamp AS DATE) = CAST(GETDATE() AS DATE)
          AND t.PN_TYPE  = '01'
    ");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $currentCashSales = (float)($row['CashSales'] ?? 0);
} catch (PDOException $e) {
    error_log("Cash Sales query failed: " . $e->getMessage());
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
} catch (PDOException $e) { die("All Sales query failed: ".$e->getMessage()); }

// Loyalty - PaymentNo = '10'
$loyalty = 0.0;
try {
    $stmt = $sqlsrv_pdo->query("
         SELECT SUM(t.PN_CURR) AS Loyalty
        FROM svp.dbo.TENDER t
        WHERE CAST(t.dtTimeStamp AS DATE) = CAST(GETDATE() AS DATE)
          AND t.PN_TYPE  = '10'
    ");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $loyalty = (float)($row['Loyalty'] ?? 0);
} catch (PDOException $e) { 
    error_log("Loyalty query failed: " . $e->getMessage());
    $loyalty = 0.0; 
}

// Donations
$donations = 0.0;
try {
    $stmt = $sqlsrv_pdo->query("
        SELECT SUM(s.SN_Actual) AS Donations 
        FROM svp.dbo.SALES s
        WHERE s.dtTimeStamp BETWEEN CONCAT(CAST(GETDATE() AS DATE),' 09:00:00') 
                            AND CONCAT(CAST(GETDATE() AS DATE),' 17:30:00') 
          AND s.SN_ITEM = 'DONAT01' 
        GROUP BY s.SN_ITEM
    ");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $donations = (float)($row['Donations'] ?? 0);
} catch (PDOException $e) { $donations = 0.0; }

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
    $currentRunningTotal = $allTender;
} catch (PDOException $e) { 
    error_log("Current Running Total query failed: " . $e->getMessage());
    $currentRunningTotal = 0.0; 
}
// Current Weekly Total (All Tender + Donations)
$currentWeeklyTotal = 0.0;
try {
    $stmt = $sqlsrv_pdo->query("
        SELECT SUM(t.PN_CURR) AS weekTender
        FROM svp.dbo.TENDER t
        WHERE CAST(t.dtTimeStamp AS DATE) = CAST(GETDATE() AS DATE)
    ");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $weekTender = $row ? (float)($row['weekTender'] ?? 0) : 0;
    $currentWeeklyTotal = $weekTender;
} catch (PDOException $e) { 
    error_log("Current Weekly Total query failed: " . $e->getMessage());
    $currentWeeklyTotal = 0.0; 
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
      AND t.PN_TYPE  IN ('01', '04', '10')  -- Adjust column name and values as needed
  )
GROUP BY CAST(t.dtTimeStamp AS DATE)
    ");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $yesterdaySales = $row ? (float)$row['YesterdaySales'] : 0.0;
    $salesDate = $row ? $row['SalesDate'] : null;

// Format the date nicely
if ($salesDate) {
    $salesDateTime = new DateTime($salesDate);
    $salesDayName = $salesDateTime->format('l');  // e.g., "Friday"
    $salesDateFormatted = $salesDateTime->format('M j');  // e.g., "Oct 3"
} else {
    $salesDayName = 'No Data';
    $salesDateFormatted = '';
}

} catch (PDOException $e) { 
    $yesterdaySales = 0.0; 
    $yesterdayError = $e->getMessage();
}

// Z Count calculation will be done in JavaScript using: Z = (floatCurrent + AE + lodge) - prevFloatHeld
$Z = 0.0; // Placeholder, calculated in JS

// Denominations
$denoms = [0.05,0.10,0.20,0.50,1,2,5,10,20,50,100];
$bagDenoms = [5,10,20,50,100];

// Extract existing data
$cashDrawerData  = $existingData['cash_drawer'] ?? [];
$changeBagsData  = $existingData['change_bags'] ?? [];

include 'header.php';
?>

<!-- Auto-save Status Indicator -->
<div class="save-status">
    <div class="alert alert-success fade-out" id="saveIndicator" style="display: none;">
        <i class="fas fa-check-circle me-1"></i> Saved
    </div>
</div>

<div class="container-fluid my-4">
    <!-- Back to Dashboard Button -->
    <div class="row mb-3">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <a href="dashboard.php" class="btn btn-outline-secondary">
                ← Back to Dashboard
            </a>
            <div class="text-muted small">
                Auto-saves every 150 secs. | Last saved: <span id="lastSaved">Never</span>
            </div>
        </div>
    </div>

<!-- =================== DASHBOARD TOP SECTION =================== -->
<div class="container-fluid">

  <!-- Top Row: Z Count, Cash Sales, All Sales, Donations, Loyalty, Weekly Target, Float -->
  <div class="row mb-3 g-3 align-items-stretch">

    <div class="col-md-2">
      <div class="card text-center shadow-sm h-100">
        <div class="card-header bg-primary text-white">Z Count</div>
        <div class="card-body"><h5 id="z-count">€<?= number_format($zCount ?? 0,2) ?></h5></div>
      </div>
    </div>

    <div class="col-md-2">
      <div class="card text-center shadow-sm h-100">
        <div class="card-header bg-success text-white">Cash Sales</div>
        <div class="card-body"><h5 id="cash-sales">€<?= number_format($currentCashSales,2) ?></h5></div>
      </div>
    </div>

    <div class="col-md-2">
      <div class="card text-center shadow-sm h-100">
        <div class="card-header bg-warning">All Other Sales</div>
        <div class="card-body"><h5 id="all-sales">€<?= number_format($AE,2) ?></h5></div>
      </div>
    </div>

    <div class="col-md-2">
      <div class="card text-center shadow-sm h-100">
        <div class="card-header text-white" style="background: blue;">Donations</div>
        <div class="card-body"><h5 id="donations">€<?= number_format($donations,2) ?></h5></div>
      </div>
    </div>

    <div class="col-md-2">
      <div class="card text-center shadow-sm h-100">
        <div class="card-header text-dark" style="background: #FA8072;">Loyalty</div>
        <div class="card-body"><h5 id="loyalty">€<?= number_format($loyalty,2) ?></h5></div>
      </div>
    </div>

    <!-- FLOAT -->
    <div class="col-md-2">
      <div class="card shadow-sm h-100">
        <div class="card-header bg-light fw-semibold">FLOAT</div>
        <div class="card-body p-2">
          <table class="table table-borderless mb-0 small align-middle">
            <tr>
              <th>Current</th>
              <td><input type="number" id="float-current" readonly class="form-control form-control-sm text-end"></td>
            </tr>
            <tr>
              <th>Previous</th>
              <td><input type="number" id="float-previous" step="0.01" readonly
                         class="form-control form-control-sm text-end"
                         value="<?= number_format($prevFloatHeld, 2, '.', '') ?>"></td>
            </tr>
            <tr>
              <th>Balance</th>
              <td><input type="number" id="float-balance" readonly class="form-control form-control-sm text-end"></td>
            </tr>
          </table>
        </div>
      </div>
    </div>

  </div> <!-- /row -->

  <!-- Optional: tighten vertical rhythm between sections -->
  <style>
    .card-header { padding: 0.4rem 0.75rem; }
    .card-body { padding: 0.5rem; }
    .card-body h5 { margin-bottom: 0.25rem; }
  </style>

</div>
<!-- =================== END DASHBOARD TOP SECTION =================== -->

<?php
// Calculate Weekly Target values
$firstDayOfMonth = date('Y-m-01');
$today = date('Y-m-d');
$currentDayOfWeek = date('N'); // 1 (Monday) to 7 (Sunday)

// Get sum of monthToDateTotal for this month
$monthToDateTotal = 0.0;
try {
    $stmt = $sqlsrv_pdo->query("SELECT SUM(t.PN_CURR) AS month_total
FROM svp.dbo.TENDER t
WHERE CAST(t.dtTimeStamp AS DATE)
BETWEEN CAST(DATEADD(DAY, -($currentDayOfWeek - 1), CAST(GETDATE() AS DATE)) AS DATE)
AND '$today'
");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $monthToDateTotal = $row ? (float)($row['month_total'] ?? 0) : 0;
} catch (PDOException $e) { 
    error_log("Weekly Total query failed: " . $e->getMessage());
    $monthToDateTotal = 0.0; 
}


// Calculate expected sales based on day of week (Monday = 1, Sunday = 7)
$dailyTarget = 833.33;
$expectedSales = $dailyTarget * $currentDayOfWeek;

// Calculate percentage of €5000 target
$weeklyTarget = 5000;
$targetPercentage = ($monthToDateTotal / $expectedSales) * 100;

// Determine background color
$isOnTarget = ($monthToDateTotal >= $expectedSales);
$targetCardBg = $isOnTarget ? '#28a745' : '#FFA500'; // Green or Orange
$targetCardTextColor = 'white';
?>

<!-- Weekly Target Card Row (between the two main rows) -->
<div class="row mb-3 g-3">
    <div class="col-md-2 offset-md-4">
        <div class="card text-center shadow-sm" style="background: <?= $targetCardBg ?>; color: <?= $targetCardTextColor ?>;">
            <div class="card-header" style="font-weight: 600;">Weekly Target</div>
            <div class="card-body p-2">
                <h5 class="mb-1">€<?= number_format($monthToDateTotal, 2) ?></h5>
                <small style="font-size: 0.85em;">
                    <?= number_format($targetPercentage, 1) ?>% of €<?= number_format($weeklyTarget, 0) ?>
                </small>
                <div style="font-size: 0.75em; margin-top: 5px; opacity: 0.9;">
                    Target: €<?= number_format($expectedSales, 0) ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Row below: LODGE, SALES Running Total, Cash Payments and Action Buttons -->
<div class="row mb-3 g-3 align-items-stretch">
    <div class="col-md-2">
        <div class="card text-center shadow-sm" style="background:#14A44D;">
            <div class="card-header">LODGE</div>
            <div class="card-body"><h5 id="lodge">€0.00</h5></div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center shadow-sm" id="sales-card">
            <div class="card-header">Reconciliation</div>
            <div class="card-body"><h5 id="sales">€0.00</h5></div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center shadow-sm" style="background: #fff3b0;" id="yesterday-card">
            <div class="card-header"><?= $salesDate ? $salesDayName . ' ' . $salesDateFormatted .' Sales, at this time' : 'Previous Day Sales, at this time' ?></div>
            <div class="card-body"><h5 id="F">€<?= number_format($yesterdaySales,2) ?></h5></div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center shadow-sm" style="background: #ADD8E6;" id="running-total-card">
            <div class="card-header">Current Running Total</div>
            <div class="card-body"><h5 id="running-total">€<?= number_format($currentRunningTotal, 2) ?></h5></div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center shadow-sm" style="background: #FA8072;" id="cash_payment-card">
            <div class="card-header">Cash Payments</div>
            <div class="card-body"><h5 id="cash-payment">€<?= number_format($CP, 2) ?></h5></div>
        </div>
    </div>
    <div class="col-md-1 d-flex flex-column gap-2">
        <button type="button" id="start-of-day-btn" class="btn btn-info flex-fill">
            <i class="fas fa-play-circle me-1"></i> Start of Day
        </button>
        <button type="button" id="refresh-mssql-btn" class="btn btn-success flex-fill">
            <i class="fas fa-sync me-1"></i> Refresh Sales
        </button>
    </div>
</div>    <!-- Main row: Cash Drawer, Change in Bags, System Count -->
    <div class="row g-4">
        <!-- Cash Drawer -->
        <div class="col-sm-5">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">Cash Drawer</div>
                <div class="card-body">
                    <table class="table table-sm align-middle" id="cash-drawer">
                        <tbody>
                        <?php foreach($denoms as $d): 
    			$dataDenom = number_format($d, 2, '.', ''); 
			?>
			<tr>
                            <td class="denomination-label">€<?= number_format($d,2) ?></td>
                            <td>
                                <input type="number"
       min="0"
       class="form-control cash-input"
       data-denom="<?= $dataDenom ?>"
       value="<?= $cashDrawerData[$dataDenom] ?? 0 ?>">

                            </td>
                            <?php if(in_array($d,[0.05,0.10,0.20,0.50,1,2])): ?>
                            <td>
                                <button type="button" 
                                        class="btn btn-sm btn-warning bag-btn" 
                                        data-denom="<?= $dataDenom ?>" 
                                        disabled>Bag? (0)</button>
                            </td>
                            <?php else: ?>
                            <td></td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                        <!-- Totals row -->
                        <tr class="table-secondary fw-bold">
                            <td>Total</td>
                            <td></td>
                            <td id="cash-drawer-total">0.00</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Change in Bags -->
        <div class="col-sm-3">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">Change in Bags</div>
                <div class="card-body">
                    <table class="table table-sm align-middle">
                        <tbody>
                        <?php for ($i=0; $i<7; $i++): ?>
                            <tr style="visibility:hidden; height:39px;"><td>&nbsp;</td><td></td></tr>
                        <?php endfor; ?>
<?php foreach ($bagDenoms as $d): ?>
    <?php $dataDenom = number_format($d, 2, '.', ''); ?>
    <tr>
        <td class="denomination-label">€<?= $dataDenom ?></td>
        <td>
            <input type="number" 
                   min="0" 
                   class="form-control bag-input" 
                   data-denom="<?= $dataDenom ?>" 
                   value="<?= $changeBagsData[$dataDenom] ?? 0 ?>">
        </td>
    </tr>
<?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- System Count -->
        <div class="col-sm-3">
            <div class="card shadow-sm" id="system-count-card">
                <div class="card-header bg-warning">System Count</div>
                <div class="card-body p-2">
                    <table class="table table-sm align-middle" id="system-count">
                        <tbody>
<?php foreach ($denoms as $d): ?>
    <?php $dataDenom = number_format($d, 2, '.', ''); ?>
    <tr style="height:47px;">
        <td class="sys-count" data-denom="<?= $dataDenom ?>">0</td>
        <td class="sys-value" data-denom="<?= $dataDenom ?>">€0.00</td>
    </tr>
<?php endforeach; ?>
                            <!-- Totals row -->
                            <tr class="table-secondary fw-bold" style="height:36px;">
                                <td>Total</td>
                                <td id="system-count-total">€0.00</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const denoms = ["0.05","0.10","0.20","0.50","1.00","2.00","5.00","10.00","20.00","50.00","100.00"];
const bagDenoms = ["5.00","10.00","20.00","50.00","100.00"];
let saveTimeout;
    
    // Inject PHP values into JS
    let AE = <?= json_encode($AE) ?>;
    let CP = <?= json_encode($CP) ?>;
    let allSales = <?= json_encode($allSales) ?>;
    let currentCashSales = <?= json_encode($currentCashSales) ?>;
    let loyalty = <?= json_encode($loyalty) ?>;
    let currentRunningTotal = <?= json_encode($currentRunningTotal) ?>;
    const yesterdaySales = <?= json_encode($yesterdaySales) ?>;
    const prevFloatHeld = <?= json_encode($prevFloatHeld) ?>;
    
    // Auto-save function with debouncing
    function autoSave() {
        clearTimeout(saveTimeout);
        saveTimeout = setTimeout(() => {
            saveData();
        }, 1000);
    }

    function saveData() {
        const cashDrawer = {};
        document.querySelectorAll('.cash-input').forEach(input => {
            const denom = input.dataset.denom;
            cashDrawer[denom] = parseInt(input.value) || 0;
        });
        
        const changeBags = {};
        document.querySelectorAll('.bag-input').forEach(input => {
            const denom = input.dataset.denom;
            changeBags[denom] = parseInt(input.value) || 0;
        });
        
        const systemCount = {};
        document.querySelectorAll('.sys-count').forEach(td => {
            const denom = td.dataset.denom;
            systemCount[denom] = parseInt(td.textContent) || 0;
        });
        
        const systemValue = {};
        document.querySelectorAll('.sys-value').forEach(td => {
            const denom = td.dataset.denom;
            systemValue[denom] = parseFloat(td.textContent.replace('€','')) || 0;
        });
        
        const floatCurrent = parseFloat(document.getElementById('float-current').value) || 0;
        const floatBalance = parseFloat(document.getElementById('float-balance').value) || 0;
        const lodge = parseFloat(document.getElementById('lodge').textContent.replace('€','')) || 0;
        const sales = parseFloat(document.getElementById('sales').textContent.replace('€','')) || 0;
        
        // Z Count calculation: NEW FORMULA
        const zCount = currentRunningTotal + floatBalance;

const payload = {
            cashDrawer,
            changeBags,
            systemCount,
            systemValue,
            floatCurrent,
            floatBalance,
            lodge,
            sales,
            zCount,
            cashSales: currentCashSales,
            allSales: allSales,
            yesterdaySales: yesterdaySales
        };
        
        fetch('create_form_v3.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                showSaveIndicator();
                document.getElementById('lastSaved').textContent = new Date().toLocaleTimeString();
            } else {
                console.error('Save failed:', result.error);
            }
        })
        .catch(err => {
            console.error('Auto-save failed:', err);
        });
    }

    function showSaveIndicator() {
        const indicator = document.getElementById('saveIndicator');
        indicator.style.display = 'block';
        indicator.classList.remove('fade-out');
        setTimeout(() => {
            indicator.classList.add('fade-out');
            setTimeout(() => {
                indicator.style.display = 'none';
            }, 300);
        }, 1500);
    }

function updateMSSQLData() {
    fetch('refresh_mssql_data.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update global JS variables
                AE = data.AE;
                CP = data.CP;
                currentCashSales = data.CS;
                allSales = data.allSales;
                loyalty = data.loyalty;
                currentRunningTotal = data.currentRunningTotal;
                
                // Update displayed card values
                document.getElementById('cash-sales').textContent = '€' + currentCashSales.toFixed(2);
                document.getElementById('cash-payment').textContent = '€' + CP.toFixed(2);
                document.getElementById('all-sales').textContent = '€' + AE.toFixed(2);
                document.getElementById('loyalty').textContent = '€' + loyalty.toFixed(2);
                document.getElementById('running-total').textContent = '€' + currentRunningTotal.toFixed(2);
                
                // Update donations if available
                if (data.donations !== undefined) {
                    const donationsField = document.getElementById('donations');
                    if (donationsField) {
                        donationsField.textContent = '€' + data.donations.toFixed(2);
                    }
                }
                
                // Update previous float if provided
                if (data.PrevFloatHeld !== undefined) {
                    const prevFloatInput = document.getElementById('float-previous');
                    if (prevFloatInput) {
                        prevFloatInput.value = data.PrevFloatHeld.toFixed(2);
                    }
                }
                
                // Recalculate all dependent values
                recalc();
                
                console.log('MSSQL data refreshed successfully', data);
            } else {
                console.error('MSSQL refresh failed:', data.error);
            }
        })
        .catch(err => console.error('Failed to refresh MSSQL data:', err));
}

function recalc() {
        const cashMap = {}, bagMap = {};
        denoms.forEach(d => {
            const cashInput = document.querySelector(`.cash-input[data-denom="${d}"]`);
            cashMap[d] = cashInput ? (parseInt(cashInput.value) || 0) : 0;
        });
        bagDenoms.forEach(d => {
            const bagInput = document.querySelector(`.bag-input[data-denom="${d}"]`);
            bagMap[d] = bagInput ? (parseInt(bagInput.value) || 0) : 0;
        });
        
        let overallTotal = 0;
        let cashDrawerTotal = 0;
        
        denoms.forEach(d => {
            const cashQty = cashMap[d] || 0;
            const bagQty = bagMap[d] || 0;
            const totalQty = cashQty + bagQty;
            const sysCount = document.querySelector(`.sys-count[data-denom="${d}"]`);
            const sysVal = document.querySelector(`.sys-value[data-denom="${d}"]`);
            
            if(sysCount) sysCount.textContent = totalQty;
            if(sysVal){
                const val = totalQty * d;
                sysVal.textContent = '€' + val.toFixed(2);
                overallTotal += val;
            }
            cashDrawerTotal += cashQty * d;
        });
        
        const sysTotalCell = document.getElementById('system-count-total');
        if(sysTotalCell) sysTotalCell.textContent = '€' + overallTotal.toFixed(2);
        
        const cashDrawerTotalCell = document.getElementById('cash-drawer-total');
        if(cashDrawerTotalCell) cashDrawerTotalCell.textContent = '€' + cashDrawerTotal.toFixed(2);

        // FLOAT calculations
        const floatDenoms = ["0.05","0.10","0.20","0.50","1.00","2.00"];
        const floatCurrent = floatDenoms.reduce((sum, d) => {
            const sysVal = document.querySelector(`.sys-value[data-denom="${d}"]`);
            if (sysVal) {
                const val = parseFloat(sysVal.textContent.replace(/[^\d.-]/g,'')) || 0;
                return sum + val;
            }
            return sum;
        }, 0);
        
        document.getElementById('float-current').value = floatCurrent.toFixed(2);
        document.getElementById('float-balance').value = (floatCurrent - prevFloatHeld).toFixed(2);

        // LODGE calculation
        const floatBalance = parseFloat(document.getElementById('float-balance').value) || 0;
        const lodge = bagDenoms.reduce((sum,d)=>{
            const cell = document.querySelector(`.sys-value[data-denom="${d}"]`);
            if(cell){
                const val = parseFloat(cell.textContent.replace('€','')) || 0;
                return sum + val;
            }
            return sum;
        }, 0);
        document.getElementById('lodge').textContent = '€' + lodge.toFixed(2);

        // Z Count calculation: NEW FORMULA
        const Z = currentRunningTotal + floatBalance;
        document.getElementById('z-count').textContent = '€' + Z.toFixed(2);

        // SALES calculation
        const salesVal = ((floatBalance + AE + lodge) - (allSales + CP));
        const salesField = document.getElementById('sales');
        salesField.textContent = '€' + salesVal.toFixed(2);
        
        const salesCard = document.getElementById('sales-card');
        salesCard.classList.remove('sales-zero','sales-pos','sales-neg');
        if(Math.abs(salesVal)<0.005) salesCard.classList.add('sales-zero');
        else if(salesVal>0) salesCard.classList.add('sales-pos');
        else salesCard.classList.add('sales-neg');
        
        const yesterdayField = document.getElementById('yesterday-sales');
        if(yesterdayField) yesterdayField.textContent = '€' + yesterdaySales.toFixed(2);
    }

// Bagging rules (string keys)
const bagRules = {
    "0.05": {threshold: 100, bagDenom: 5, bagCount: 1},
    "0.10": {threshold: 100, bagDenom: 10, bagCount: 1},
    "0.20": {threshold: 50,  bagDenom: 10, bagCount: 1},
    "0.50": {threshold: 50,  bagDenom: [5,20], bagCount: [1,1]},
    "1.00": {threshold: 25,  bagDenom: [5,20], bagCount: [1,1]},
    "2.00": {threshold: 25,  bagDenom: 50, bagCount: 1}
};

function updateBagButtons() {
    document.querySelectorAll('.bag-btn').forEach(btn => {
        const denom = btn.dataset.denom;
        const input = document.querySelector(`.cash-input[data-denom="${denom}"]`);
        if (!input) return;

        const qty = parseInt(input.value) || 0;
        const rule = bagRules[denom];
        if (!rule) return;

        const bagsAvailable = Math.floor(qty / rule.threshold);
        btn.textContent = `Bag? (${bagsAvailable})`;
        btn.disabled = bagsAvailable < 1;
    });
}

// Event listeners - CORRECTED VERSION
document.querySelectorAll('.cash-input').forEach(i => {
    i.addEventListener('input', () => {
        recalc();           // Recalculate totals based on user input
        updateBagButtons(); // Update bag button availability
        autoSave();         // Save to database
        // REMOVED: updateMSSQLData() - this doesn't need to run on every input change!
    });
});

document.querySelectorAll('.bag-input').forEach(i => {
    i.addEventListener('input', () => {
        recalc();           // Recalculate totals
        autoSave();         // Save to database
        // REMOVED: updateMSSQLData() - same reason
    });
});

// Handle Bag button clicks
document.querySelectorAll('.bag-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const denom = btn.dataset.denom;
        const input = document.querySelector(`.cash-input[data-denom="${denom}"]`);
        if (!input) return;

        let qty = parseInt(input.value) || 0;
        const rule = bagRules[denom];
        if (!rule || qty < rule.threshold) return;

        // Subtract threshold worth from cash drawer
        qty -= rule.threshold;
        input.value = qty;

        // Update corresponding change bag(s)
        if (Array.isArray(rule.bagDenom)) {
            rule.bagDenom.forEach((d, i) => {
                const bagInput = document.querySelector(`.bag-input[data-denom="${d.toFixed(2)}"]`);
                if (bagInput) {
                    bagInput.value = (parseInt(bagInput.value) || 0) + rule.bagCount[i];
                }
            });
        } else {
            const bagInput = document.querySelector(`.bag-input[data-denom="${rule.bagDenom.toFixed(2)}"]`);
            if (bagInput) {
                bagInput.value = (parseInt(bagInput.value) || 0) + rule.bagCount;
            }
        }

        recalc();
        updateBagButtons();
        autoSave();
        // REMOVED: updateMSSQLData() - same reason
    });
});

// Auto-refresh MSSQL data every 5 minutes (to catch new sales from POS)
 setInterval(updateMSSQLData, 150000); // 300000ms = 5 minutes

// Manual refresh button handler
const refreshBtn = document.getElementById('refresh-mssql-btn');
if (refreshBtn) {
    refreshBtn.addEventListener('click', function() {
        this.disabled = true;
        const originalHTML = this.innerHTML;
        this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Refreshing...';
        
        updateMSSQLData();
        
        setTimeout(() => {
            this.disabled = false;
            this.innerHTML = originalHTML;
            
            // Show brief success indicator
            this.classList.remove('btn-success');
            this.classList.add('btn-outline-success');
            setTimeout(() => {
                this.classList.remove('btn-outline-success');
                this.classList.add('btn-success');
            }, 500);
        }, 2000);
    });
}

// Initial state setup
updateBagButtons();
recalc();

if (<?= !empty($existingData) ? 'true' : 'false' ?>) {
    document.getElementById('lastSaved').textContent = 'Data loaded from database';
}// Start of Day button
document.getElementById('start-of-day-btn').addEventListener('click', function() {
    if (confirm('Start new day? This will:\n• Carry forward small denominations (€0.05-€2.00) from yesterday\n• Zero large denominations (€5-€100)\n• Zero all bag denomination fields\n• Create new database record for today\n\nContinue?')) {
        
        // Show loading indicator
        const btn = this;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Processing...';
        btn.disabled = true;
        
        fetch('start_of_day.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Success - reload the page to show the new data
                alert('Start of Day completed successfully!\nSmall denominations carried forward from yesterday.\n\nPage will now reload.');
                window.location.reload();
            } else {
                alert('Error starting new day: ' + (data.error || 'Unknown error'));
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        })
        .catch(err => {
            console.error('Start of Day failed:', err);
            alert('Failed to start new day. Please try again.');
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
    }
});
});
</script>
<?php include 'footer.php'; ?>
