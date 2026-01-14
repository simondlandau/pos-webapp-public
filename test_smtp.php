<?php
// test_smtp.php - Test SMTP credentials
require_once "vendor/autoload.php";
use PHPMailer\PHPMailer\PHPMailer;

echo "<h2>SMTP Credential Test</h2>";
echo "<pre>";

// Test 1: Check config file
echo "=== TEST 1: Checking config.php ===\n";
require_once "config.php";

echo "SMTP_HOST: " . SMTP_HOST . "\n";
echo "SMTP_PORT: " . SMTP_PORT . "\n";
echo "SMTP_USER: " . SMTP_USER . "\n";
echo "SMTP_PASS length: " . strlen(SMTP_PASS) . " characters\n";
echo "SMTP_PASS (masked): " . substr(SMTP_PASS, 0, 4) . str_repeat('*', strlen(SMTP_PASS)-8) . substr(SMTP_PASS, -4) . "\n";

// Check for spaces
$spaceCount = substr_count(SMTP_PASS, ' ');
echo "Spaces in password: " . $spaceCount . "\n";

// Check for invisible characters
$cleanPass = preg_replace('/[^\x20-\x7E]/', '', SMTP_PASS);
if (strlen($cleanPass) != strlen(SMTP_PASS)) {
    echo "⚠️ WARNING: Password contains invisible/special characters!\n";
    echo "Original length: " . strlen(SMTP_PASS) . "\n";
    echo "Clean length: " . strlen($cleanPass) . "\n";
} else {
    echo "✓ No invisible characters detected\n";
}

// Test 2: Check Google account settings
echo "\n=== TEST 2: Google Account Checklist ===\n";
echo "Please verify at https://myaccount.google.com/security:\n";
echo "1. 2-Step Verification is ON\n";
echo "2. App password was generated AFTER enabling 2FA\n";
echo "3. 'Less secure app access' is NOT needed (deprecated)\n";
echo "4. No suspicious activity alerts\n";

// Test 3: Try connecting
echo "\n=== TEST 3: Attempting SMTP Connection ===\n";

$mail = new PHPMailer(true);

try {
    $mail->SMTPDebug = 2;
    $mail->Debugoutput = function($str, $level) {
        echo $str . "\n";
    };
    
    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USER;
    $mail->Password = SMTP_PASS;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = SMTP_PORT;
    
    $mail->setFrom(SMTP_USER, 'Test');
    $mail->addAddress(SMTP_USER); // Send to self
    
    $mail->isHTML(true);
    $mail->Subject = 'SMTP Test - ' . date('Y-m-d H:i:s');
    $mail->Body = 'If you receive this, SMTP is working correctly.';
    
    $result = $mail->send();
    
    if ($result) {
        echo "\n✓✓✓ SUCCESS! Email sent successfully.\n";
        echo "Check inbox at: " . SMTP_USER . "\n";
    }
    
} catch (Exception $e) {
    echo "\n✗✗✗ FAILED!\n";
    echo "Error: {$mail->ErrorInfo}\n";
    echo "Exception: {$e->getMessage()}\n";
}

echo "\n=== TEST 4: Alternative Password Input ===\n";
echo "If test failed, try entering password manually:\n";
echo "Remove ALL spaces from your app password\n";
echo "App password should be 16 characters (e.g., 'abcdefghijklmnop')\n";
echo "\nCurrent password format check:\n";
if (strlen(SMTP_PASS) == 16 && ctype_alnum(SMTP_PASS)) {
    echo "✓ Password is 16 alphanumeric characters (correct format)\n";
} elseif (strlen(SMTP_PASS) == 19 && substr_count(SMTP_PASS, ' ') == 3) {
    echo "⚠️ Password has spaces - should remove them\n";
    $noSpaces = str_replace(' ', '', SMTP_PASS);
    echo "Try this in config.php: define('SMTP_PASS', '$noSpaces');\n";
} else {
    echo "⚠️ Password format doesn't match expected app password format\n";
    echo "Length: " . strlen(SMTP_PASS) . " (should be 16)\n";
}

echo "</pre>";
