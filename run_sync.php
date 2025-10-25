<?php
/**
 * Manual sync trigger
 */
header('Content-Type: application/json');

$output = shell_exec('php /var/www/finance/svp/sync_pos_sales.php 2>&1');

if (strpos($output, 'Sync Complete') !== false) {
    echo json_encode([
        'success' => true,
        'message' => 'Sync completed successfully'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Sync failed - check logs'
    ]);
}
?>
