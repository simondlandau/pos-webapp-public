<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once "config.php"; // contains $sqlsrv_pdo + mailer config

// Load PHPMailer
require_once "vendor/autoload.php";
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ==================== LOGGING SETUP ====================
$logFile = __DIR__ . '/send_daily_log.txt';

function writeLog($message) {
    global $logFile;
    static $firstWrite = true;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] {$message}\n";
    
    // First write overwrites, subsequent writes append
    if ($firstWrite) {
        file_put_contents($logFile, $logMessage);
        $firstWrite = false;
    } else {
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
    
    error_log($message); // Also keep standard error_log
}

// Start logging
writeLog("========== SCRIPT STARTED ==========");
writeLog("PHP Version: " . phpversion());
writeLog("Script path: " . __FILE__);

function sendMail($to, $name, $subject, $body, $config, $logoPath = null) {
    $mail = new PHPMailer(true);
    try {
        writeLog("Attempting to send email to: {$to}");
        
        $mail->isSMTP();
        $mail->Host       = $config['mailHost'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['mailUsername'];
        $mail->Password   = $config['mailPassword'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $config['mailPort'];
        
        writeLog("SMTP Config - Host: {$mail->Host}, Port: {$mail->Port}, User: {$mail->Username}, Pass length: " . strlen($config['mailPassword']));
        
        // Enable verbose debug output
        $mail->SMTPDebug = 2;
        $mail->Debugoutput = function($str, $level) {
            writeLog("SMTP DEBUG [{$level}]: {$str}");
        };
        
        // Set character encoding for proper Euro symbol display
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->setFrom($config['mailFrom'], 'SVP Finance');
        $mail->addAddress($to, $name);
        
        // Embed logo as inline attachment if path provided
        if ($logoPath && file_exists($logoPath)) {
            $mail->addEmbeddedImage($logoPath, 'logo_cid', 'svplogo.png');
            writeLog("Logo embedded from: {$logoPath}");
        } else {
            writeLog("Logo NOT embedded - path: " . ($logoPath ?? 'null') . ", exists: " . (file_exists($logoPath ?? '') ? 'yes' : 'no'));
        }
        
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        
        writeLog("Email prepared - Subject: {$subject}");
        writeLog("Body length: " . strlen($body) . " characters");
        
        $result = $mail->send();
        writeLog("✓ PHPMailer send() returned TRUE for {$to}");
        return true;
        
    } catch (Exception $e) {
        writeLog("✗ EXCEPTION for {$to}: {$mail->ErrorInfo}");
        writeLog("Exception details: " . $e->getMessage());
        writeLog("Stack trace: " . $e->getTraceAsString());
        return false;
    }
}

// ------------------ Step 1: Check MSSQL CashDecHeader has records today ------------------
try {
    writeLog("Checking for CashDecHeader records today...");
    $stmt = $sqlsrv_pdo->query("
        SELECT TOP 1 1 
        FROM CashDecHeader 
        WHERE CAST(dtTimeStamp AS DATE) = CAST(GETDATE() AS DATE)
    ");
    $hasTender = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$hasTender) {
        writeLog("No DECLARED records for today, aborting email send.");
        exit;
    }
    writeLog("CashDecHeader records found for today");
} catch (PDOException $e) {
    writeLog("MSSQL check failed: " . $e->getMessage());
    exit;
}

// ------------------ Step 1.5: MSSQL Aggregates ------------------
$Loyalty = $Donations = $zCount = $cashSales = $allOtherSales = $currentFloat = $Lodge = $Difference = 0.0;

try {
    writeLog("Fetching Loyalty data...");
    $stmt = $sqlsrv_pdo->query("
        SELECT SUM(cdl.TillTotal) AS Loyalty
        FROM CashDecLines cdl
        INNER JOIN CashDecHeader cdh ON cdl.CashDecRef = cdh.CashDecRef
        WHERE CAST(cdh.dtTimeStamp AS DATE) = CAST(GETDATE() AS DATE)
          AND cdl.PaymentNo = '10'
    ");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $Loyalty = (float)($row['Loyalty'] ?? 0);
    writeLog("Loyalty: " . $Loyalty);
} catch (PDOException $e) {
    writeLog("Loyalty query failed: " . $e->getMessage());
}

try {
    writeLog("Fetching Donations data...");
    $stmt = $sqlsrv_pdo->query("
        SELECT SUM(SN_Actual) AS Donations 
        FROM SALES 
        WHERE PostedDate = CONCAT(CAST(GETDATE() AS DATE),' 00:00:00') 
          AND SN_ITEM = 'DONAT01'
        GROUP BY SN_ITEM
    ");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $Donations = (float)($row['Donations'] ?? 0);
    writeLog("Donations: " . $Donations);
} catch (PDOException $e) {
    writeLog("Donations query failed: " . $e->getMessage());
}

try {
    writeLog("Fetching previous day's float...");
    $stmt = $sqlsrv_pdo->query("
        SELECT TOP 1 SUM(cdl.FloatHeld) AS PrevFloatHeld
        FROM CashDecLines cdl
        INNER JOIN CashDecHeader cdh ON cdl.CashDecRef = cdh.CashDecRef
        WHERE CAST(cdh.dtTimeStamp AS DATE) < CAST(GETDATE() AS DATE)
          AND cdl.PaymentNo = '01'
        GROUP BY CAST(cdh.dtTimeStamp AS DATE)
        ORDER BY CAST(cdh.dtTimeStamp AS DATE) DESC
    ");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $prevFloatHeld = $row ? (float)($row['PrevFloatHeld'] ?? 0) : 0;
    writeLog("Previous Float: " . $prevFloatHeld);
} catch (PDOException $e) {
    writeLog("Previous Float query failed: " . $e->getMessage());
    $prevFloatHeld = 0;
}

try {
    writeLog("Fetching All Other Sales...");
    $stmt = $sqlsrv_pdo->query("
        SELECT (SUM(cdl.TillTotal) - ($Loyalty)) AS AE
        FROM CashDecLines cdl
        INNER JOIN CashDecHeader cdh ON cdl.CashDecRef = cdh.CashDecRef
        WHERE CAST(cdh.dtTimeStamp AS DATE) = CAST(GETDATE() AS DATE)
          AND cdl.PaymentNo = '04'
    ");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $allOtherSales = (float)($row['AE'] ?? 0);
    writeLog("All Other Sales: " . $allOtherSales);
} catch (PDOException $e) {
    writeLog("All Other Sales query failed: " . $e->getMessage());
}

try {
    writeLog("Fetching Cash Payments...");
    $stmt = $sqlsrv_pdo->query("
         SELECT SUM(t.PN_CURR) AS CP
        FROM svp.dbo.TENDER t
        WHERE CAST(t.dtTimeStamp AS DATE) = CAST(GETDATE() AS DATE)
          AND t.PN_RECTYPE  ='13'
    ");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $CP = (float)($row['CP'] ?? 0);
    writeLog("Cash Payments: " . $CP);
} catch (PDOException $e) {
    writeLog("Cash Payments query failed: " . $e->getMessage());
}

try {
    writeLog("Fetching Cash Sales...");
    $stmt = $sqlsrv_pdo->query("
              SELECT (SUM(cdl.UserTotal) - SUM(cdl.FloatHeld)) AS CashSales
        FROM CashDecLines cdl
        INNER JOIN CashDecHeader cdh ON cdl.CashDecRef = cdh.CashDecRef
              WHERE CAST(cdh.dtTimeStamp AS DATE) = CAST(GETDATE() AS DATE)
          AND cdl.PaymentNo = '01'
    ");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $cashSales = (float)($row['CashSales'] ?? 0);
    writeLog("Cash Sales: " . $cashSales);
} catch (PDOException $e) {
    writeLog("Cash Sales query failed: " . $e->getMessage());
}

try {
    writeLog("Fetching Current Float...");
    $stmt = $sqlsrv_pdo->query("
        SELECT SUM(cdl.FloatHeld) AS FloatHeld
        FROM CashDecLines cdl
        INNER JOIN CashDecHeader cdh ON cdl.CashDecRef = cdh.CashDecRef
        WHERE CAST(cdh.dtTimeStamp AS DATE) = CAST(GETDATE() AS DATE)
          AND cdl.PaymentNo = '01'
    ");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $currentFloat = (float)($row['FloatHeld'] ?? 0);
    writeLog("Current Float: " . $currentFloat);
} catch (PDOException $e) {
    writeLog("Current Float query failed: " . $e->getMessage());
}

try {
    writeLog("Fetching Lodge...");
    $stmt = $sqlsrv_pdo->query("
        SELECT SUM(cdl.Lodged) AS Lodge
        FROM CashDecLines cdl
        INNER JOIN CashDecHeader cdh ON cdl.CashDecRef = cdh.CashDecRef
        WHERE CAST(cdh.dtTimeStamp AS DATE) = CAST(GETDATE() AS DATE)
          AND cdl.PaymentNo = '01'
    ");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $Lodge = (float)($row['Lodge'] ?? 0);
    writeLog("Lodge: " . $Lodge);
} catch (PDOException $e) {
    writeLog("Lodge query failed: " . $e->getMessage());
}

try {
    writeLog("Fetching Difference...");
    $stmt = $sqlsrv_pdo->query("
        SELECT SUM(cdl.Difference) AS Difference
        FROM CashDecLines cdl
        INNER JOIN CashDecHeader cdh ON cdl.CashDecRef = cdh.CashDecRef
        WHERE CAST(cdh.dtTimeStamp AS DATE) = CAST(GETDATE() AS DATE)
          AND cdl.PaymentNo = '01'
    ");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $Difference = (float)($row['Difference'] ?? 0);
    writeLog("Difference: " . $Difference);
} catch (PDOException $e) {
    writeLog("Difference query failed: " . $e->getMessage());
}

try {
    writeLog("Fetching Difference Reason...");
    $stmt = $sqlsrv_pdo->query("
SELECT cdh.ReasonText AS Reason
        FROM svp.dbo.CashDecHeader cdh
        WHERE CAST(cdh.dtTimeStamp AS DATE) = CAST(GETDATE() AS DATE)
    ");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $Reason = ($row['Reason'] ?? 0);
    writeLog("Reason: " . $Reason);
} catch (PDOException $e) {
    writeLog("Reason query failed: " . $e->getMessage());
}

try {
    writeLog("Fetching Operator...");
    $stmt = $sqlsrv_pdo->query("
SELECT emp.EM_NAME AS Operator
        FROM svp.dbo.EMPLOY emp
        INNER JOIN svp.dbo.CashDecHeader cdh ON emp.EM_CODE = cdh.EmpDecBy
        WHERE CAST(cdh.dtTimeStamp AS DATE) = CAST(GETDATE() AS DATE)
    ");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $Operator = ($row['Operator'] ?? 0);
    writeLog("Operator: " . $Operator);
} catch (PDOException $e) {
    writeLog("Operator query failed: " . $e->getMessage());
}

try {
    $zCount = ((($currentFloat - $prevFloatHeld) + $allOtherSales + $Lodge) - $Difference);
    writeLog("Z Count calculated: " . $zCount);
} catch (Exception $e) {
    writeLog("Z Count calculation failed: " . $e->getMessage());
    $zCount = 0;
}

try {
    $allSales = ($cashSales + $allOtherSales + $CP);
    writeLog("All Sales calculated: " . $allSales);
} catch (Exception $e) {
    writeLog("All Sales calculation failed: " . $e->getMessage());
    $allSales = 0;
}

// ------------------ Step 2: Get list of subscribed users from MySQL ------------------
try {
    writeLog("Fetching subscribed users from MySQL...");
    $stmt = $pdo->prepare("SELECT forename, email FROM svp.users WHERE receive = 1");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if (empty($users)) {
        writeLog("No subscribed users found, no emails sent.");
        exit;
    }
    writeLog("Found " . count($users) . " subscribed users");
    foreach ($users as $user) {
        writeLog("  - {$user['forename']} <{$user['email']}>");
    }
} catch (PDOException $e) {
    writeLog("Failed to fetch users: " . $e->getMessage());
    exit;
}

// ------------------ Step 3: Build email content ------------------
$currency = fn($v) => "&euro;" . number_format($v, 2);

$logoPath = 'svplogo.png';

if (file_exists($logoPath)) {
    writeLog("Logo file confirmed at: {$logoPath}");
    writeLog("Logo file size: " . filesize($logoPath) . " bytes");
    writeLog("Logo file readable: " . (is_readable($logoPath) ? 'yes' : 'no'));
} else {
    writeLog("WARNING: Logo file not found at: {$logoPath}");
    $logoPath = null;
}

$emailHeader = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .header { text-align: center; padding: 20px; background: #f8f9fa; border-bottom: 3px solid #007bff; }
        .logo { height: 50px; margin-bottom: 10px; }
        .content { padding: 20px; max-width: 600px; margin: 0 auto; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        td { padding: 12px; border: 1px solid #ddd; }
        tr:nth-child(even) { background: #f8f9fa; }
        .label { font-weight: bold; width: 60%; }
        .value { text-align: right; font-family: 'Courier New', monospace; }
        .footer { text-align: center; padding: 20px; background: #f8f9fa; border-top: 1px solid #ddd; margin-top: 30px; font-size: 0.9em; color: #666; }
    </style>
</head>
<body>
    <div class='header'>
        " . ($logoPath ? "<img src='cid:logo_cid' alt='SVP Logo' class='logo' />" : "<h2>SVP Finance</h2>") . "
        <h1 style='margin: 10px 0; color: #007bff;'>End of Day Report</h1>
        <p style='margin: 5px 0; color: #666;'>" . date("l, F j, Y") . "</p>
    </div>
    <div class='content'>
";

$emailFooter = "
    </div>
    <div class='footer'>
        <p><strong>St. Vincent de Paul</strong><br>
        Main Street, Letterkenny<br>
        This is an automated report. Please do not reply to this email.</p>
    </div>
</body>
</html>
";

$table = "
<table>
<tr><td class='label'>Current Float</td><td class='value'>{$currency($currentFloat)}</td></tr>
<tr><td class='label'>Previous Float</td><td class='value'>{$currency($prevFloatHeld)}</td></tr>
<tr><td class='label'>Z Count</td><td class='value'>{$currency($zCount)}</td></tr>
<tr><td class='label'>Cash Sales</td><td class='value'>{$currency($cashSales)}</td></tr>
<tr><td class='label'>All Other Sales</td><td class='value'>{$currency($allOtherSales)}</td></tr>
<tr><td class='label'>Donations</td><td class='value'>{$currency($Donations)}</td></tr>
<tr><td class='label'>Loyalty</td><td class='value'>{$currency($Loyalty)}</td></tr>
<tr><td class='label'>Cash Payments</td><td class='value'>{$currency($CP)}</td></tr>
<tr><td class='label'>All Sales</td><td class='value'>{$currency($allSales)}</td></tr>
<tr><td class='label'>Difference</td><td class='value'>{$currency($Difference)}</td></tr>
<tr><td class='label'>Reason</td><td class='value'>$Reason</td></tr>
<tr><td class='label'>Operator</td><td class='value'>$Operator</td></tr>
<tr><td class='label'>Lodge</td><td class='value'>{$currency($Lodge)}</td></tr>
</table>
";

// ------------------ Step 4: Prepare config array ------------------
$mailConfig = [
    'mailHost'     => SMTP_HOST,
    'mailPort'     => SMTP_PORT,
    'mailUsername' => SMTP_USER,
    'mailPassword' => SMTP_PASS,
    'mailFrom'     => SMTP_FROM_EMAIL
];

writeLog("Mail config prepared - Host: " . SMTP_HOST . ", Port: " . SMTP_PORT . ", From: " . SMTP_FROM_EMAIL);

// ------------------ Step 5: Send emails ------------------
$successCount = 0;
$failCount = 0;

writeLog("========== BEGINNING EMAIL SEND LOOP ==========");

foreach ($users as $user) {
    $to = $user['email'];
    $name = $user['forename'];
    $subject = "End of Day Report - " . date("Y-m-d");
    
    writeLog("--- Processing user: {$name} <{$to}> ---");
    
    $body = $emailHeader . "
        <p>Dear <strong>{$name}</strong>,</p>
        <p>Please find today's end of day figures below:</p>
        {$table}
        <p style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 0.9em;'>
            If you have any questions about these figures, please contact the finance team.
        </p>
    " . $emailFooter;
    
    if (sendMail($to, $name, $subject, $body, $mailConfig, $logoPath)) {
        writeLog("✓✓✓ Email sent successfully to {$to}");
        $successCount++;
    } else {
        writeLog("✗✗✗ Failed to send email to {$to}");
        $failCount++;
    }
    
    writeLog(""); // Blank line for readability
}

writeLog("========== EMAIL SEND LOOP COMPLETED ==========");
writeLog("Final Summary: {$successCount} sent, {$failCount} failed");
writeLog("========== SCRIPT ENDED ==========");
writeLog("");
writeLog("");

echo "Email process completed.\n";
echo "Successfully sent: {$successCount}\n";
echo "Failed: {$failCount}\n";
echo "Check log file at: {$logFile}";
