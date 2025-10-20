<?php
require_once 'config.php';

// Set page variables for header
$pageTitle = 'Dashboard';
$headerTitle = 'Example Retail POS Company Name';
$additionalCSS = '
<style>
.metric-card {
    transition: transform 0.2s;
}
.metric-card:hover {
    transform: translateY(-2px);
}
.chart-container {
    height: 300px;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6c757d;
}
</style>';

// ===== DATA QUERIES =====

// Yesterday's sales (or Friday if today is Monday)
$yesterdaySales = 0.0;
try {
    $stmt = $sqlsrv_pdo->query("
SELECT
CAST(t.dtTimeStamp AS DATE) AS SalesDate,
SUM(t.PN_CURR) AS YesterdaySales
FROM svp.dbo.TENDER t
WHERE CAST(t.dtTimeStamp AS DATE) < CAST(GETDATE() AS DATE)
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

// This Year to Date vs Last Year to Date
try {
    $stmt = $sqlsrv_pdo->query("
        SELECT 
            -- This Year To Date
            (SELECT ISNULL(SUM(CASE WHEN cdl.PaymentNo = '04' THEN cdl.TillTotal ELSE 0 END), 0) + 
                    ISNULL(SUM(CASE WHEN cdl.PaymentNo = '01' THEN cdl.Lodged ELSE 0 END), 0)
             FROM CashDecLines cdl
             INNER JOIN CashDecHeader cdh ON cdl.CashDecRef = cdh.CashDecRef
             WHERE CAST(cdh.dtTimeStamp AS DATE) >= DATEFROMPARTS(YEAR(GETDATE()), 1, 1)
               AND CAST(cdh.dtTimeStamp AS DATE) < CAST(GETDATE() AS DATE)
               AND cdl.PaymentNo IN ('01', '04')
            ) AS ThisYearToDate,
            
            -- Last Year To Date
            (SELECT ISNULL(SUM(CASE WHEN cdl.PaymentNo = '04' THEN cdl.TillTotal ELSE 0 END), 0) + 
                    ISNULL(SUM(CASE WHEN cdl.PaymentNo = '01' THEN cdl.Lodged ELSE 0 END), 0)
             FROM CashDecLines cdl
             INNER JOIN CashDecHeader cdh ON cdl.CashDecRef = cdh.CashDecRef
             WHERE CAST(cdh.dtTimeStamp AS DATE) >= DATEFROMPARTS(YEAR(GETDATE()) - 1, 1, 1)
               AND CAST(cdh.dtTimeStamp AS DATE) < DATEADD(YEAR, -1, CAST(GETDATE() AS DATE))
               AND cdl.PaymentNo IN ('01', '04')
            ) AS LastYearToDate,
            
            -- Last Year Total
            (SELECT ISNULL(SUM(CASE WHEN cdl.PaymentNo = '04' THEN cdl.TillTotal ELSE 0 END), 0) + 
                    ISNULL(SUM(CASE WHEN cdl.PaymentNo = '01' THEN cdl.Lodged ELSE 0 END), 0)
             FROM CashDecLines cdl
             INNER JOIN CashDecHeader cdh ON cdl.CashDecRef = cdh.CashDecRef
             WHERE YEAR(cdh.dtTimeStamp) = YEAR(GETDATE()) - 1
               AND cdl.PaymentNo IN ('01', '04')
            ) AS LastYearTotal
    ");
    
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row) {
        $thisYearToDate = (float)$row['ThisYearToDate'];
        $lastYearToDate = (float)$row['LastYearToDate'];
        $lastYearTotal = (float)$row['LastYearTotal'];
    } else {
        $thisYearToDate = 0.0;
        $lastYearToDate = 0.0;
        $lastYearTotal = 0.0;
    }
} catch (Exception $e) {
    error_log("Year-to-date query failed: " . $e->getMessage());
    $thisYearToDate = 0.0;
    $lastYearToDate = 0.0;
    $lastYearTotal = 0.0;
}

// Calculate percentage change
$percentageChange = 0;
if ($lastYearToDate > 0) {
    $percentageChange = (($thisYearToDate - $lastYearToDate) / $lastYearToDate) * 100;
}

// Today's sales
$todaySales = 0.0;
try {
    $stmt = $sqlsrv_pdo->query("
SELECT SUM(s.SN_Actual) AS TodaySales
        FROM svp.dbo.SALES s
        WHERE dtTimeStamp BETWEEN CONCAT(CAST(GETDATE() AS DATE),' 09:00:00') 
                            AND CONCAT(CAST(GETDATE() AS DATE),' 17:30:00')
    ");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $todaySales = (float)($row['TodaySales'] ?? 0);
} catch (PDOException $e) {
    $todaySales = 0.0;
    $todayError = $e->getMessage();
}

// Today's tender subset (stacked cash vs other)
$currentCashSales = 0.0;
$allOtherSales = 0.0;

try {
    $stmt = $sqlsrv_pdo->query("
        SELECT 
            SUM(t.PN_CURR) AS Z,
            (SELECT SUM(t.PN_CURR) 
             FROM svp.dbo.TENDER t
             WHERE t.dtTimeStamp BETWEEN CONCAT(CAST(GETDATE() AS DATE),' 09:00:00') 
                                   AND CONCAT(CAST(GETDATE() AS DATE),' 17:30:00') 
               AND t.PN_TYPE <> '01') AS AE
        FROM  svp.dbo.TENDER t 
        WHERE t.dtTimeStamp BETWEEN CONCAT(CAST(GETDATE() AS DATE),' 09:00:00') 
                              AND CONCAT(CAST(GETDATE() AS DATE),' 17:30:00')
    ");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $allOtherSales = (float)($row['AE'] ?? 0);
        $currentCashSales = (float)($row['Z'] ?? 0) - $allOtherSales;
    }
} catch (PDOException $e) {
/*    $currentCashSales = $allOtherSales = 0.0; */
}

include 'header.php';
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="container-fluid my-4">
    <!-- Action Buttons -->
    <div class="row mb-4 g-3">
        <!--<div class="col-md-6">-->
        <div class="col-lg-3 col-md-6">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center">
                    <h5 class="card-title">Daily Operations</h5>
                    <p class="card-text">Manage daily cash reconciliation and end of day procedures</p>
                    <a href="cash_count_v3.php" class="btn btn-primary btn-lg">End of Day Assistant</a>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center">
                    <h5 class="card-title">Reporting Operations</h5>
                    <p class="card-text">Reporting and Mailing Functions</p>
                    <a href="reports.php" class="btn btn-info btn-lg">View Reports</a>
                    <small class="d-block text-muted mt-2">Configure notifications and generate reports</small>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center">
                    <h5 class="card-title">Inventory Scanning</h5>
                    <p class="card-text">Live Dashboard and Scan</p>
                    <a href="inventory_dashboard.php" class="btn btn-success btn-lg">Inventory Scanner</a>
                    <small class="d-block text-muted mt-2">Display live inventory and single/bulk scan items for floor display</small>
                </div>
            </div>
        </div>

</div>

    <!-- Performance Indicators -->
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-3">Performance Overview</h2>
        </div>
    </div>

    <!-- Key Metrics Row -->
    <div class="row mb-4 g-3">
        <div class="col-lg-3 col-md-6">
            <div class="card metric-card shadow-sm">
                <div class="card-body text-center">
                    <h6 class="card-subtitle mb-2 text-muted">
                        <?= $salesDate ? $salesDayName . ' Sales' : 'Previous Day Sales' ?>
                    </h6>
                    <h4 class="card-title text-primary">€<?= number_format($yesterdaySales, 2) ?></h4>
                    <small class="text-muted">
                        <?= $salesDateFormatted ?>
                    </small>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6">
            <div class="card metric-card shadow-sm">
                <div class="card-body text-center">
                    <h6 class="card-subtitle mb-2 text-muted">This Year to Date</h6>
                    <h4 class="card-title text-success">€<?= number_format($thisYearToDate, 2) ?></h4>
                    <small class="text-muted">Jan 1 - <?= date('M j') ?></small>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6">
            <div class="card metric-card shadow-sm">
                <div class="card-body text-center">
                    <h6 class="card-subtitle mb-2 text-muted">Last Year to Date</h6>
                    <h4 class="card-title text-warning">€<?= number_format($lastYearToDate, 2) ?></h4>
                    <small class="text-muted">Same period <?= date('Y') - 1 ?></small>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6">
            <div class="card metric-card shadow-sm">
                <div class="card-body text-center">
                    <h6 class="card-subtitle mb-2 text-muted"><?= date('Y') - 1 ?> Full Year</h6>
                    <h4 class="card-title text-info">€<?= number_format($lastYearTotal, 2) ?></h4>
                    <small class="text-muted">Complete year</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <!-- Overall Sales Card -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Overall Sales (Yesterday vs Today)</h5>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="overallSalesChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tender Breakdown Card -->
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Tender Breakdown (Today)</h5>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="tenderChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Year over Year Comparison -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Year-over-Year Performance</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6 text-center">
                            <h6 class="text-muted">Performance vs Last Year</h6>
                            <h3 class="<?= $percentageChange >= 0 ? 'text-success' : 'text-danger' ?>">
                                <?= $percentageChange >= 0 ? '+' : '' ?><?= number_format($percentageChange, 1) ?>%
                            </h3>
                        </div>
                        <div class="col-6 text-center">
                            <h6 class="text-muted">Difference</h6>
                            <h3 class="<?= ($thisYearToDate - $lastYearToDate) >= 0 ? 'text-success' : 'text-danger' ?>">
                                €<?= number_format(abs($thisYearToDate - $lastYearToDate), 2) ?>
                            </h3>
                        </div>
                    </div>
                    <div class="progress mt-3" style="height: 20px;">
                        <?php 
                        $progressPercent = $lastYearToDate > 0 ? min(($thisYearToDate / $lastYearToDate) * 100, 100) : 0;
                        ?>
                        <div class="progress-bar <?= $percentageChange >= 0 ? 'bg-success' : 'bg-warning' ?>" 
                             role="progressbar" 
                             style="width: <?= $progressPercent ?>%"
                             aria-valuenow="<?= $progressPercent ?>" 
                             aria-valuemin="0" 
                             aria-valuemax="100">
                            <?= number_format($progressPercent, 1) ?>%
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div> 

    <script>
    document.addEventListener("DOMContentLoaded", function() {
        // Overall Sales Chart
        const overallCtx = document.getElementById('overallSalesChart').getContext('2d');
        new Chart(overallCtx, {
            type: 'bar',
            data: {
                labels: ['Sales'],
                datasets: [
                    {
                        label: 'Yesterday',
                        data: [<?= $yesterdaySales ?>],
                        backgroundColor: 'rgba(54, 162, 235, 0.7)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Today',
                        data: [<?= $currentCashSales + $allOtherSales ?>],
                        backgroundColor: 'rgba(75, 192, 192, 0.7)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': €' + context.raw.toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) { return '€' + value.toLocaleString(); }
                        }
                    }
                }
            }
        });

        // Tender Breakdown Chart
        const tenderCtx = document.getElementById('tenderChart').getContext('2d');
        new Chart(tenderCtx, {
            type: 'bar',
            data: {
                labels: ['Tender Breakdown'],
                datasets: [
                    {
                        label: 'Current Cash Sales',
                        data: [<?= $currentCashSales ?>],
                        backgroundColor: 'rgba(255, 206, 86, 0.7)',
                        borderColor: 'rgba(255, 206, 86, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'All Other Sales',
                        data: [<?= $allOtherSales ?>],
                        backgroundColor: 'rgba(255, 99, 132, 0.7)',
                        borderColor: 'rgba(255, 99, 132, 1)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': €' + context.raw.toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    x: { stacked: true },
                    y: { 
                        stacked: true,
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) { return '€' + value.toLocaleString(); }
                        }
                    }
                }
            }
        });
    });
    </script>

    <!-- Database Status -->
    <div class="row">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">
                    <h6 class="mb-0">MySQL (Cash Drawer) Database Status</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="bg-success rounded-circle me-3" style="width: 12px; height: 12px;"></div>
                        <span>Connected</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0">MSSQL (Live) Database Status</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="bg-success rounded-circle me-3" style="width: 12px; height: 12px;"></div>
                        <span>Connected</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
