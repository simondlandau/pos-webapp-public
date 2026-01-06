<?php
/**
 * Database Connection Test Script
 * Run this from command line: php test_connection.php
 */

// Color output for terminal
function colorOutput($text, $color = 'green') {
    $colors = [
        'green' => "\033[0;32m",
        'red' => "\033[0;31m",
        'yellow' => "\033[1;33m",
        'blue' => "\033[0;34m",
        'reset' => "\033[0m"
    ];
    
    // Check if running in CLI
    if (php_sapi_name() === 'cli') {
        return $colors[$color] . $text . $colors['reset'];
    }
    return $text;
}

echo "\n";
echo "============================================\n";
echo "  Database Connection Test\n";
echo "============================================\n\n";

// Check if config.php exists
if (!file_exists(__DIR__ . '/config.php')) {
    echo colorOutput("✗ ERROR: config.php not found!\n", 'red');
    echo "Please copy config.template.php to config.php and configure it.\n";
    exit(1);
}

require_once __DIR__ . '/config.php';

// Test 1: Check PHP Extensions
echo "Test 1: Checking PHP Extensions...\n";
echo "-----------------------------------\n";

$required_extensions = [
    'mysqli' => 'MySQL',
    'pdo_mysql' => 'PDO MySQL',
    'sqlsrv' => 'SQL Server',
    'pdo_sqlsrv' => 'PDO SQL Server'
];

$extensions_ok = true;
foreach ($required_extensions as $ext => $name) {
    if (extension_loaded($ext)) {
        echo colorOutput("✓ $name ($ext): LOADED\n", 'green');
    } else {
        echo colorOutput("✗ $name ($ext): NOT LOADED\n", 'red');
        $extensions_ok = false;
    }
}

if (!$extensions_ok) {
    echo colorOutput("\n⚠ WARNING: Some required extensions are not loaded.\n", 'yellow');
    echo "Please enable them in php.ini\n";
}

echo "\n";

// Test 2: MySQL Connection
echo "Test 2: Testing MySQL Connection...\n";
echo "-----------------------------------\n";
echo "Host: " . MYSQL_HOST . ":" . MYSQL_PORT . "\n";
echo "Database: " . MYSQL_DATABASE . "\n";
echo "Username: " . MYSQL_USERNAME . "\n";

try {
    $mysql = getMySQLConnection();
    echo colorOutput("✓ MySQL Connection: SUCCESS\n", 'green');
    
    // Test query
    $stmt = $mysql->query("SELECT VERSION() as version");
    $version = $stmt->fetch();
    echo "  MySQL Version: " . $version['version'] . "\n";
    
    // Test database
    $stmt = $mysql->query("SELECT DATABASE() as db");
    $db = $stmt->fetch();
    echo "  Current Database: " . $db['db'] . "\n";
    
    // List tables
    $stmt = $mysql->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "  Tables Found: " . count($tables) . "\n";
    if (count($tables) > 0) {
        echo "  Sample Tables: " . implode(', ', array_slice($tables, 0, 5)) . "\n";
    }
    
} catch (Exception $e) {
    echo colorOutput("✗ MySQL Connection: FAILED\n", 'red');
    echo "  Error: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 3: MSSQL Connection
echo "Test 3: Testing MSSQL Connection...\n";
echo "-----------------------------------\n";
echo "Server: " . MSSQL_SERVER . ":" . MSSQL_PORT . "\n";
echo "Database: " . MSSQL_DATABASE . "\n";
echo "Username: " . MSSQL_USERNAME . "\n";

try {
    $mssql = getMSSQLConnection();
    echo colorOutput("✓ MSSQL Connection: SUCCESS\n", 'green');
    
    // Test query
    $stmt = $mssql->query("SELECT @@VERSION as version");
    $version = $stmt->fetch();
    echo "  SQL Server Version: " . substr($version['version'], 0, 100) . "...\n";
    
    // Test database
    $stmt = $mssql->query("SELECT DB_NAME() as db");
    $db = $stmt->fetch();
    echo "  Current Database: " . $db['db'] . "\n";
    
    // List tables
    $stmt = $mssql->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "  Tables Found: " . count($tables) . "\n";
    if (count($tables) > 0) {
        echo "  Sample Tables: " . implode(', ', array_slice($tables, 0, 5)) . "\n";
    }
    
} catch (Exception $e) {
    echo colorOutput("✗ MSSQL Connection: FAILED\n", 'red');
    echo "  Error: " . $e->getMessage() . "\n";
    echo "\n";
    echo colorOutput("  Troubleshooting Tips:\n", 'yellow');
    echo "  1. Verify MSSQL Server IP: " . MSSQL_SERVER . "\n";
    echo "  2. Check if SQL Server allows remote connections\n";
    echo "  3. Verify TCP/IP is enabled in SQL Server Configuration Manager\n";
    echo "  4. Test connectivity: telnet " . MSSQL_SERVER . " " . MSSQL_PORT . "\n";
    echo "  5. Check Windows Firewall allows port " . MSSQL_PORT . "\n";
    echo "  6. Verify credentials are correct\n";
}

echo "\n";

// Test 4: File Permissions
echo "Test 4: Checking File Permissions...\n";
echo "-----------------------------------\n";

$directories = [
    'logs' => LOG_PATH,
    'uploads' => __DIR__ . '/uploads'
];

foreach ($directories as $name => $path) {
    if (!is_dir($path)) {
        echo colorOutput("⚠ $name: Directory does not exist\n", 'yellow');
        echo "  Creating: $path\n";
        if (@mkdir($path, 0755, true)) {
            echo colorOutput("  ✓ Created successfully\n", 'green');
        } else {
            echo colorOutput("  ✗ Failed to create\n", 'red');
        }
    } else if (is_writable($path)) {
        echo colorOutput("✓ $name: WRITABLE\n", 'green');
        echo "  Path: $path\n";
    } else {
        echo colorOutput("✗ $name: NOT WRITABLE\n", 'red');
        echo "  Path: $path\n";
        echo "  Run: icacls \"$path\" /grant Users:(OI)(CI)F /T\n";
    }
}

echo "\n";

// Test 5: Configuration Summary
echo "Test 5: Configuration Summary...\n";
echo "-----------------------------------\n";
echo "Application URL: " . APP_URL . "\n";
echo "Server IP: " . SERVER_IP . "\n";
echo "Server Port: " . SERVER_PORT . "\n";
echo "Timezone: " . date_default_timezone_get() . "\n";
echo "Current Time: " . date('Y-m-d H:i:s') . "\n";
echo "PHP Version: " . phpversion() . "\n";
echo "Max Upload: " . ini_get('upload_max_filesize') . "\n";
echo "Post Max: " . ini_get('post_max_size') . "\n";
echo "Max Execution Time: " . ini_get('max_execution_time') . "s\n";

echo "\n";

// Test 6: Email Configuration
echo "Test 6: Email Configuration...\n";
echo "-----------------------------------\n";
echo "SMTP Host: " . SMTP_HOST . "\n";
echo "SMTP Port: " . SMTP_PORT . "\n";
echo "SMTP Encryption: " . SMTP_ENCRYPTION . "\n";
echo "From Email: " . SMTP_FROM_EMAIL . "\n";
echo "From Name: " . SMTP_FROM_NAME . "\n";
echo "Daily Recipients: " . DAILY_REPORT_RECIPIENTS . "\n";

echo "\n";

// Final Summary
echo "============================================\n";
echo "  Test Summary\n";
echo "============================================\n";

$all_tests_passed = $extensions_ok;
try {
    getMySQLConnection();
    echo colorOutput("✓ MySQL: READY\n", 'green');
} catch (Exception $e) {
    echo colorOutput("✗ MySQL: NOT READY\n", 'red');
    $all_tests_passed = false;
}

try {
    getMSSQLConnection();
    echo colorOutput("✓ MSSQL: READY\n", 'green');
} catch (Exception $e) {
    echo colorOutput("✗ MSSQL: NOT READY\n", 'red');
    $all_tests_passed = false;
}

echo "\n";

if ($all_tests_passed) {
    echo colorOutput("🎉 All tests passed! System is ready.\n", 'green');
    exit(0);
} else {
    echo colorOutput("⚠ Some tests failed. Please review the errors above.\n", 'yellow');
    exit(1);
}
