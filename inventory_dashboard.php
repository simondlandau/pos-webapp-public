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
$headerTitle = 'Scanned Inventory Dashboard - Company';

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
</style>

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

<script>
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

// Check for new scans every 10 seconds
setInterval(checkForNewScans, 10000);
</script>

<?php 
if (file_exists('footer.php')) {
    include 'footer.php';
} else {
    echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>';
    echo '</body></html>';
}
?>
