<?php
require_once 'config.php';

// Set page variables for header
$pageTitle = 'MySQL Cash Reconciliation Reports';
$headerTitle = 'Cash Reconciliation Reports - POS Company Name';
$additionalCSS = '
<style>
.report-card {
    transition: transform 0.2s;
    border-left: 4px solid #28a745;
}
.report-card:hover {
    transform: translateY(-1px);
}
.filters-card {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
}
.summary-stats {
    background: linear-gradient(135deg, #28a745, #20c997);
    color: white;
}
.table-container {
    max-height: 500px;
    overflow-y: auto;
}
.positive { color: #28a745; font-weight: bold; }
.negative { color: #dc3545; font-weight: bold; }
.zero { color: #6c757d; font-weight: bold; }
.export-btn { 
    position: sticky;
    top: 10px;
    z-index: 100;
}
</style>';

// Initialize variables
$startDate = isset($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-01'); // First day of current month
$endDate = isset($_POST['end_date']) ? $_POST['end_date'] : date('Y-m-d'); // Today
$reportData = [];
$totalCashSales = 0;
$totalOtherSales = 0;
$totalLodged = 0;
$totalReconciliation = 0;
$recordCount = 0;
$errorMessage = '';

// Process form submission
if ($_POST && !empty($startDate) && !empty($endDate)) {
    try {
        // Validate dates
        $startDateTime = new DateTime($startDate);
        $endDateTime = new DateTime($endDate);
        
        if ($startDateTime > $endDateTime) {
            $errorMessage = "Start date cannot be after end date.";
        } else {
            // Fetch reconciliation data from MySQL
            $stmt = $pdo->prepare("
                SELECT 
                    DATE(recon_day) as recon_date,
                    cash_sales,
                    (z_count - cash_sales) as other_sales,
                    lodge as lodged,
                    z_count,
                    float_balance,
                    cash_sales as ae_value,
                    sales as reconciliation,
                    date_recorded
                FROM cash_reconciliation 
                WHERE DATE(recon_day) BETWEEN ? AND ?
                ORDER BY recon_day DESC
            ");
            
            $stmt->execute([$startDate, $endDate]);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $recordCount = count($reportData);
            
            // Fetch MSSQL EOD timestamps if we have data
            if (!empty($reportData)) {
                $dateRecordedList = array_map(function($row) {
                    return date('Y-m-d', strtotime($row['date_recorded']));
                }, $reportData);
                
                $placeholders = str_repeat('?,', count($dateRecordedList) - 1) . '?';
                
                try {
                    $mssqlStmt = $sqlsrv_pdo->prepare("
                        SELECT 
                            CAST(dtTimeStamp AS DATE) as date_key,
                            dtTimeStamp
                        FROM CashDecHeader
                        WHERE CAST(dtTimeStamp AS DATE) IN ($placeholders)
                    ");
                    
                    $mssqlStmt->execute($dateRecordedList);
                    $mssqlData = [];
                    
                    while ($row = $mssqlStmt->fetch(PDO::FETCH_ASSOC)) {
                        // Handle different date formats from MSSQL
                        if ($row['date_key'] instanceof DateTime) {
                            $dateKey = $row['date_key']->format('Y-m-d');
                        } else {
                            $dateKey = date('Y-m-d', strtotime($row['date_key']));
                        }
                        
                        if ($row['dtTimeStamp'] instanceof DateTime) {
                            $mssqlData[$dateKey] = $row['dtTimeStamp']->format('Y-m-d H:i:s');
                        } else {
                            $mssqlData[$dateKey] = $row['dtTimeStamp'];
                        }
                    }
                    
                    // Merge MSSQL data into reportData
                    foreach ($reportData as &$row) {
                        $dateKey = date('Y-m-d', strtotime($row['date_recorded']));
                        $row['eod_timestamp'] = isset($mssqlData[$dateKey]) ? $mssqlData[$dateKey] : null;
                    }
                    unset($row); // Break reference
                    
                } catch (Exception $e) {
                    // If MSSQL query fails, continue with MySQL data only
                    error_log("MSSQL query failed: " . $e->getMessage());
                    foreach ($reportData as &$row) {
                        $row['eod_timestamp'] = null;
                    }
                    unset($row);
                }
            }
            
            // Calculate totals
            foreach ($reportData as $row) {
                $totalCashSales += (float)($row['cash_sales'] ?? 0);
                $totalOtherSales += (float)($row['other_sales'] ?? 0);
                $totalLodged += (float)($row['lodged'] ?? 0);
                $totalReconciliation += (float)($row['reconciliation'] ?? 0);
            }
            
            // Get summary statistics
            $summaryStmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_days,
                    AVG(cash_sales) as avg_cash_sales,
                    AVG(sales) as avg_other_sales,
                    AVG(lodge) as avg_lodged,
                    AVG(float_balance + cash_sales + lodge - z_count) as avg_reconciliation,
                    MAX(cash_sales) as max_cash_sales,
                    MIN(cash_sales) as min_cash_sales
                FROM cash_reconciliation 
                WHERE DATE(recon_day) BETWEEN ? AND ?
            ");
            
            $summaryStmt->execute([$startDate, $endDate]);
            $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        $errorMessage = "Error generating report: " . $e->getMessage();
    }
}

include 'header.php';
?>

<div class="container-fluid my-4">
    <!-- Back to Menu Button -->
    <div class="row mb-3">
        <div class="col-12">
            <a href="reports.php" class="btn btn-outline-secondary">
                ← Back to Menu
            </a>
        </div>
    </div>

    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card report-card shadow-sm">
                <div class="card-body">
                    <h2 class="card-title mb-1">Cash Reconciliation History</h2>
                    <p class="card-text text-muted">Daily reconciliation records with cash sales, other sales, and lodgement details</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Error Messages -->
    <?php if ($errorMessage): ?>
    <div class="row mb-3">
        <div class="col-12">
            <div class="alert alert-danger">
                <strong>Error:</strong> <?= htmlspecialchars($errorMessage) ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Filters Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card filters-card shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0">Report Filters</h5>
                </div>
                <div class="card-body">
                    <form method="POST" id="reportForm">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" 
                                       value="<?= htmlspecialchars($startDate) ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" 
                                       value="<?= htmlspecialchars($endDate) ?>" required>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="fas fa-chart-line me-1"></i> Generate Report
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($reportData) && !$errorMessage): ?>
    <!-- Summary Statistics -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card summary-stats shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0 text-white">Summary Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-2">
                            <h6 class="text-white-50">Total Days</h6>
                            <h4><?= $recordCount ?></h4>
                        </div>
                        <div class="col-md-2">
                            <h6 class="text-white-50">Total Cash Sales</h6>
                            <h4>€<?= number_format($totalCashSales, 2) ?></h4>
                        </div>
                        <div class="col-md-2">
                            <h6 class="text-white-50">Total Other Sales</h6>
                            <h4>€<?= number_format($totalOtherSales, 2) ?></h4>
                        </div>
                        <div class="col-md-2">
                            <h6 class="text-white-50">Total Lodged</h6>
                            <h4>€<?= number_format($totalLodged, 2) ?></h4>
                        </div>
                        <div class="col-md-2">
                            <h6 class="text-white-50">Net Reconciliation</h6>
                            <h4 class="<?= $totalReconciliation >= 0 ? 'text-light' : 'text-warning' ?>">
                                €<?= number_format($totalReconciliation, 2) ?>
                            </h4>
                        </div>
                        <div class="col-md-2">
                            <h6 class="text-white-50">Daily Average</h6>
                            <h4>€<?= $recordCount > 0 ? number_format($totalReconciliation / $recordCount, 2) : '0.00' ?></h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Results -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        Reconciliation Records
                        <small class="text-muted">(<?= htmlspecialchars($startDate) ?> to <?= htmlspecialchars($endDate) ?>)</small>
                    </h5>
                    <button class="btn btn-outline-primary btn-sm export-btn" onclick="exportToCSV()">
                        <i class="fas fa-download me-1"></i> Export CSV
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-container">
                        <table class="table table-striped table-hover mb-0" id="reconciliationTable">
                            <thead class="table-success sticky-top">
                                <tr>
                                    <th>Date</th>
                                    <th class="text-end">Cash Sales</th>
                                    <th class="text-end">Other Sales</th>
                                    <th class="text-end">Lodged</th>
                                    <th class="text-end">Reconciliation</th>
                                    <th>Status</th>
                                    <th>EOD Timestamp</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reportData as $row): ?>
                                <?php 
                                $reconciliation = (float)$row['reconciliation'];
                                $statusClass = abs($reconciliation) < 0.005 ? 'zero' : ($reconciliation > 0 ? 'positive' : 'negative');
                                $statusText = abs($reconciliation) < 0.005 ? 'Balanced' : ($reconciliation > 0 ? 'Over' : 'Short');
                                ?>
                                <tr>
                                    <td><?= date('D, M j, Y', strtotime($row['recon_date'])) ?></td>
                                    <td class="text-end">€<?= number_format($row['cash_sales'] ?? 0, 2) ?></td>
                                    <td class="text-end">€<?= number_format($row['other_sales'] ?? 0, 2) ?></td>
                                    <td class="text-end">€<?= number_format($row['lodged'] ?? 0, 2) ?></td>
                                    <td class="text-end <?= $statusClass ?>">€<?= number_format($reconciliation, 2) ?></td>
                                    <td><span class="badge bg-<?= $statusClass === 'zero' ? 'success' : ($statusClass === 'positive' ? 'warning' : 'danger') ?>"><?= $statusText ?></span></td>
                                    <td>
                                        <?php if (!empty($row['eod_timestamp'])): ?>
                                            <?= date('M j, H:i', strtotime($row['eod_timestamp'])) ?>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">EOD Not Run</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-secondary">
                                <tr class="fw-bold">
                                    <td>TOTALS</td>
                                    <td class="text-end">€<?= number_format($totalCashSales, 2) ?></td>
                                    <td class="text-end">€<?= number_format($totalOtherSales, 2) ?></td>
                                    <td class="text-end">€<?= number_format($totalLodged, 2) ?></td>
                                    <td class="text-end <?= abs($totalReconciliation) < 0.005 ? 'zero' : ($totalReconciliation > 0 ? 'positive' : 'negative') ?>">
                                        €<?= number_format($totalReconciliation, 2) ?>
                                    </td>
                                    <td colspan="2">
                                        <small class="text-muted"><?= $recordCount ?> records</small>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php elseif ($_POST && empty($reportData) && !$errorMessage): ?>
    <!-- No Data Found -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body text-center py-5">
                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No Records Found</h5>
                    <p class="text-muted">No reconciliation records found for the selected date range.</p>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Validate date range
    const startDate = document.getElementById('start_date');
    const endDate = document.getElementById('end_date');
    
    function validateDates() {
        if (startDate.value && endDate.value) {
            if (new Date(startDate.value) > new Date(endDate.value)) {
                endDate.setCustomValidity('End date must be after start date');
            } else {
                endDate.setCustomValidity('');
            }
        }
    }
    
    startDate.addEventListener('change', validateDates);
    endDate.addEventListener('change', validateDates);
    
    // Quick date range buttons
    document.addEventListener('click', function(e) {
        if (e.target.matches('.quick-date')) {
            const days = parseInt(e.target.dataset.days);
            const today = new Date();
            const startDateValue = new Date(today.getTime() - (days * 24 * 60 * 60 * 1000));
            
            startDate.value = startDateValue.toISOString().split('T')[0];
            endDate.value = today.toISOString().split('T')[0];
        }
    });
});

function exportToCSV() {
    const table = document.getElementById('reconciliationTable');
    if (!table) return;
    
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    for (let i = 0; i < rows.length; i++) {
        const row = [];
        const cols = rows[i].querySelectorAll('td, th');
        
        for (let j = 0; j < cols.length; j++) {
            let text = cols[j].innerText.replace(/"/g, '""');
            row.push('"' + text + '"');
        }
        csv.push(row.join(','));
    }
    
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    
    a.setAttribute('hidden', '');
    a.setAttribute('href', url);
    a.setAttribute('download', `cash-reconciliation-${<?= json_encode($startDate) ?>}-to-${<?= json_encode($endDate) ?>}.csv`);
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}
</script>

<?php include 'footer.php'; ?>
