<?php
/**
 * config.example.php
 * 
 * Example configuration file for SVP Web Application.
 * Copy this file to config.php and update credentials accordingly.
 */

// ------------------ MySQL ------------------
define("DB_HOST", "localhost");
define("DB_NAME", "svp");
define("DB_USER", "your_mysql_user");
define("DB_PASS", "your_mysql_password");

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die("MySQL connection failed: " . htmlspecialchars($e->getMessage()));
}

// ------------------ MSSQL ------------------
// For Windows/XAMPP: use pdo_sqlsrv
// For Ubuntu/Linux: use pdo_odbc (DSN defined in /etc/odbc.ini)
$server   = "127.0.0.1,1433";  // or use DSN: 'odbc:MSSQL_SVP'
$dbname   = "svp";
$username = "your_mssql_user";
$password = "your_mssql_password";

if (extension_loaded("pdo_sqlsrv")) {
    $dsn = "sqlsrv:Server=$server;Database=$dbname";
} elseif (extension_loaded("pdo_odbc")) {
    $dsn = "odbc:MSSQL_SVP";
} else {
    die("No suitable MSSQL driver found (pdo_sqlsrv or odbc required).");
}

try {
    $sqlsrv_pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("MSSQL connection failed: " . $e->getMessage());
}

// ------------------ SMTP / Email ------------------
define('SMTP_HOST', 'smtp.yourserver.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your_email@example.com');
define('SMTP_PASS', 'your_email_password');
define('SMTP_FROM_EMAIL', 'your_email@example.com');
define('SMTP_FROM_NAME', 'Your App Name');
define('SMTP_BCC', 'optional_bcc@example.com');

