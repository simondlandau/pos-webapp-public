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

function sendMail($to, $name, $subject, $body, $config, $logoPath = null) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $config['mailHost'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['mailUsername'];
        $mail->Password   = $config['mailPassword'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $config['mailPort'];
        
        // Set character encoding for proper Euro symbol display
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';

        $mail->setFrom($config['mailFrom'], 'SVP Finance');
        $mail->addAddress($to, $name);

        // Embed logo as inline attachment if path provided
        if ($logoPath && file_exists($logoPath)) {
            $mail->addEmbeddedImage($logoPath, 'logo_cid', 'svplogo.png');
            error_log("Logo embedded for {$to}");
        } else {
            error_log("Logo NOT embedded for {$to} - path: " . ($logoPath ?? 'null'));
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mailer Error ({$to}): {$mail->ErrorInfo}");
        return false;
    }
}

// ------------------ Step 1: Check MSSQL CashDecHeader has records today ------------------
try {
    $stmt = $sqlsrv_pdo->query("
        SELECT TOP 1 1 
        FROM CashDecHeader 
        WHERE CAST(dtTimeStamp AS DATE) = CAST(GETDATE() AS DATE)
    ");
    $hasTender = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$hasTender) {
        error_log("No DECLARED records for today, aborting email send.");
        exit;
    }
} catch (PDOException $e) {
    error_log("MSSQL check failed: " . $e->getMessage());
    exit;
}

// ------------------ Step 1.5: MSSQL Aggregates ------------------
$Loyalty = $Donations = $zCount = $cashSales = $allOtherSales = $currentFloat = $Lodge = $Difference = 0.0;

try {
    // Loyalty from CashDecLines (PaymentNo = '10')
    $stmt = $sqlsrv_pdo->query("
        SELECT SUM(cdl.TillTotal) AS Loyalty
        FROM CashDecLines cdl
        INNER JOIN CashDecHeader cdh ON cdl.CashDecRef = cdh.CashDecRef
        WHERE CAST(cdh.dtTimeStamp AS DATE) = CAST(GETDATE() AS DATE)
          AND cdl.PaymentNo = '10'
    ");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $Loyalty = (float)($row['Loyalty'] ?? 0);
} catch (PDOException $e) {
    error_log("Loyalty query failed: " . $e->getMessage());
}

try {
    // Donations
    $stmt = $sqlsrv_pdo->query("
        SELECT SUM(SN_Actual) AS Donations 
        FROM SALES 
        WHERE PostedDate = CONCAT(CAST(GETDATE() AS DATE),' 00:00:00') 
          AND SN_ITEM = 'DONAT01'
        GROUP BY SN_ITEM
    ");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $Donations = (float)($row['Donations'] ?? 0);
} catch (PDOException $e) {
    error_log("Donations query failed: " . $e->getMessage());
}

try {
    // Get previous day's float (fallback to earlier days if needed)
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
} catch (PDOException $e) {
    error_log("Previous Float query failed: " . $e->getMessage());
    $prevFloatHeld = 0;
}

try {
    // All Other Sales from CashDecLines (PaymentNo = '04')
    $stmt = $sqlsrv_pdo->query("
        SELECT (SUM(cdl.TillTotal) - ($Loyalty)) AS AE
        FROM CashDecLines cdl
        INNER JOIN CashDecHeader cdh ON cdl.CashDecRef = cdh.CashDecRef
        WHERE CAST(cdh.dtTimeStamp AS DATE) = CAST(GETDATE() AS DATE)
          AND cdl.PaymentNo = '04'
    ");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $allOtherSales = (float)($row['AE'] ?? 0);
} catch (PDOException $e) {
    error_log("All Other Sales query failed: " . $e->getMessage());
}

try {
// Cash Payments (CP) - PN_RECTYPE = '13'
    $stmt = $sqlsrv_pdo->query("
         SELECT SUM(t.PN_CURR) AS CP
        FROM svp.dbo.TENDER t
        WHERE CAST(t.dtTimeStamp AS DATE) = CAST(GETDATE() AS DATE)
          AND t.PN_RECTYPE  ='13'

    ");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $CP = (float)($row['CP'] ?? 0);
} catch (PDOException $e) {
    error_log("Cash Payments query failed: " . $e->getMessage());
}

try {
    // Cash Sales from CashDecLines
    $stmt = $sqlsrv_pdo->query("
              SELECT (SUM(cdl.UserTotal) - SUM(cdl.FloatHeld)) AS CashSales
        FROM CashDecLines cdl
        INNER JOIN CashDecHeader cdh ON cdl.CashDecRef = cdh.CashDecRef
              WHERE CAST(cdh.dtTimeStamp AS DATE) = CAST(GETDATE() AS DATE)
          AND cdl.PaymentNo = '01'
    ");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $cashSales = (float)($row['CashSales'] ?? 0);
} catch (PDOException $e) {
    error_log("Cash Sales query failed: " . $e->getMessage());
}

try {
    // Current Float from CashDecLines (PaymentNo = '01')
    $stmt = $sqlsrv_pdo->query("
        SELECT SUM(cdl.FloatHeld) AS FloatHeld
        FROM CashDecLines cdl
        INNER JOIN CashDecHeader cdh ON cdl.CashDecRef = cdh.CashDecRef
        WHERE CAST(cdh.dtTimeStamp AS DATE) = CAST(GETDATE() AS DATE)
          AND cdl.PaymentNo = '01'
    ");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $currentFloat = (float)($row['FloatHeld'] ?? 0);
} catch (PDOException $e) {
    error_log("Current Float query failed: " . $e->getMessage());
}

try {
    // Lodge from CashDecLines (PaymentNo = '01')
    $stmt = $sqlsrv_pdo->query("
        SELECT SUM(cdl.Lodged) AS Lodge
        FROM CashDecLines cdl
        INNER JOIN CashDecHeader cdh ON cdl.CashDecRef = cdh.CashDecRef
        WHERE CAST(cdh.dtTimeStamp AS DATE) = CAST(GETDATE() AS DATE)
          AND cdl.PaymentNo = '01'
    ");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $Lodge = (float)($row['Lodge'] ?? 0);
} catch (PDOException $e) {
    error_log("Lodge query failed: " . $e->getMessage());
}

try {
    // Difference from CashDecLines (PaymentNo = '01')
    $stmt = $sqlsrv_pdo->query("
        SELECT SUM(cdl.Difference) AS Difference
        FROM CashDecLines cdl
        INNER JOIN CashDecHeader cdh ON cdl.CashDecRef = cdh.CashDecRef
        WHERE CAST(cdh.dtTimeStamp AS DATE) = CAST(GETDATE() AS DATE)
          AND cdl.PaymentNo = '01'
    ");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $Difference = (float)($row['Difference'] ?? 0);
} catch (PDOException $e) {
    error_log("Difference query failed: " . $e->getMessage());
}

try {
    // Z Count calculation: (((CurrentFloat + AE + Lodge) - PreviousDayFloat) - Difference)
    $zCount = ((($currentFloat - $prevFloatHeld) + $allOtherSales + $Lodge) - $Difference);
} catch (Exception $e) {
    error_log("Z Count calculation failed: " . $e->getMessage());
    $zCount = 0;
}

try {
    // All Sales calculation: allSales = (cashSales + allOtherSales + CP)
    $allSales = ($cashSales + $allOtherSales + $CP);
} catch (Exception $e) {
    error_log("All Sales calculation failed: " . $e->getMessage());
    $zCount = 0;
}

// ------------------ Step 2: Get list of subscribed users from MySQL ------------------
try {
    $stmt = $pdo->prepare("SELECT forename, email FROM svp.users WHERE receive = 1");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if (empty($users)) {
        error_log("No subscribed users found, no emails sent.");
        exit;
    }
} catch (PDOException $e) {
    error_log("Failed to fetch users: " . $e->getMessage());
    exit;
}

// ------------------ Step 3: Build email content ------------------
// Use HTML entity for Euro symbol to ensure proper display across all email clients
$currency = fn($v) => "&euro;" . number_format($v, 2);

// Logo path - confirmed working from test
$logoPath = '/var/www/finance/svp/svplogo.png';

// Verify logo exists
if (file_exists($logoPath)) {
    error_log("Logo file confirmed at: {$logoPath}");
} else {
    error_log("WARNING: Logo file not found at: {$logoPath}");
    $logoPath = null;
}

// Build email header - use CID (Content-ID) reference for inline image
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

// Build data table
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

// ------------------ Step 5: Send emails ------------------
$successCount = 0;
$failCount = 0;

foreach ($users as $user) {
    $to = $user['email'];
    $name = $user['forename'];
    $subject = "End of Day Report - " . date("Y-m-d");
    
    // Build personalized email body
    $body = $emailHeader . "
        <p>Dear <strong>{$name}</strong>,</p>
        <p>Please find today's end of day figures below:</p>
        {$table}
        <p style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 0.9em;'>
            If you have any questions about these figures, please contact the finance team.
        </p>
    " . $emailFooter;
    
    if (sendMail($to, $name, $subject, $body, $mailConfig, $logoPath)) {
        error_log("✓ Email sent successfully to {$to}");
        $successCount++;
    } else {
        error_log("✗ Failed to send email to {$to}");
        $failCount++;
    }
}

// Log summary
error_log("Email process completed: {$successCount} sent, {$failCount} failed");
echo "Email process completed.\n";
echo "Successfully sent: {$successCount}\n";
echo "Failed: {$failCount}\n";
echo "Check logs for details.";
