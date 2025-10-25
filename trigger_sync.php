<?php
/**
 * Background sync trigger (non-blocking)
 */
header('Content-Type: application/json');

$lock_file = __DIR__ . '/tmp/sync.lock';

// Check if sync is already running
if (file_exists($lock_file)) {
    $lock_age = time() - filemtime($lock_file);
    if ($lock_age < 300) {
        echo json_encode([
            'success' => false,
            'message' => 'Sync already running',
            'locked_for' => $lock_age
        ]);
        exit;
    }
}

// Trigger sync in background
$php_path = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') 
    ? 'C:\xampp\php\php.exe' 
    : 'php';

$script_path = __DIR__ . '/sync_pos_sales.php';

if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    // Windows - run in background
    pclose(popen("start /B \"\" \"$php_path\" \"$script_path\"", "r"));
} else {
    // Linux - run in background
    exec("$php_path $script_path > /dev/null 2>&1 &");
}

echo json_encode([
    'success' => true,
    'message' => 'Sync triggered in background',
    'time' => date('Y-m-d H:i:s')
]);
?>
