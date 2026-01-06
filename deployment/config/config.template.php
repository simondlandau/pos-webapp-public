<?php
/**
 * Configuration File Template
 * Copy this file to config.php and update with your settings
 */

// ============================================
// DATABASE CONFIGURATION
// ============================================

// MySQL Configuration (Local database)
define('MYSQL_HOST', 'localhost');
define('MYSQL_PORT', '3306');
define('MYSQL_DATABASE', 'your_database_name');
define('MYSQL_USERNAME', 'root');
define('MYSQL_PASSWORD', '');
define('MYSQL_CHARSET', 'utf8mb4');

// MSSQL Configuration (POS database)
define('MSSQL_SERVER', '192.168.1.1');  // Your POS server IP
define('MSSQL_PORT', '1433');
define('MSSQL_DATABASE', 'your_mssql_database');
define('MSSQL_USERNAME', 'your_mssql_username');
define('MSSQL_PASSWORD', 'your_mssql_password');

// ============================================
// APPLICATION CONFIGURATION
// ============================================

// Application URL
define('APP_URL', 'http://192.168.1.10:9090/webapp');
define('APP_NAME', 'Web Application');

// Server IP and Port
define('SERVER_IP', '192.168.1.10');
define('SERVER_PORT', '9090');

// Timezone
date_default_timezone_set('America/New_York');

// Error Reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Logging
define('LOG_PATH', __DIR__ . '/logs/');
define('LOG_LEVEL', 'DEBUG'); // DEBUG, INFO, WARNING, ERROR

// ============================================
// EMAIL CONFIGURATION
// ============================================

// SMTP Settings
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your_email@gmail.com');
define('SMTP_PASSWORD', 'your_app_password');
define('SMTP_ENCRYPTION', 'tls'); // tls or ssl
define('SMTP_FROM_EMAIL', 'your_email@gmail.com');
define('SMTP_FROM_NAME', 'Web Application');

// Email Recipients for Daily Reports
define('DAILY_REPORT_RECIPIENTS', 'recipient1@example.com,recipient2@example.com');

// ============================================
// SECURITY CONFIGURATION
// ============================================

// Session Settings
define('SESSION_LIFETIME', 7200); // 2 hours in seconds
define('SESSION_NAME', 'webapp_session');

// API Keys (if needed)
define('API_KEY', 'generate_random_key_here');

// Allowed IPs for sensitive operations (comma-separated)
define('ALLOWED_IPS', '192.168.1.0/24,127.0.0.1');

// ============================================
// POS SYNC CONFIGURATION
// ============================================

// Sync Intervals (in seconds)
define('SYNC_INTERVAL', 300); // 5 minutes
define('SYNC_ENABLED', true);

// Sync Tables Configuration
$sync_tables = [
    'sales' => [
        'mssql_table' => 'Sales',
        'mysql_table' => 'pos_sales',
        'primary_key' => 'sale_id'
    ],
    'inventory' => [
        'mssql_table' => 'Inventory',
        'mysql_table' => 'pos_inventory',
        'primary_key' => 'item_id'
    ],
    // Add more tables as needed
];

// ============================================
// FILE UPLOAD CONFIGURATION
// ============================================

define('UPLOAD_MAX_SIZE', 5242880); // 5MB in bytes
define('UPLOAD_ALLOWED_TYPES', 'jpg,jpeg,png,pdf,xlsx,csv');
define('UPLOAD_PATH', __DIR__ . '/uploads/');

// ============================================
// DATABASE CONNECTION FUNCTIONS
// ============================================

/**
 * Get MySQL PDO Connection
 */
function getMySQLConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                MYSQL_HOST,
                MYSQL_PORT,
                MYSQL_DATABASE,
                MYSQL_CHARSET
            );
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $pdo = new PDO($dsn, MYSQL_USERNAME, MYSQL_PASSWORD, $options);
        } catch (PDOException $e) {
            error_log('MySQL Connection Error: ' . $e->getMessage());
            throw new Exception('Database connection failed');
        }
    }
    
    return $pdo;
}

/**
 * Get MSSQL PDO Connection
 */
function getMSSQLConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = sprintf(
                'sqlsrv:Server=%s,%s;Database=%s',
                MSSQL_SERVER,
                MSSQL_PORT,
                MSSQL_DATABASE
            );
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ];
            
            $pdo = new PDO($dsn, MSSQL_USERNAME, MSSQL_PASSWORD, $options);
        } catch (PDOException $e) {
            error_log('MSSQL Connection Error: ' . $e->getMessage());
            throw new Exception('POS database connection failed');
        }
    }
    
    return $pdo;
}

/**
 * Log message to file
 */
function logMessage($message, $level = 'INFO', $file = 'app.log') {
    $timestamp = date('Y-m-d H:i:s');
    $logFile = LOG_PATH . $file;
    $logEntry = "[$timestamp] [$level] $message" . PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

// ============================================
// INITIALIZE
// ============================================

// Create directories if they don't exist
$directories = [LOG_PATH, UPLOAD_PATH];
foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
    
    // Set session timeout
    if (isset($_SESSION['LAST_ACTIVITY']) && 
        (time() - $_SESSION['LAST_ACTIVITY'] > SESSION_LIFETIME)) {
        session_unset();
        session_destroy();
        session_start();
    }
    $_SESSION['LAST_ACTIVITY'] = time();
}
