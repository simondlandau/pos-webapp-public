<?php
require_once 'config.php';

// Set page variables for header
$pageTitle = 'MSSQL Sales Reports';
$headerTitle = 'Sales Analysis Reports - POS Company Name';
$additionalCSS = '
<style>
.report-card {
    transition: transform 0.2s;
    border-left: 4px solid #007bff;
}
.report-card:hover {
    transform: translateY(-1px);
}
.filters-card {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
}
.result-highlight {
    font-size: 2rem;
    font-weight: bold;
    color: #28a745;
}
.table-container {
    max-height: 400px;
    overflow-y: auto;
}
.loading {
    display: none;
}
.error-message {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
    border-radius: 0.375rem;
    padding: 0.75rem;
}
.success-message {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
    border-radius: 0.375rem;
    padding: 0.75rem;
}
.level-indicator {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 0.75rem;
    font-weight: bold;
    margin-left: 8px;
}
.level-1 { background: #007bff; color: white; }
.level-2 { background: #17a2b8; color: white; }
.level-3 { background: #28a745; color: white; }
</style>';

// Initialize variables
$selectedLevel = '';
$selectedValue = '';
$startDate = date('Y-m-01');
$endDate = date('Y-m-d');
$reportData = [];
$summary = [];
$errorMessage = '';

// Fetch hierarchical data from STOCKMST
$hierarchyData = [];
try {
    $stmt = $sqlsrv_pdo->query("
        SELECT DISTINCT 
            \"PM_ANAL1\",
            \"PM_ANAL2\",
            \"PM_DESC\",
            \"PM_PART\"
        FROM \"STOCKMST\" 
        WHERE \"PM_PART\" IS NOT NULL
        ORDER BY \"PM_ANAL1\", \"PM_ANAL2\", \"PM_DESC\"
    ");
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $anal1 = $row['PM_ANAL1'] ?? '';
        $anal2 = $row['PM_ANAL2'] ?? '';
        $desc = $row['PM_DESC'] ?? '';
        $part = $row['PM_PART'];
        
        if (!isset($hierarchyData[$anal1])) {
            $hierarchyData[$anal1] = ['items' => [], 'children' => []];
        }
        $hierarchyData[$anal1]['items'][] = $part;
        
        if (!isset($hierarchyData[$anal1]['children'][$anal2])) {
            $hierarchyData[$anal1]['children'][$anal2] = ['items' => [], 'children' => []];
        }
        $hierarchyData[$anal1]['children'][$anal2]['items'][] = $part;
        
        if (!isset($hierarchyData[$anal1]['children'][$anal2]['children'][$desc])) {
            $hierarchyData[$anal1]['children'][$anal2]['children'][$desc] = [];
        }
        $hierarchyData[$anal1]['children'][$anal2]['children'][$desc][] = $part;
    }
} catch (PDOException $e) {
    $errorMessage = "Error fetching hierarchy: " . $e->getMessage();
}

// Process form submission
if ($_POST) {
    $selectedLevel = $_POST['selection_level'] ?? '';
    $selectedValue = $_POST['selection_value'] ?? '';
    $startDate = $_POST['start_date'] ?? $startDate;
    $endDate = $_POST['end_date'] ?? $endDate;
    
    if ($selectedLevel && $selectedValue && $startDate && $endDate) {
        try {
            // Validate dates
            $startDateTime = new DateTime($startDate);
            $endDateTime = new DateTime($endDate);
            
            if ($startDateTime > $endDateTime) {
                $errorMessage = "Start date cannot be after end date.";
            } else {
                // Get list of PM_PART values based on selection
                $partNumbers = [];
                
                if ($selectedLevel == 'anal1') {
                    $partNumbers = $hierarchyData[$selectedValue]['items'] ?? [];
                } elseif ($selectedLevel == 'anal2') {
                    // Find anal2 across all anal1
                    foreach ($hierarchyData as $anal1Data) {
                        if (isset($anal1Data['children'][$selectedValue])) {
                            $partNumbers = $anal1Data['children'][$selectedValue]['items'];
                            break;
                        }
                    }
                } elseif ($selectedLevel == 'desc') {
                    // Find desc across all anal1/anal2
                    foreach ($hierarchyData as $anal1Data) {
                        foreach ($anal1Data['children'] as $anal2Data) {
                            if (isset($anal2Data['children'][$selectedValue])) {
                                $partNumbers = $anal2Data['children'][$selectedValue];
                                break 2;
                            }
                        }
                    }
                }
                
                if (empty($partNumbers)) {
                    $errorMessage = "No matching items found in STOCKMST.";
                } else {
                    // Create placeholders for IN clause
                    $placeholders = str_repeat('?,', count($partNumbers) - 1) . '?';
                    
                    // Fetch detailed sales data
                    $stmt = $sqlsrv_pdo->prepare("
                        SELECT 
                            s.\"SN_ITEM\",
                            s.\"SN_ITEM\",
                            s.\"SN_Actual\",
                            s.\"dtTimeStamp\",
                            s.\"PostedDate\",
                            s.\"SN_QTY\",
                            sm.\"PM_ANAL1\",
                            sm.\"PM_ANAL2\",
                            sm.\"PM_DESC\"
                        FROM \"SALES\" s
                        INNER JOIN \"STOCKMST\" sm ON s.\"SN_ITEM\" = sm.\"PM_PART\"
                        WHERE s.\"SN_ITEM\" IN ($placeholders)
                          AND s.\"dtTimeStamp\" >= ? 
                          AND s.\"dtTimeStamp\" <= ?
                        ORDER BY s.\"dtTimeStamp\" DESC
                    ");
                    
                    $params = array_merge(
                        $partNumbers,
                        [$startDate . ' 00:00:00', $endDate . ' 23:59:59']
                    );
                    
                    $stmt->execute($params);
                    $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Get summary statistics
                    $summaryStmt = $sqlsrv_pdo->prepare("
                        SELECT 
                            SUM(s.\"SN_Actual\") as total_sales,
                            COUNT(*) as transaction_count,
                            AVG(s.\"SN_Actual\") as avg_sale,
                            MIN(s.\"SN_Actual\") as min_sale,
                            MAX(s.\"SN_Actual\") as max_sale,
                            SUM(s.\"SN_QTY\") as total_quantity,
                            COUNT(DISTINCT s.\"SN_ITEM\") as unique_items
                        FROM \"SALES\" s
                        WHERE s.\"SN_ITEM\" IN ($placeholders)
                          AND s.\"dtTimeStamp\" >= ? 
                          AND s.\"dtTimeStamp\" <= ?
                    ");
                    
                    $summaryStmt->execute($params);
                    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);
                }
            }
        } catch (Exception $e) {
            $errorMessage = "Error generating report: " . $e->getMessage();
        }
    } else {
        $errorMessage = "Please complete all filter fields.";
    }
}

include 'header.php';
?>

<div class="container my-4" style="max-width: 1400px;">
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
                    <h2 class="card-title mb-1">Hierarchical Sales Analysis Report</h2>
                    <p class="card-text text-muted">Analyze sales by Category, Sub-Category, or Item Description</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Error/Success Messages -->
    <?php if ($errorMessage): ?>
    <div class="row mb-3">
        <div class="col-12">
            <div class="error-message">
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
                            <div class="col-md-6">
                                <label for="selection_level" class="form-label">Select Level</label>
                                <select class="form-select" id="selection_level" name="selection_level" required>
                                    <option value="">-- Select Level --</option>
                                    <option value="anal1" <?= $selectedLevel === 'anal1' ? 'selected' : '' ?>>
                                        Level 1 - Category
                                    </option>
                                    <option value="anal2" <?= $selectedLevel === 'anal2' ? 'selected' : '' ?>>
                                        Level 2 - Sub-Category
                                    </option>
                                    <option value="desc" <?= $selectedLevel === 'desc' ? 'selected' : '' ?>>
                                        Level 3 - Item Description
                                    </option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="selection_value" class="form-label">Select Item</label>
                                <select class="form-select" id="selection_value" name="selection_value" required>
                                    <option value="">-- First select a level --</option>
                                </select>
                            </div>
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
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search me-1"></i> Generate Report
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php if ($selectedLevel && !$errorMessage && !empty($reportData)): ?>
    <!-- Summary Statistics -->
    <div class="row mb-4 g-3">
        <div class="col-md-2">
            <div class="card text-center shadow-sm">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted">Total Sales</h6>
                    <div class="result-highlight">€<?= number_format($summary['total_sales'] ?? 0, 2) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center shadow-sm">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted">Transactions</h6>
                    <div class="result-highlight text-info"><?= number_format($summary['transaction_count'] ?? 0) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center shadow-sm">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted">Average Sale</h6>
                    <div class="result-highlight text-warning">€<?= number_format($summary['avg_sale'] ?? 0, 2) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center shadow-sm">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted">Total Quantity</h6>
                    <div class="result-highlight text-success"><?= number_format($summary['total_quantity'] ?? 0) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center shadow-sm">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted">Unique Items</h6>
                    <div class="result-highlight text-primary"><?= number_format($summary['unique_items'] ?? 0) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center shadow-sm">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted">Max Sale</h6>
                    <div class="text-muted">€<?= number_format($summary['max_sale'] ?? 0, 2) ?></div>
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
                        Sales Details for "<?= htmlspecialchars($selectedValue) ?>"
                        <span class="level-indicator level-<?= $selectedLevel === 'anal1' ? '1' : ($selectedLevel === 'anal2' ? '2' : '3') ?>">
                            <?= $selectedLevel === 'anal1' ? 'Level 1' : ($selectedLevel === 'anal2' ? 'Level 2' : 'Level 3') ?>
                        </span>
                        <small class="text-muted d-block mt-1">(<?= htmlspecialchars($startDate) ?> to <?= htmlspecialchars($endDate) ?>)</small>
                    </h5>
                    <button class="btn btn-outline-success btn-sm" onclick="exportToCSV()">
                        <i class="fas fa-download me-1"></i> Export CSV
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-container">
                        <table class="table table-striped table-hover mb-0" id="salesTable">
                            <thead class="table-dark sticky-top">
                                <tr>
                                    <th>Date/Time</th>
                                    <th>Category</th>
                                    <th>Sub-Category</th>
                                    <th>Description</th>
                                    <th>Part #</th>
                                    <th class="text-end">Qty</th>
                                    <th class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reportData as $row): ?>
                                <tr>
                                    <td><?= date('M j, Y H:i', strtotime($row['dtTimeStamp'])) ?></td>
                                    <td><?= htmlspecialchars($row['PM_ANAL1'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($row['PM_ANAL2'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($row['PM_DESC'] ?? '') ?></td>
                                    <td><code><?= htmlspecialchars($row['SN_ITEM']) ?></code></td>
                                    <td class="text-end"><?= number_format($row['SN_QTY'] ?? 0) ?></td>
                                    <td class="text-end">€<?= number_format($row['SN_Actual'], 2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-secondary">
                                <tr class="fw-bold">
                                    <td colspan="5">TOTAL</td>
                                    <td class="text-end"><?= number_format($summary['total_quantity'] ?? 0) ?></td>
                                    <td class="text-end">€<?= number_format($summary['total_sales'] ?? 0, 2) ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php elseif ($selectedLevel && !$errorMessage && empty($reportData)): ?>
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body p-4 text-center text-muted">
                    <i class="fas fa-search fa-3x mb-3"></i>
                    <p class="mb-0">No sales found for the selected criteria and date range.</p>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
// Hierarchy data from PHP
const hierarchyData = <?= json_encode($hierarchyData) ?>;

document.addEventListener('DOMContentLoaded', function() {
    const levelSelect = document.getElementById('selection_level');
    const valueSelect = document.getElementById('selection_value');
    const startDate = document.getElementById('start_date');
    const endDate = document.getElementById('end_date');
    
    // Populate value dropdown based on level selection
    levelSelect.addEventListener('change', function() {
        valueSelect.innerHTML = '<option value="">-- Select Item --</option>';
        const level = this.value;
        
        if (level === 'anal1') {
            // Populate with PM_ANAL1 values
            Object.keys(hierarchyData).sort().forEach(anal1 => {
                if (anal1) {
                    const option = document.createElement('option');
                    option.value = anal1;
                    option.textContent = anal1;
                    valueSelect.appendChild(option);
                }
            });
        } else if (level === 'anal2') {
            // Populate with all unique PM_ANAL2 values
            const anal2Set = new Set();
            Object.values(hierarchyData).forEach(anal1Data => {
                Object.keys(anal1Data.children).forEach(anal2 => {
                    if (anal2) anal2Set.add(anal2);
                });
            });
            Array.from(anal2Set).sort().forEach(anal2 => {
                const option = document.createElement('option');
                option.value = anal2;
                option.textContent = anal2;
                valueSelect.appendChild(option);
            });
        } else if (level === 'desc') {
            // Populate with all unique PM_DESC values
            const descSet = new Set();
            Object.values(hierarchyData).forEach(anal1Data => {
                Object.values(anal1Data.children).forEach(anal2Data => {
                    Object.keys(anal2Data.children).forEach(desc => {
                        if (desc) descSet.add(desc);
                    });
                });
            });
            Array.from(descSet).sort().forEach(desc => {
                const option = document.createElement('option');
                option.value = desc;
                option.textContent = desc;
                valueSelect.appendChild(option);
            });
        }
        
        // Restore selected value if it exists
        const savedValue = '<?= htmlspecialchars($selectedValue) ?>';
        if (savedValue && level === '<?= htmlspecialchars($selectedLevel) ?>') {
            valueSelect.value = savedValue;
        }
    });
    
    // Trigger change if level is pre-selected
    if (levelSelect.value) {
        levelSelect.dispatchEvent(new Event('change'));
    }
    
    // Validate date range
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
});

function exportToCSV() {
    const table = document.getElementById('salesTable');
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
    a.setAttribute('download', 'sales-report-<?= date('Y-m-d') ?>.csv');
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}
</script>

<?php include 'footer.php'; ?>
