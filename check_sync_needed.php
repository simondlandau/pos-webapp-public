<?php
/**
 * Check if sync is needed (backup mechanism)
 */
header('Content-Type: application/json');

$sync_file = __DIR__ . '/tmp/last_pos_sync.txt';
$last_sync = file_exists($sync_file) ? filemtime($sync_file) : 0;
$minutes_ago = $last_sync > 0 ? round((time() - $last_sync) / 60) : 999;

// Check if within business hours
$hour = (int)date('G');
$day = (int)date('N'); // 1=Monday, 7=Sunday

$in_business_hours = ($day >= 1 && $day <= 6 && $hour >= 10 && $hour <= 17);

// If more than 20 minutes since last sync AND in business hours
$sync_needed = ($minutes_ago > 20 && $in_business_hours);

// Check if sync is currently running
$lock_file = __DIR__ . '/tmp/sync.lock';
$sync_running = file_exists($lock_file);

echo json_encode([
    'sync_needed' => $sync_needed && !$sync_running,
    'sync_running' => $sync_running,
    'last_sync_minutes_ago' => $minutes_ago,
    'in_business_hours' => $in_business_hours,
    'next_check_in' => 300 // seconds
]);
?>
