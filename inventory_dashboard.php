<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Try to load config
try {
    require_once 'config.php';
} catch (Exception $e) {
    die("Config error: " . $e->getMessage());
}

$pageTitle = 'Inventory Dashboard';
$headerTitle = 'Scanned Inventory Dashboard - St. Vincents';

// Get summary statistics from MySQL
$todayStats = [];
$overallStats = [];
$floorInventory = [];
$recentScans = [];
$error = null;

try {
    // Today's stats
    $todayStmt = $pdo->query("
        SELECT 
            COUNT(*) as today_scans,
            COUNT(DISTINCT Barcode) as unique_products,
            SUM(CASE WHEN Status = 'OnFloor' THEN Quantity ELSE 0 END) as on_floor,
            SUM(CASE WHEN Status = 'Sold' THEN Quantity ELSE 0 END) as sold_today
        FROM InventoryScans
        WHERE DATE(ScanDateTime) = CURDATE()
    ");
    $todayStats = $todayStmt->fetch(PDO::FETCH_ASSOC);
    $todayStmt->closeCursor();
    
    // Overall stats
    $overallStmt = $pdo->query("
        SELECT 
            COUNT(*) as total_scans,
            SUM(CASE WHEN Status = 'OnFloor' THEN Quantity ELSE 0 END) as total_on_floor,
            COUNT(DISTINCT Barcode) as total_unique
        FROM InventoryScans
    ");
    $overallStats = $overallStmt->fetch(PDO::FETCH_ASSOC);
    $overallStmt->closeCursor();
    
    // Get current floor inventory
    $inventoryStmt = $pdo->query("
        SELECT * FROM vw_CurrentFloorInventory
        ORDER BY QuantityOnFloor DESC
    ");
    $floorInventory = $inventoryStmt->fetchAll(PDO::FETCH_ASSOC);
    $inventoryStmt->closeCursor();
    
    // Get recent scans
    $recentStmt = $pdo->query("
        SELECT 
            ScanID,
            Barcode,
            ProductName,
            ScannedBy,
            ScanDateTime,
            Status,
            Location
        FROM InventoryScans
        ORDER BY ScanDateTime DESC
        LIMIT 20
    ");
    $recentScans = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
    $recentStmt->closeCursor();
    
} catch (Exception $e) {
    $error = $e->getMessage();
}

// Try to include header
if (file_exists('header.php')) {
    include 'header.php';
} else {
    // Simple header if header.php doesn't exist
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . $pageTitle . '</title>';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">';
    echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">';
    echo '</head><body>';
}
?>

<style>
.dashboard-card {
    transition: transform 0.2s;
}
.dashboard-card:hover {
    transform: translateY(-2px);
}
.stat-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
}
.stat-card.green {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
}
.stat-card.blue {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
}
.stat-card.orange {
    background: linear-gradient(135deg, #fd7e14 0%, #dc3545 100%);
}
.stat-value {
    font-size: 2.5rem;
    font-weight: bold;
    margin: 10px 0;
}
.stat-label {
    font-size: 0.9rem;
    opacity: 0.9;
}
.table-container {
    max-height: 500px;
    overflow-y: auto;
}
.badge-on-floor {
    background: #28a745;
}
.badge-sold {
    background: #6c757d;
}
.dashboard-section {
    background: white;
    padding: 20px;
    margin: 20px 0;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.sync-status-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    border-left: 5px solid;
}

.status-good {
    background: #d4edda;
    border-color: #28a745;
}

.status-warning {
    background: #fff3cd;
    border-color: #ffc107;
}

.status-error {
    background: #f8d7da;
    border-color: #dc3545;
}

.status-unknown {
    background: #e2e3e5;
    border-color: #6c757d;
}

.sync-info {
    display: flex;
    flex-direction: column;
}

.sync-label {
    font-weight: 600;
    font-size: 14px;
    color: #666;
}

.sync-value {
    font-size: 18px;
    font-weight: bold;
    margin: 5px 0;
}

.sync-ago {
    font-size: 12px;
    color: #999;
}

.sync-badge {
    padding: 10px 20px;
    background: rgba(255,255,255,0.8);
    border-radius: 20px;
    font-weight: bold;
    font-size: 14px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin: 20px 0;
}

.stat-card {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    text-align: center;
    border: 2px solid #dee2e6;
}

.stat-value {
    font-size: 32px;
    font-weight: bold;
    color: #28a745;
}

.stat-label {
    font-size: 12px;
    color: #6c757d;
    margin-top: 5px;
    text-transform: uppercase;
}

.sales-table, .top-sellers-table, .low-stock-table {
    width: 100%;
    border-collapse: collapse;
    margin: 15px 0;
}

.sales-table th, .top-sellers-table th, .low-stock-table th {
    background: #f8f9fa;
    padding: 12px;
    text-align: left;
    font-weight: 600;
    border-bottom: 2px solid #dee2e6;
}

.sales-table td, .top-sellers-table td, .low-stock-table td {
    padding: 10px 12px;
    border-bottom: 1px solid #dee2e6;
}

.low-stock-table tr.critical {
    background: #fff5f5;
    color: #dc3545;
}

.low-stock-table tr.warning {
    background: #fffef5;
    color: #ffc107;
}

.sales-table code, .top-sellers-table code, .low-stock-table code {
    background: #f8f9fa;
    padding: 4px 8px;
    border-radius: 4px;
    font-family: monospace;
    font-size: 13px;
}

.sync-log {
    background: #000;
    color: #0f0;
    padding: 15px;
    border-radius: 8px;
    font-family: 'Courier New', monospace;
    font-size: 12px;
    max-height: 300px;
    overflow-y: auto;
    margin: 15px 0;
}

.log-line {
    padding: 3px 0;
    white-space: pre-wrap;
}

.log-error {
    color: #ff6b6b;
}

.log-warning {
    color: #ffd93d;
}

.log-success {
    color: #6bcf7f;
}

.sync-actions {
    margin-top: 20px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 15px;
}

#sync-result {
    padding: 8px 15px;
    border-radius: 4px;
    font-weight: 600;
}

#sync-result.success {
    background: #d4edda;
    color: #155724;
}

#sync-result.error {
    background: #f8d7da;
    color: #721c24;
}


.btn {
    padding: 12px 24px;
    border: none;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    font-size: 16px;
}

.btn-primary {
    background: #28a745;
    color: white;
}

.btn-primary:hover {
    background: #218838;
}

.btn-primary:disabled {
    background: #6c757d;
    cursor: not-allowed;
}

.no-data {
    text-align: center;
    color: #6c757d;
    padding: 20px;
    font-style: italic;
}

#sync-result {
    padding: 8px 15px;
    border-radius: 4px;
    font-weight: 600;
}

#sync-result.success {
    background: #d4edda;
    color: #155724;
}

#sync-result.error {
    background: #f8d7da;
    color: #721c24;
}
</style>
<!-- POS Sync Status Widget -->
<div class="dashboard-section">
    <h2>🔄 POS Sync Status</h2>
    
    <?php
    // Check last sync time
    $sync_file = '/tmp/last_pos_sync.txt';
    $last_sync = file_exists($sync_file) ? file_get_contents($sync_file) : 'Never';
    $last_sync_time = strtotime($last_sync);
    $minutes_ago = $last_sync_time ? round((time() - $last_sync_time) / 60) : 0;
    
    // Determine sync status
    $sync_status = 'Unknown';
    $status_class = 'status-unknown';
    if ($minutes_ago <= 20) {
        $sync_status = 'Current';
        $status_class = 'status-good';
    } elseif ($minutes_ago <= 60) {
        $sync_status = 'Recent';
        $status_class = 'status-warning';
    } else {
        $sync_status = 'Stale';
        $status_class = 'status-error';
    }
    ?>
    
    <div class="sync-status-card <?php echo $status_class; ?>">
        <div class="sync-info">
            <span class="sync-label">Last Sync:</span>
            <span class="sync-value"><?php echo $last_sync; ?></span>
            <span class="sync-ago">(<?php echo $minutes_ago; ?> minutes ago)</span>
        </div>
        <div class="sync-badge">
            <?php echo $sync_status; ?>
        </div>
    </div>
    
    <!-- Recent Sales Activity (Last Hour) -->
    <h3>Recent Sales (Last Hour)</h3>
    <?php
    $recent_sales = $pdo->query("
        SELECT 
            ProductID,
            ProductName,
            COUNT(*) as items_sold,
            SUM(Quantity) as total_qty,
            MAX(SUBSTRING_INDEX(Notes, 'Sold via POS on ', -1)) as last_sale_time
        FROM InventoryScans
        WHERE Status = 'Sold'
        AND Notes LIKE '%Sold via POS%'
        AND SUBSTRING_INDEX(Notes, 'Sold via POS on ', -1) >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        GROUP BY ProductID, ProductName
        ORDER BY last_sale_time DESC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($recent_sales) > 0) {
        echo '<table class="sales-table">';
        echo '<thead><tr><th>Product ID</th><th>Product Name</th><th>Qty Sold</th><th>Last Sale</th></tr></thead>';
        echo '<tbody>';
        foreach ($recent_sales as $sale) {
            echo '<tr>';
            echo '<td><code>' . htmlspecialchars($sale['ProductID']) . '</code></td>';
            echo '<td>' . htmlspecialchars($sale['ProductName']) . '</td>';
            echo '<td><strong>' . $sale['total_qty'] . '</strong></td>';
            echo '<td>' . date('H:i:s', strtotime($sale['last_sale_time'])) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<p class="no-data">No sales in the last hour</p>';
    }
    ?>
    
    <!-- Today's Sales Summary -->
    <h3>📊 Today's Sales Summary</h3>
    <?php
    $today_stats = $pdo->query("
        SELECT 
            COUNT(DISTINCT ProductID) as unique_products,
            SUM(Quantity) as total_items_sold,
            COUNT(*) as transactions
        FROM InventoryScans
        WHERE Status = 'Sold'
        AND Notes LIKE '%Sold via POS%'
        AND DATE(SUBSTRING_INDEX(Notes, 'Sold via POS on ', -1)) = CURDATE()
    ")->fetch(PDO::FETCH_ASSOC);
    ?>
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?php echo $today_stats['total_items_sold'] ?? 0; ?></div>
            <div class="stat-label">Items Sold</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $today_stats['unique_products'] ?? 0; ?></div>
            <div class="stat-label">Unique Products</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $today_stats['transactions'] ?? 0; ?></div>
            <div class="stat-label">Sync Transactions</div>
        </div>
    </div>
    
    <!-- Top Sellers Today -->
    <h3>🏆 Top Sellers Today</h3>
    <?php
    $top_sellers = $pdo->query("
        SELECT 
            ProductID,
            ProductName,
            SUM(Quantity) as total_sold
        FROM InventoryScans
        WHERE Status = 'Sold'
        AND Notes LIKE '%Sold via POS%'
        AND DATE(SUBSTRING_INDEX(Notes, 'Sold via POS on ', -1)) = CURDATE()
        GROUP BY ProductID, ProductName
        ORDER BY total_sold DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($top_sellers) > 0) {
        echo '<table class="top-sellers-table">';
        echo '<thead><tr><th>Rank</th><th>Product ID</th><th>Product Name</th><th>Qty Sold</th></tr></thead>';
        echo '<tbody>';
        $rank = 1;
        foreach ($top_sellers as $seller) {
            $medal = '';
            if ($rank == 1) $medal = '🥇';
            elseif ($rank == 2) $medal = '🥈';
            elseif ($rank == 3) $medal = '🥉';
            
            echo '<tr>';
            echo '<td>' . $medal . ' ' . $rank . '</td>';
            echo '<td><code>' . htmlspecialchars($seller['ProductID']) . '</code></td>';
            echo '<td>' . htmlspecialchars($seller['ProductName']) . '</td>';
            echo '<td><strong>' . $seller['total_sold'] . '</strong></td>';
            echo '</tr>';
            $rank++;
        }
        echo '</tbody></table>';
    } else {
        echo '<p class="no-data">No sales today yet</p>';
    }
    ?>
    
    <!-- Low Stock Alerts -->
    <h3>⚠️ Low Stock Alerts (After Sales)</h3>
    <?php
    $low_stock = $pdo->query("
        SELECT 
            ProductID,
            ProductName,
            SUM(Quantity) as remaining,
            (SELECT SUM(Quantity) 
             FROM InventoryScans i2 
             WHERE i2.ProductID = i1.ProductID 
             AND i2.Status = 'Sold'
             AND DATE(SUBSTRING_INDEX(i2.Notes, 'Sold via POS on ', -1)) = CURDATE()
            ) as sold_today
        FROM InventoryScans i1
        WHERE Status = 'OnFloor'
        GROUP BY ProductID, ProductName
        HAVING remaining <= 5 AND remaining > 0
        ORDER BY remaining ASC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($low_stock) > 0) {
        echo '<table class="low-stock-table">';
        echo '<thead><tr><th>Product ID</th><th>Product Name</th><th>Remaining</th><th>Sold Today</th></tr></thead>';
        echo '<tbody>';
        foreach ($low_stock as $item) {
            $alert_class = $item['remaining'] <= 2 ? 'critical' : 'warning';
            echo '<tr class="' . $alert_class . '">';
            echo '<td><code>' . htmlspecialchars($item['ProductID']) . '</code></td>';
            echo '<td>' . htmlspecialchars($item['ProductName']) . '</td>';
            echo '<td><strong>' . $item['remaining'] . '</strong></td>';
            echo '<td>' . ($item['sold_today'] ?? 0) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<p class="no-data">✓ All items have adequate stock</p>';
    }
    ?>
    
    <!-- Sync Log (Last 10 entries) -->
    <h3>📝 Recent Sync Log</h3>
    <?php
    $log_file = './logs/pos_sync.log';
    if (file_exists($log_file)) {
        $log_lines = array_slice(file($log_file), -20); // Last 20 lines
        echo '<div class="sync-log">';
        foreach (array_reverse($log_lines) as $line) {
            $line = htmlspecialchars(trim($line));
            $class = '';
            if (strpos($line, 'ERROR') !== false) $class = 'log-error';
            elseif (strpos($line, 'WARNING') !== false) $class = 'log-warning';
            elseif (strpos($line, '✓') !== false) $class = 'log-success';
            
            echo '<div class="log-line ' . $class . '">' . $line . '</div>';
        }
        echo '</div>';
    } else {
        echo '<p class="no-data">Log file not found</p>';
    }
    ?>
    
<!-- Manual Sync Button -->
<div class="sync-actions">
    <button onclick="runManualSync()" class="btn btn-primary" id="syncButton">
        🔄 Run Sync Now
    </button>
    <span id="sync-result"></span>
</div>
</div>
<div class="container-fluid my-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2>📦 Inventory Dashboard</h2>
                    <p class="text-muted">Real-time view of scanned inventory</p>
                </div>
                <div>
                    <a href="dashboard.php" class="btn btn-outline-secondary me-2">← Back to Dashboard</a>
                    <a href="inventory_scanner.html" class="btn btn-success" target="_blank">
                        📱 Open Scanner
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($error)): ?>
    <div class="alert alert-danger">
        <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card green">
                <div class="stat-label">TODAY'S SCANS</div>
                <div class="stat-value"><?php echo number_format($todayStats['today_scans'] ?? 0); ?></div>
                <small><?php echo $todayStats['unique_products'] ?? 0; ?> unique products</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card blue">
                <div class="stat-label">ITEMS ON FLOOR</div>
                <div class="stat-value"><?php echo number_format($todayStats['on_floor'] ?? 0); ?></div>
                <small>Available for sale</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card orange">
                <div class="stat-label">SOLD TODAY</div>
                <div class="stat-value"><?php echo number_format($todayStats['sold_today'] ?? 0); ?></div>
                <small>Processed through POS</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-label">TOTAL INVENTORY</div>
                <div class="stat-value"><?php echo number_format($overallStats['total_on_floor'] ?? 0); ?></div>
                <small><?php echo $overallStats['total_unique'] ?? 0; ?> unique items</small>
            </div>
        </div>
    </div>

    <!-- Current Floor Inventory -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm dashboard-card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">📍 Current Floor Inventory</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-container">
                        <table class="table table-hover mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>Barcode</th>
                                    <th>Product Name</th>
                                    <th class="text-center">Quantity</th>
                                    <th>First Scanned</th>
                                    <th>Last Scanned</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($floorInventory)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">
                                        No items currently on floor
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($floorInventory as $item): ?>
                                <tr>
                                    <td>
                                        <code><?php echo htmlspecialchars($item['Barcode']); ?></code>
                                    </td>
                                    <td><?php echo htmlspecialchars($item['ProductName']); ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-success rounded-pill">
                                            <?php echo $item['QuantityOnFloor']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo date('M j, H:i', strtotime($item['FirstScanned'])); ?>
                                    </td>
                                    <td>
                                        <?php echo date('M j, H:i', strtotime($item['LastScanned'])); ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" 
                                                onclick="viewDetails('<?php echo htmlspecialchars($item['Barcode'], ENT_QUOTES); ?>')">
                                            View Details
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Scan Activity -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm dashboard-card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">🔄 Recent Scan Activity</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-container">
                        <table class="table table-hover mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>Time</th>
                                    <th>Barcode</th>
                                    <th>Product</th>
                                    <th>Scanned By</th>
                                    <th>Location</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentScans)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">
                                        No recent scan activity
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($recentScans as $scan): ?>
                                <tr>
                                    <td>
                                        <?php echo date('M j, H:i:s', strtotime($scan['ScanDateTime'])); ?>
                                    </td>
                                    <td><code><?php echo htmlspecialchars($scan['Barcode']); ?></code></td>
                                    <td><?php echo htmlspecialchars($scan['ProductName']); ?></td>
                                    <td><?php echo htmlspecialchars($scan['ScannedBy'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($scan['Location'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php
                                        $statusClass = $scan['Status'] === 'OnFloor' ? 'badge-on-floor' : 'badge-sold';
                                        echo '<span class="badge ' . $statusClass . '">';
                                        echo htmlspecialchars($scan['Status']);
                                        echo '</span>';
                                        ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal for Item Details -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Item Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modalContent">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

// Auto-refresh every 30 seconds
setInterval(() => {
    location.reload();
}, 30000);

// View item details
function viewDetails(barcode) {
    const modal = new bootstrap.Modal(document.getElementById('detailsModal'));
    const content = document.getElementById('modalContent');
    
    modal.show();
    
    // Fetch details
    fetch('inventory_api.php?action=history&barcode=' + encodeURIComponent(barcode))
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                content.innerHTML = generateDetailsHTML(barcode, data.history);
            } else {
                content.innerHTML = '<div class="alert alert-danger">Failed to load details</div>';
            }
        })
        .catch(error => {
            content.innerHTML = '<div class="alert alert-danger">Error loading details</div>';
        });
}

function generateDetailsHTML(barcode, history) {
    let html = '<div class="mb-3"><h6>Barcode: <code>' + barcode + '</code></h6></div>';
    html += '<div class="table-responsive"><table class="table table-sm">';
    html += '<thead><tr><th>Scan Time</th><th>Scanned By</th><th>Location</th><th>Status</th></tr></thead>';
    html += '<tbody>';
    
    if (history && history.length > 0) {
        history.forEach(item => {
            const statusBadge = item.Status === 'OnFloor' ? 
                '<span class="badge bg-success">On Floor</span>' : 
                '<span class="badge bg-secondary">Sold</span>';
            
            html += '<tr>';
            html += '<td>' + formatDateTime(item.ScanDateTime) + '</td>';
            html += '<td>' + (item.ScannedBy || 'N/A') + '</td>';
            html += '<td>' + (item.Location || 'N/A') + '</td>';
            html += '<td>' + statusBadge + '</td>';
            html += '</tr>';
        });
    } else {
        html += '<tr><td colspan="4" class="text-center">No history found</td></tr>';
    }
    
    html += '</tbody></table></div>';
    return html;
}

function formatDateTime(dateStr) {
    if (!dateStr) return 'N/A';
    const date = new Date(dateStr);
    return date.toLocaleString('en-IE', { 
        month: 'short', 
        day: 'numeric', 
        hour: '2-digit', 
        minute: '2-digit' 
    });
}

// Show notification for new scans
let lastScanCount = <?php echo $todayStats['today_scans'] ?? 0; ?>;

function checkForNewScans() {
    fetch('inventory_api.php?action=stats')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.stats.today_scans > lastScanCount) {
                showNotification('New items scanned!');
                lastScanCount = data.stats.today_scans;
            }
        })
        .catch(error => console.error('Error checking scans:', error));
}

function showNotification(message) {
    const toast = document.createElement('div');
    toast.className = 'toast position-fixed bottom-0 end-0 m-3';
    toast.setAttribute('role', 'alert');
    toast.innerHTML = '<div class="toast-header bg-success text-white">' +
        '<strong class="me-auto">Inventory Update</strong>' +
        '<button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>' +
        '</div><div class="toast-body">' + message + '</div>';
    
    document.body.appendChild(toast);
    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();
    
    setTimeout(() => toast.remove(), 5000);
}

<script>
// Manual sync function - renamed to avoid conflicts
function runManualSync() {
    console.log('Manual sync button clicked');
    
    const button = document.getElementById('syncButton');
    const resultDiv = document.getElementById('sync-result');
    
    if (!button || !resultDiv) {
        console.error('Button or result div not found!');
        alert('Error: Page elements not found. Please refresh the page.');
        return;
    }
    
    // Disable button during sync
    button.disabled = true;
    button.textContent = '⏳ Syncing...';
    resultDiv.textContent = '';
    resultDiv.className = '';
    
    console.log('Fetching sync_pos_sales_ajax.php...');
    
    // Call sync via AJAX
    fetch('sync_pos_sales_ajax.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' }
    })
    .then(response => {
        console.log('Response received:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('Sync result:', data);
        
        button.disabled = false;
        button.textContent = '🔄 Run Sync Now';
        
        if (data.success) {
            resultDiv.textContent = '✓ ' + data.message;
            resultDiv.className = 'success';
            
            console.log('Sync successful, reloading in 2 seconds...');
            
            // Refresh page after 2 seconds
            setTimeout(() => {
                location.reload();
            }, 2000);
        } else {
            resultDiv.textContent = '✗ ' + data.message;
            resultDiv.className = 'error';
        }
    })
    .catch(error => {
        console.error('Sync error:', error);
        
        button.disabled = false;
        button.textContent = '🔄 Run Sync Now';
        resultDiv.textContent = '✗ Network error: ' + error.message;
        resultDiv.className = 'error';
    });
}

// Test button availability
document.addEventListener('DOMContentLoaded', function() {
    const button = document.getElementById('syncButton');
    const result = document.getElementById('sync-result');
    
    if (button && result) {
        console.log('✓ Sync button initialized successfully');
    } else {
        console.error('✗ Sync button elements not found!');
    }
});
</script>

// Check for new scans every 10 seconds
setInterval(checkForNewScans, 10000);
</script>
<script>
// Real-time dashboard updates
let syncCheckInterval;
let dataRefreshInterval;

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    startAutoRefresh();
});

function startAutoRefresh() {
    // Check if sync needed every 5 minutes
    syncCheckInterval = setInterval(checkSyncNeeded, 300000);
    
    // Refresh dashboard data every 2 minutes
    dataRefreshInterval = setInterval(refreshDashboardData, 120000);
    
    console.log('Auto-refresh started');
}

function checkSyncNeeded() {
    fetch('check_sync_needed.php')
        .then(r => r.json())
        .then(data => {
            console.log('Sync check:', data);
            
            if (data.sync_needed && !data.sync_running) {
                console.log('⚠️ Sync overdue, triggering backup...');
                triggerBackgroundSync();
            }
        })
        .catch(err => console.error('Sync check failed:', err));
}

function refreshDashboardData() {
    fetch('get_dashboard_data.php')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                updateDashboardUI(data);
                console.log('Dashboard refreshed:', data.timestamp);
            }
        })
        .catch(err => console.error('Data refresh failed:', err));
}

function updateDashboardUI(data) {
    // Update sync status
    const syncStatusCard = document.querySelector('.sync-status-card');
    if (syncStatusCard) {
        syncStatusCard.className = 'sync-status-card status-' + 
            (data.sync_status === 'Current' ? 'good' : 
             data.sync_status === 'Recent' ? 'warning' : 'error');
        
        syncStatusCard.querySelector('.sync-value').textContent = data.last_sync;
        syncStatusCard.querySelector('.sync-ago').textContent = 
            '(' + data.minutes_ago + ' minutes ago)';
        syncStatusCard.querySelector('.sync-badge').textContent = data.sync_status;
    }
    
    // Update today's stats
    if (data.today_stats) {
        const statsElements = {
            'total_items_sold': data.today_stats.total_items,
            'unique_products_sold': data.today_stats.unique_products,
            'transactions_today': data.today_stats.transactions
        };
        
        for (let [id, value] of Object.entries(statsElements)) {
            const el = document.getElementById(id);
            if (el) el.textContent = value || 0;
        }
    }
    
    // Update inventory counts
    if (data.inventory_count) {
        const invEl = document.getElementById('inventory_total');
        if (invEl) invEl.textContent = data.inventory_count.total_items || 0;
    }
    
    // Optional: Show notification badge if new sales
    if (data.recent_sales && data.recent_sales.length > 0) {
        showNotificationBadge(data.recent_sales.length);
    }
}

function triggerBackgroundSync() {
    console.log('Auto-triggering background sync...');
    
    fetch('sync_pos_sales_ajax.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            console.log('✓ Auto-sync completed:', data.message);
            // Refresh data after successful sync
            setTimeout(refreshDashboardData, 3000);
        } else {
            console.log('⚠️ Auto-sync issue:', data.message);
        }
    })
    .catch(err => console.error('Auto-sync failed:', err));
}

function showNotificationBadge(count) {
    // Optional: Show browser notification if supported
    if ("Notification" in window && Notification.permission === "granted") {
        new Notification("New POS Sales", {
            body: count + " item(s) sold recently",
            icon: "/svp/icon.png"
        });
    }
}

// Request notification permission on first load
if ("Notification" in window && Notification.permission === "default") {
    Notification.requestPermission();
}

// Log activity
console.log('Dashboard hybrid sync initialized');
console.log('- Data refresh: every 2 minutes');
console.log('- Sync check: every 5 minutes');
console.log('- Task Scheduler: primary (every 15 min, Mon-Sat 10-17)');
console.log('- Dashboard: backup trigger');
</script>
<?php 
if (file_exists('footer.php')) {
    include 'footer.php';
} else {
    echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>';

echo '</body></html>';
}
?>
